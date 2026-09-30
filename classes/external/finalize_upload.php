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

namespace assignsubmission_bloboffload\external;

use assignsubmission_bloboffload\local\azure_blob_storage_service;
use assignsubmission_bloboffload\local\blob_path_builder;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Persist blob upload metadata after a successful direct upload.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finalize_upload extends external_base {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignid' => new external_value(PARAM_INT, 'Assignment instance id'),
            'uploadtoken' => new external_value(
                PARAM_ALPHANUMEXT,
                'Upload token from target creation'
            ),
            'blobpath' => new external_value(PARAM_RAW, 'Blob path'),
            'filename' => new external_value(PARAM_FILE, 'Original filename'),
            'filesize' => new external_value(PARAM_INT, 'File size'),
            'mimetype' => new external_value(PARAM_RAW, 'Mime type'),
            'etag' => new external_value(
                PARAM_RAW,
                'Azure blob etag',
                VALUE_DEFAULT,
                ''
            ),
            'contenthash' => new external_value(
                PARAM_RAW,
                'Optional content hash',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $assignid
     * @param string $uploadtoken
     * @param string $blobpath
     * @param string $filename
     * @param int $filesize
     * @param string $mimetype
     * @param string $etag
     * @param string $contenthash
     * @return array
     */
    public static function execute(
        int $assignid,
        string $uploadtoken,
        string $blobpath,
        string $filename,
        int $filesize,
        string $mimetype,
        string $etag = '',
        string $contenthash = ''
    ): array {
        global $USER;

        [
            'assignid' => $assignid,
            'uploadtoken' => $uploadtoken,
            'blobpath' => $blobpath,
            'filename' => $filename,
            'filesize' => $filesize,
            'mimetype' => $mimetype,
            'etag' => $etag,
            'contenthash' => $contenthash,
        ] = self::validate_parameters(self::execute_parameters(), [
            'assignid' => $assignid,
            'uploadtoken' => $uploadtoken,
            'blobpath' => $blobpath,
            'filename' => $filename,
            'filesize' => $filesize,
            'mimetype' => $mimetype,
            'etag' => $etag,
            'contenthash' => $contenthash,
        ]);

        $assignment = self::resolve_assign($assignid);
        $submission = self::resolve_submission($assignment, true);
        $config = self::get_plugin_config($assignment);
        $pending = self::manager()->get_file_by_token($uploadtoken);
        $pendingmeta = $pending ? json_decode((string)$pending->metadatajson, true) : null;
        if (!$pending || $pending->state !== 'pending' ||
                (int)$pending->submissionid !== (int)$submission->id ||
                (int)$pending->userid !== (int)$USER->id || $pending->blobpath !== $blobpath ||
                $pending->originalfilename !== $filename || (int)$pending->filesize !== $filesize ||
                $pending->mimetype !== $mimetype ||
                (int)($pendingmeta['expiresat'] ?? 0) < time() ||
                (int)$pending->timecreated + azure_blob_storage_service::MAX_UPLOAD_AGE_SECONDS < time()) {
            throw new \moodle_exception('invaliduploadtoken', 'assignsubmission_bloboffload');
        }
        $builder = new blob_path_builder();
        $expectedprefix = $builder->build_prefix($assignment, $submission, $USER->id);
        if (strpos($blobpath, $expectedprefix) !== 0) {
            throw new \moodle_exception(
                'error:fileoutsideprefix',
                'assignsubmission_bloboffload'
            );
        }

        if (count(self::manager()->get_submission_files((int)$submission->id)) >=
                (int)$config['maxfilesubmissions']) {
            throw new \moodle_exception('maxfilesreached', 'assignsubmission_bloboffload');
        }
        if ((int)$config['maxsubmissionsizebytes'] > 0 &&
                $filesize > (int)$config['maxsubmissionsizebytes']) {
            throw new \moodle_exception('maxbytesexceeded', 'assignsubmission_bloboffload');
        }
        if ($filesize > azure_blob_storage_service::MAX_FILE_BYTES) {
            throw new \moodle_exception('maxbytesexceeded', 'assignsubmission_bloboffload');
        }
        $util = new \core_form\filetypes_util();
        if (!$util->is_allowed_file_type($filename, (string)$config['filetypeslist'])) {
            throw new \moodle_exception('error:filetypenotallowed', 'assignsubmission_bloboffload');
        }

        $storage = new azure_blob_storage_service();
        $actualetag = $storage->verify_uploaded_blob($blobpath, $filesize);
        if ($etag !== '' && $etag !== $actualetag) {
            throw new \moodle_exception('error:blobverificationfailed', 'assignsubmission_bloboffload');
        }
        $etag = $actualetag;
        $file = self::manager()->finalize_upload($submission, $USER->id, [
            'uploadtoken' => $uploadtoken,
            'blobpath' => $blobpath,
            'bloburl' => $storage->get_blob_url($blobpath),
            'originalfilename' => $filename,
            'storedfilename' => basename($blobpath),
            'mimetype' => $mimetype,
            'filesize' => $filesize,
            'contenthash' => $contenthash,
            'etag' => $etag,
            'state' => 'uploaded',
            'metadatajson' => [
                'etag' => $etag,
                'contenthash' => $contenthash,
            ],
        ]);

        return self::export_files([$file])[0];
    }

    /**
     * Describe returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Metadata file id'),
            'filename' => new external_value(PARAM_RAW, 'Original filename'),
            'filesize' => new external_value(PARAM_RAW, 'Display file size'),
            'mimetype' => new external_value(PARAM_RAW, 'Mime type'),
            'state' => new external_value(PARAM_RAW, 'File state'),
            'downloadurl' => new external_value(PARAM_URL, 'Download URL'),
            'viewurl' => new external_value(PARAM_URL, 'View URL'),
        ]);
    }
}
