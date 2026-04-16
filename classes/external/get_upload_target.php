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

defined('MOODLE_INTERNAL') || die();

use assignsubmission_bloboffload\local\azure_blob_storage_service;
use assignsubmission_bloboffload\local\blob_path_builder;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Generate a short-lived Azure upload target.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_upload_target extends external_base {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignid' => new external_value(PARAM_INT, 'Assignment instance id'),
            'filename' => new external_value(PARAM_FILE, 'Original file name'),
            'filesize' => new external_value(PARAM_INT, 'File size'),
            'mimetype' => new external_value(PARAM_RAW, 'Mime type'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $assignid
     * @param string $filename
     * @param int $filesize
     * @param string $mimetype
     * @return array
     */
    public static function execute(
        int $assignid,
        string $filename,
        int $filesize,
        string $mimetype
    ): array {
        global $USER;

        [
            'assignid' => $assignid,
            'filename' => $filename,
            'filesize' => $filesize,
            'mimetype' => $mimetype,
        ] = self::validate_parameters(self::execute_parameters(), [
            'assignid' => $assignid,
            'filename' => $filename,
            'filesize' => $filesize,
            'mimetype' => $mimetype,
        ]);

        $assignment = self::resolve_assign($assignid);
        $submission = self::resolve_submission($assignment, true);
        $config = self::get_plugin_config($assignment);
        $manager = self::manager();
        $util = new \core_form\filetypes_util();
        if (
            count($manager->get_submission_files((int)$submission->id))
                >= (int)$config['maxfilesubmissions']
        ) {
            throw new \moodle_exception(
                'maxfilesreached',
                'assignsubmission_bloboffload'
            );
        }
        if (
            (int)$config['maxsubmissionsizebytes'] > 0
                && $filesize > (int)$config['maxsubmissionsizebytes']
        ) {
            throw new \moodle_exception(
                'maxbytesexceeded',
                'assignsubmission_bloboffload'
            );
        }
        if (
            !$util->is_allowed_file_type(
                $filename,
                (string)$config['filetypeslist']
            )
        ) {
            throw new \moodle_exception(
                'error:filetypenotallowed',
                'assignsubmission_bloboffload'
            );
        }

        $builder = new blob_path_builder();
        $uploadtoken = bin2hex(random_bytes(32));
        $blobpath = $builder->build(
            $assignment,
            $submission,
            $USER->id,
            $filename,
            bin2hex(random_bytes(8))
        );
        $storage = new azure_blob_storage_service();
        $target = $storage->get_upload_target(
            $blobpath,
            (int)get_config('assignsubmission_bloboffload', 'uploadsasexpiry')
        );

        return [
            'submissionid' => (int)$submission->id,
            'uploadtoken' => $uploadtoken,
            'blobpath' => $blobpath,
            'bloburl' => $target['bloburl'],
            'uploadurl' => $target['uploadurl'],
            'expiresat' => (int)$target['expiresat'],
        ];
    }

    /**
     * Describe returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'submissionid' => new external_value(PARAM_INT, 'Submission id'),
            'uploadtoken' => new external_value(PARAM_ALPHANUMEXT, 'Upload token'),
            'blobpath' => new external_value(PARAM_RAW, 'Blob path'),
            'bloburl' => new external_value(PARAM_URL, 'Base blob url'),
            'uploadurl' => new external_value(PARAM_URL, 'SAS upload url'),
            'expiresat' => new external_value(PARAM_INT, 'Expiry timestamp'),
        ]);
    }
}
