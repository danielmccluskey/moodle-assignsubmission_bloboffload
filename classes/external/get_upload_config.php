<?php
// This file is part of Moodle - http://moodle.org/

namespace assignsubmission_bloboffload\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Return upload configuration for the current submission.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_upload_config extends external_base {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignid' => new external_value(PARAM_INT, 'Assignment instance id'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $assignid
     * @return array
     */
    public static function execute(int $assignid): array {
        ['assignid' => $assignid] = self::validate_parameters(
            self::execute_parameters(),
            ['assignid' => $assignid]
        );

        $assignment = self::resolve_assign($assignid);
        $submission = self::resolve_submission($assignment, false);
        $config = self::get_plugin_config($assignment);
        $files = $submission
            ? self::manager()->get_submission_files((int)$submission->id)
            : [];

        return [
            'submissionid' => $submission ? (int)$submission->id : 0,
            'maxfiles' => (int)$config['maxfilesubmissions'],
            'maxbytes' => (int)$config['maxsubmissionsizebytes'],
            'maxbyteslabel' => (int)$config['maxsubmissionsizebytes'] > 0
                ? display_size((int)$config['maxsubmissionsizebytes'])
                : '',
            'filetypeslist' => (string)$config['filetypeslist'],
            'acceptedtypeslabel' => self::get_accepted_types_label(
                (string)$config['filetypeslist']
            ),
            'acceptattr' => self::get_accept_attribute((string)$config['filetypeslist']),
            'files' => self::export_files($files),
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
            'maxfiles' => new external_value(PARAM_INT, 'Maximum file count'),
            'maxbytes' => new external_value(PARAM_INT, 'Maximum bytes'),
            'maxbyteslabel' => new external_value(
                PARAM_RAW,
                'Display maximum bytes label'
            ),
            'filetypeslist' => new external_value(
                PARAM_RAW,
                'Configured file types list'
            ),
            'acceptedtypeslabel' => new external_value(
                PARAM_RAW,
                'Human-readable accepted file types label'
            ),
            'acceptattr' => new external_value(
                PARAM_RAW,
                'HTML accept attribute value'
            ),
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Metadata file id'),
                    'filename' => new external_value(
                        PARAM_RAW,
                        'Original filename'
                    ),
                    'filesize' => new external_value(
                        PARAM_RAW,
                        'Display file size'
                    ),
                    'mimetype' => new external_value(PARAM_RAW, 'Mime type'),
                    'state' => new external_value(PARAM_RAW, 'File state'),
                    'downloadurl' => new external_value(
                        PARAM_URL,
                        'Download URL'
                    ),
                    'viewurl' => new external_value(PARAM_URL, 'View URL'),
                ])
            ),
        ]);
    }

    /**
     * Build a human-readable accepted file types label.
     *
     * @param string $filetypeslist
     * @return string
     */
    private static function get_accepted_types_label(
        string $filetypeslist
    ): string {
        if ($filetypeslist === '') {
            return '';
        }

        $util = new \core_form\filetypes_util();
        return implode(', ', $util->normalize_file_types($filetypeslist));
    }

    /**
     * Generate an HTML accept attribute for the configured file types.
     *
     * @param string $filetypeslist
     * @return string
     */
    private static function get_accept_attribute(string $filetypeslist): string {
        if ($filetypeslist === '') {
            return '';
        }

        $util = new \core_form\filetypes_util();
        $description = $util->describe_file_types($filetypeslist);
        $extensions = preg_split(
            '/\s+/',
            trim((string)$description->allowedextensions),
            -1,
            PREG_SPLIT_NO_EMPTY
        );
        return implode(',', $extensions ?: []);
    }
}
