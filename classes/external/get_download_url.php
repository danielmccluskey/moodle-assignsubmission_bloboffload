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
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Return a short-lived read URL for a blob-backed file.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_download_url extends external_base {
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
        $file = self::manager()->get_active_file_by_id($fileid);
        $submission = self::resolve_submission($assignment, false);

        $cangrade = has_capability(
            'mod/assign:viewgrades',
            $assignment->get_context()
        );
        $isowner = $submission
            && (int)$submission->id === (int)$file->submissionid
            && (int)$file->userid === (int)$USER->id;
        if (!$cangrade && !$isowner) {
            throw new \moodle_exception(
                'error:forbiddendownload',
                'assignsubmission_bloboffload'
            );
        }

        $storage = new azure_blob_storage_service();
        return [
            'url' => $storage->get_read_url(
                $file->blobpath,
                (int)get_config('assignsubmission_bloboffload', 'readsasexpiry')
            ),
        ];
    }

    /**
     * Describe returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_URL, 'Temporary download URL'),
        ]);
    }
}
