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
        $curl = new \curl();
        $options = [
            'CURLOPT_CUSTOMREQUEST' => 'DELETE',
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HEADER' => true,
        ];
        $headers = [
            'x-ms-version: 2023-11-03',
        ];

        $response = $curl->get($url, null, $options, $headers);
        $info = $curl->get_info();
        $statuscode = (int)($info['http_code'] ?? 0);

        if ($statuscode === 404) {
            return;
        }

        if ($statuscode < 200 || $statuscode >= 300) {
            throw new \moodle_exception('error:blobdeletefailed', 'assignsubmission_bloboffload', '', null,
                'Azure returned HTTP ' . $statuscode . ' while deleting blob.');
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

        $signature = base64_encode(hash_hmac('sha256', $stringtosign, base64_decode($this->accountkey), true));

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
