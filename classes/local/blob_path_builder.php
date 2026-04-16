<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace assignsubmission_bloboffload\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds stable blob paths for submission uploads.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blob_path_builder {
    /**
     * Build a blob path for a submission upload.
     *
     * @param \assign $assignment
     * @param \stdClass $submission
     * @param int $userid
     * @param string $originalfilename
     * @param string $uuid
     * @return string
     */
    public function build(
        \assign $assignment,
        \stdClass $submission,
        int $userid,
        string $originalfilename,
        string $uuid
    ): string {
        $filename = $this->sanitize_filename($originalfilename);

        return implode('/', [
            'course-' . $assignment->get_course()->id,
            'cm-' . $assignment->get_course_module()->id,
            'assign-' . $assignment->get_instance()->id,
            'submission-' . $submission->id,
            'attempt-' . (int)$submission->attemptnumber,
            'user-' . $userid,
            $uuid . '__' . $filename,
        ]);
    }

    /**
     * Build the expected path prefix for a submission.
     *
     * @param \assign $assignment
     * @param \stdClass $submission
     * @param int $userid
     * @return string
     */
    public function build_prefix(
        \assign $assignment,
        \stdClass $submission,
        int $userid
    ): string {
        return implode('/', [
            'course-' . $assignment->get_course()->id,
            'cm-' . $assignment->get_course_module()->id,
            'assign-' . $assignment->get_instance()->id,
            'submission-' . $submission->id,
            'attempt-' . (int)$submission->attemptnumber,
            'user-' . $userid,
        ]) . '/';
    }

    /**
     * Sanitize filename for blob storage.
     *
     * @param string $filename
     * @return string
     */
    public function sanitize_filename(string $filename): string {
        $filename = clean_param($filename, PARAM_FILE);
        $filename = trim($filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new \moodle_exception(
                'error:invalidfilename',
                'assignsubmission_bloboffload'
            );
        }

        return $filename;
    }
}
