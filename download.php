<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Download entry point for blob offload submission files.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$fileid = required_param('fileid', PARAM_INT);
$preview = optional_param('preview', 0, PARAM_BOOL);

$manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
$resolver = new \assignsubmission_bloboffload\local\assignment_resolver();
$storage = new \assignsubmission_bloboffload\local\azure_blob_storage_service();

$file = $manager->get_active_file_by_id($fileid);
$submission = $DB->get_record(
    'assign_submission',
    ['id' => $file->submissionid],
    '*',
    MUST_EXIST
);
$assignment = $resolver->get_assign((int)$submission->assignment);

require_login($assignment->get_course(), false, $assignment->get_course_module());
$context = $assignment->get_context();

$cangrade = has_capability('mod/assign:viewgrades', $context);
$isowner = ((int)$file->userid === (int)$USER->id);
if (!$cangrade && !$isowner) {
    throw new moodle_exception(
        'error:forbiddendownload',
        'assignsubmission_bloboffload'
    );
}

$url = $storage->get_read_url(
    $file->blobpath,
    (int)get_config('assignsubmission_bloboffload', 'readsasexpiry')
);
redirect($url);
