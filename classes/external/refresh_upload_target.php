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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace assignsubmission_bloboffload\external;

use assignsubmission_bloboffload\local\azure_blob_storage_service;
use assignsubmission_bloboffload\local\blob_path_builder;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Renew the short-lived SAS for a pending upload.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_upload_target extends external_base {
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignid' => new external_value(PARAM_INT, 'Assignment instance id'),
            'uploadtoken' => new external_value(PARAM_ALPHANUMEXT, 'Pending upload token'),
        ]);
    }

    /**
     * @param int $assignid
     * @param string $uploadtoken
     * @return array
     */
    public static function execute(int $assignid, string $uploadtoken): array {
        global $USER;

        ['assignid' => $assignid, 'uploadtoken' => $uploadtoken] =
            self::validate_parameters(self::execute_parameters(), [
                'assignid' => $assignid,
                'uploadtoken' => $uploadtoken,
            ]);

        $assignment = self::resolve_assign($assignid);
        $submission = self::resolve_submission($assignment, true);
        $config = self::get_plugin_config($assignment);
        $pending = self::manager()->get_file_by_token($uploadtoken);
        if (!$pending || $pending->state !== 'pending' ||
                (int)$pending->submissionid !== (int)$submission->id ||
                (int)$pending->userid !== (int)$USER->id ||
                (int)$pending->timecreated + azure_blob_storage_service::MAX_UPLOAD_AGE_SECONDS < time() ||
                (int)$pending->filesize > azure_blob_storage_service::MAX_FILE_BYTES ||
                ((int)$config['maxsubmissionsizebytes'] > 0 &&
                    (int)$pending->filesize > (int)$config['maxsubmissionsizebytes'])) {
            throw new \moodle_exception('invaliduploadtoken', 'assignsubmission_bloboffload');
        }

        $builder = new blob_path_builder();
        $prefix = $builder->build_prefix($assignment, $submission, (int)$USER->id);
        if (strpos((string)$pending->blobpath, $prefix) !== 0) {
            throw new \moodle_exception('invaliduploadtoken', 'assignsubmission_bloboffload');
        }

        $storage = new azure_blob_storage_service();
        $remaining = (int)$pending->timecreated +
            azure_blob_storage_service::MAX_UPLOAD_AGE_SECONDS - time();
        if ($remaining < 60) {
            throw new \moodle_exception('invaliduploadtoken', 'assignsubmission_bloboffload');
        }
        $expiry = min(
            max(60, (int)get_config('assignsubmission_bloboffload', 'uploadsasexpiry')),
            $remaining
        );
        $target = $storage->get_upload_target((string)$pending->blobpath, $expiry);
        self::manager()->extend_pending_upload((int)$pending->id, (int)$target['expiresat']);

        return [
            'uploadurl' => $target['uploadurl'],
            'expiresat' => (int)$target['expiresat'],
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'uploadurl' => new external_value(PARAM_URL, 'Renewed SAS upload URL'),
            'expiresat' => new external_value(PARAM_INT, 'Expiry timestamp'),
        ]);
    }
}
