<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace assignsubmission_bloboffload\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Azure Blob SAS generation helper.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class azure_blob_storage_service {
    /** Maximum assignment file size supported by this uploader (20 GiB). */
    public const MAX_FILE_BYTES = 20 * 1024 * 1024 * 1024;
    /** Pending uploads may renew their SAS for up to one day. */
    public const MAX_UPLOAD_AGE_SECONDS = 24 * 60 * 60;
    /** @var int */
    private const DELETE_SAS_EXPIRY_SECONDS = 60;

    /** @var string */
    private $accountname;
    /** @var string */
    private $accountkey;
    /** @var string */
    private $containername;
    /** @var string */
    private $endpointsuffix;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->accountname = trim((string)get_config('assignsubmission_bloboffload', 'storageaccount'));
        $this->accountkey = (string)get_config('assignsubmission_bloboffload', 'accountkey');
        $this->containername = trim((string)get_config('assignsubmission_bloboffload', 'containername'));
        $this->endpointsuffix = trim((string)get_config('assignsubmission_bloboffload', 'endpointsuffix'));
        if ($this->endpointsuffix === '') {
            $this->endpointsuffix = 'core.windows.net';
        }
    }

    /**
     * Check whether enough config exists to sign SAS tokens.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->accountname !== '' && $this->accountkey !== '' && $this->containername !== '';
    }

    /**
     * Assert the storage service is configured.
     *
     * @return void
     */
    public function require_configuration(): void {
        if (!$this->is_configured()) {
            throw new \moodle_exception('error:azureconfigmissing', 'assignsubmission_bloboffload');
        }
    }

    /**
     * Get configured container name.
     *
     * @return string
     */
    public function get_container_name(): string {
        return $this->containername;
    }

    /**
     * Build the blob base URL.
     *
     * @param string $blobpath
     * @return string
     */
    public function get_blob_url(string $blobpath): string {
        $this->require_configuration();
        $blobpath = ltrim($blobpath, '/');
        return 'https://' . $this->accountname . '.blob.' . $this->endpointsuffix . '/' .
            $this->containername . '/' . str_replace('%2F', '/', rawurlencode($blobpath));
    }

    /**
     * Build an upload URL including a SAS token.
     *
     * @param string $blobpath
     * @param int $expiryseconds
     * @return array
     */
    public function get_upload_target(string $blobpath, int $expiryseconds): array {
        $bloburl = $this->get_blob_url($blobpath);
        $sas = $this->build_blob_sas($blobpath, 'cw', $expiryseconds);
        return [
            'bloburl' => $bloburl,
            'uploadurl' => $bloburl . '?' . $sas,
            'sasquery' => $sas,
            'expiresat' => time() + $expiryseconds,
        ];
    }

    /**
     * Build a read URL including a SAS token.
     *
     * @param string $blobpath
     * @param int $expiryseconds
     * @return string
     */
    public function get_read_url(string $blobpath, int $expiryseconds): string {
        $bloburl = $this->get_blob_url($blobpath);
        return $bloburl . '?' . $this->build_blob_sas($blobpath, 'r', $expiryseconds);
    }

    /**
     * Confirm that Azure has the uploaded bytes before recording a submission file.
     *
     * @param string $blobpath
     * @param int $expectedsize
     * @return string Azure ETag
     */
    public function verify_uploaded_blob(string $blobpath, int $expectedsize): string {
        $url = $this->get_read_url($blobpath, 60);
        $curl = new \curl();
        $curl->setHeader(['x-ms-version: 2023-11-03']);
        $response = $curl->head($url);
        $status = (int)($curl->get_info()['http_code'] ?? 0);

        if ($status !== 200 || !preg_match('/^content-length:\s*(\d+)\s*$/im', $response, $size) ||
                (int)$size[1] !== $expectedsize ||
                !preg_match('/^etag:\s*(\S+)\s*$/im', $response, $etag)) {
            throw new \moodle_exception('error:blobverificationfailed', 'assignsubmission_bloboffload');
        }

        return $etag[1];
    }

    /**
     * Delete a blob from Azure storage.
     *
     * Missing blobs are treated as already deleted.
     *
     * @param string $blobpath
     * @return void
     */
    public function delete_blob(string $blobpath): void {
        $url = $this->get_blob_url($blobpath) . '?' .
            $this->build_blob_sas($blobpath, 'd', self::DELETE_SAS_EXPIRY_SECONDS);
        // Moodle's curl::delete() sets HTTP Basic credentials, which Azure treats
        // as an Authorization header instead of using the SAS in the URL.
        $curl = new class extends \curl {
            /**
             * Send an Azure SAS DELETE without adding HTTP Basic credentials.
             *
             * @param string $url
             * @return string
             */
            public function delete_with_sas(string $url) {
                return $this->request($url, ['CURLOPT_CUSTOMREQUEST' => 'DELETE']);
            }
        };
        $curl->setHeader([
            'x-ms-version: 2023-11-03',
            'x-ms-delete-snapshots: include',
        ]);
        $response = $curl->delete_with_sas($url);
        $info = $curl->get_info();
        $statuscode = (int)($info['http_code'] ?? 0);

        if ($statuscode === 404) {
            return;
        }

        if ($statuscode < 200 || $statuscode >= 300) {
            $reason = 'HTTP ' . $statuscode;
            if (preg_match('/<Code>([A-Za-z0-9]+)<\/Code>/', (string)$response, $matches)) {
                $reason .= ': ' . $matches[1];
            } else if ($statuscode === 0) {
                $reason .= ', cURL error ' . $curl->get_errno();
            }
            throw new \moodle_exception(
                'error:blobdeletefailed',
                'assignsubmission_bloboffload',
                '',
                $reason
            );
        }
    }

    /**
     * Build a blob service SAS token.
     *
     * @param string $blobpath
     * @param string $permissions
     * @param int $expiryseconds
     * @return string
     */
    private function build_blob_sas(string $blobpath, string $permissions, int $expiryseconds): string {
        $this->require_configuration();

        $version = '2023-11-03';
        $resource = 'b';
        $protocol = 'https';
        $now = time();
        $start = gmdate('Y-m-d\TH:i:s\Z', $now - 300);
        $expiry = gmdate('Y-m-d\TH:i:s\Z', $now + max(60, $expiryseconds));
        $canonicalizedresource = '/blob/' . $this->accountname . '/' . $this->containername . '/' . ltrim($blobpath, '/');

        $stringtosign = implode("\n", [
            $permissions,
            $start,
            $expiry,
            $canonicalizedresource,
            '',
            '',
            $protocol,
            $version,
            $resource,
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ]);

        $signature = base64_encode(
            hash_hmac(
                'sha256',
                $stringtosign,
                base64_decode($this->accountkey),
                true
            )
        );

        return http_build_query([
            'sv' => $version,
            'spr' => $protocol,
            'st' => $start,
            'se' => $expiry,
            'sr' => $resource,
            'sp' => $permissions,
            'sig' => $signature,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
