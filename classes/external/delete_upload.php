<?php
// This file is part of Moodle - http://moodle.org/

namespace assignsubmission_bloboffload\external;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_bloboffload\local\azure_blob_storage_service;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Mark an uploaded file as deleted.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_upload extends external_base {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignid' => new external_value(PARAM_INT, 'Assignment instance id'),
            'fileid' => new external_value(PARAM_INT, 'Metadata file id'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $assignid
     * @param int $fileid
     * @return array
     */
    public static function execute(int $assignid, int $fileid): array {
        global $USER;

        [
            'assignid' => $assignid,
            'fileid' => $fileid,
        ] = self::validate_parameters(
            self::execute_parameters(),
            [
                'assignid' => $assignid,
                'fileid' => $fileid,
            ]
        );

        $assignment = self::resolve_assign($assignid);
        $submission = self::resolve_submission($assignment, true);

        $file = self::manager()->get_file_by_id($fileid);
        if ((int)$file->userid !== (int)$USER->id
                || (int)$file->submissionid !== (int)$submission->id) {
            throw new \moodle_exception(
                'error:filenotfound',
                'assignsubmission_bloboffload'
            );
        }

        $storage = new azure_blob_storage_service();
        $storage->delete_blob((string)$file->blobpath);
        self::manager()->mark_deleted($fileid);
        return ['status' => true];
    }

    /**
     * Describe returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(
                PARAM_BOOL,
                'Whether the file was deleted'
            ),
        ]);
    }
}
