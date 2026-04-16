<?php
// This file is part of Moodle - http://moodle.org/

namespace assignsubmission_bloboffload\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_bloboffload\local\assignment_resolver;
use assignsubmission_bloboffload\local\submission_metadata_manager;
use core_external\external_api;

/**
 * Shared helper methods for blob offload external functions.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class external_base extends external_api {
    /**
     * Resolve and validate assignment access.
     *
     * @param int $assignid
     * @return \assign
     */
    protected static function resolve_assign(int $assignid): \assign {
        $resolver = new assignment_resolver();
        $assignment = $resolver->get_assign($assignid);
        self::validate_context($assignment->get_context());
        require_login($assignment->get_course(), false, $assignment->get_course_module());
        return $assignment;
    }

    /**
     * Resolve the current submission for the user.
     *
     * @param \assign $assignment
     * @param bool $create
     * @return \stdClass|false
     */
    protected static function resolve_submission(\assign $assignment, bool $create) {
        global $USER;
        $resolver = new assignment_resolver();
        if ($create) {
            $resolver->require_editable_submission($assignment, $USER->id);
        }
        return $resolver->get_submission($assignment, $USER->id, $create);
    }

    /**
     * Get plugin config as array.
     *
     * @param \assign $assignment
     * @return array
     */
    protected static function get_plugin_config(\assign $assignment): array {
        $plugin = $assignment->get_plugin_by_type('assignsubmission', 'bloboffload');
        $config = (array)$plugin->get_config();
        $config['maxfilesubmissions'] = (int)($config['maxfilesubmissions']
            ?? get_config('assignsubmission_bloboffload', 'maxfiles'));
        $config['maxsubmissionsizebytes'] = (int)($config['maxsubmissionsizebytes']
            ?? get_config('assignsubmission_bloboffload', 'maxbytes'));
        $config['filetypeslist'] = (string)($config['filetypeslist'] ?? '');
        return $config;
    }

    /**
     * Export files for external consumers.
     *
     * @param array $files
     * @return array
     */
    protected static function export_files(array $files): array {
        $exported = [];
        foreach ($files as $file) {
            $exported[] = [
                'id' => (int)$file->id,
                'filename' => $file->originalfilename,
                'filesize' => display_size((int)$file->filesize),
                'mimetype' => (string)$file->mimetype,
                'state' => (string)$file->state,
                'downloadurl' => (new \moodle_url(
                    '/mod/assign/submission/bloboffload/download.php',
                    ['fileid' => $file->id]
                ))->out(false),
                'viewurl' => (new \moodle_url(
                    '/mod/assign/submission/bloboffload/download.php',
                    ['fileid' => $file->id, 'preview' => 1]
                ))->out(false),
            ];
        }
        return $exported;
    }

    /**
     * Get file manager instance.
     *
     * @return submission_metadata_manager
     */
    protected static function manager(): submission_metadata_manager {
        return new submission_metadata_manager();
    }
}
