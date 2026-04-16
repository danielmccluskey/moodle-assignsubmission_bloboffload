<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

/**
 * Library class for the blob offload submission plugin.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_bloboffload extends assign_submission_plugin {
    /** @var int */
    public const MAXSUMMARYFILES = 5;

    /**
     * Get the plugin display name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignsubmission_bloboffload');
    }

    /**
     * Get assignment settings form elements.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;

        if ($this->assignment->has_instance()) {
            $defaultmaxfiles = $this->get_config('maxfilesubmissions');
            $defaultmaxbytes = $this->get_config('maxsubmissionsizebytes');
            $defaultfiletypes = (string)$this->get_config('filetypeslist');
        } else {
            $defaultmaxfiles = (int)get_config(
                'assignsubmission_bloboffload',
                'maxfiles'
            );
            $defaultmaxbytes = (int)get_config(
                'assignsubmission_bloboffload',
                'maxbytes'
            );
            $defaultfiletypes = '';
        }

        $options = [];
        $upperbound = max(
            1,
            (int)get_config('assignsubmission_bloboffload', 'maxfiles')
        );
        for ($i = 1; $i <= $upperbound; $i++) {
            $options[$i] = $i;
        }

        $mform->addElement(
            'select',
            'assignsubmission_bloboffload_maxfiles',
            get_string('maxfilessubmission', 'assignsubmission_bloboffload'),
            $options
        );
        $mform->setDefault(
            'assignsubmission_bloboffload_maxfiles',
            max(1, $defaultmaxfiles)
        );
        $mform->hideIf(
            'assignsubmission_bloboffload_maxfiles',
            'assignsubmission_bloboffload_enabled',
            'notchecked'
        );

        $choices = get_max_upload_sizes(
            $CFG->maxbytes,
            $COURSE->maxbytes,
            get_config('assignsubmission_bloboffload', 'maxbytes')
        );
        $mform->addElement(
            'select',
            'assignsubmission_bloboffload_maxsizebytes',
            get_string(
                'maximumsubmissionsize',
                'assignsubmission_bloboffload'
            ),
            $choices
        );
        $mform->setDefault(
            'assignsubmission_bloboffload_maxsizebytes',
            $defaultmaxbytes
        );
        $mform->hideIf(
            'assignsubmission_bloboffload_maxsizebytes',
            'assignsubmission_bloboffload_enabled',
            'notchecked'
        );

        $mform->addElement(
            'filetypes',
            'assignsubmission_bloboffload_filetypes',
            get_string('acceptedfiletypes', 'assignsubmission_bloboffload')
        );
        $mform->setDefault(
            'assignsubmission_bloboffload_filetypes',
            $defaultfiletypes
        );
        $mform->hideIf(
            'assignsubmission_bloboffload_filetypes',
            'assignsubmission_bloboffload_enabled',
            'notchecked'
        );
    }

    /**
     * Save plugin settings.
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $this->set_config(
            'maxfilesubmissions',
            max(1, (int)$data->assignsubmission_bloboffload_maxfiles)
        );
        $this->set_config(
            'maxsubmissionsizebytes',
            (int)$data->assignsubmission_bloboffload_maxsizebytes
        );
        $this->set_config(
            'filetypeslist',
            (string)($data->assignsubmission_bloboffload_filetypes ?? '')
        );
        return true;
    }

    /**
     * Add submission form elements.
     *
     * @param stdClass|false|null $submission
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements(
        $submission,
        MoodleQuickForm $mform,
        stdClass $data
    ) {
        global $PAGE, $OUTPUT, $USER;

        $storage = new \assignsubmission_bloboffload\local\azure_blob_storage_service();
        if (!$storage->is_configured()) {
            $mform->addElement(
                'static',
                'assignsubmission_bloboffload_notice',
                '',
                get_string('noconfiguration', 'assignsubmission_bloboffload')
            );
            return true;
        }

        $submissionid = $submission ? (int)$submission->id : 0;
        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        $existingfiles = $submissionid
            ? array_values(array_map(
                [$this, 'export_file_for_template'],
                $manager->get_submission_files($submissionid)
            ))
            : [];

        $payload = ['fileids' => array_map(fn($file) => (int)$file['id'], $existingfiles)];
        $mform->addElement(
            'hidden',
            'assignsubmission_bloboffload_payload',
            json_encode($payload)
        );
        $mform->setType('assignsubmission_bloboffload_payload', PARAM_RAW);
        $PAGE->requires->css(
            new moodle_url('/mod/assign/submission/bloboffload/styles.css')
        );

        $resolver = new \assignsubmission_bloboffload\local\assignment_resolver();
        if (!$resolver->can_edit_submission($this->assignment, (int)$USER->id)) {
            $mform->addElement(
                'html',
                $OUTPUT->render_from_template(
                    'assignsubmission_bloboffload/file_list',
                    [
                        'files' => $existingfiles,
                        'hasfiles' => !empty($existingfiles),
                        'nofileslabel' => get_string(
                            'nofiles',
                            'assignsubmission_bloboffload'
                        ),
                    ]
                )
            );
            return true;
        }

        $context = [
            'elementid' => html_writer::random_id('bloboffload'),
            'strings' => [
                'acceptedtypes' => get_string(
                    'acceptedfiletypes',
                    'assignsubmission_bloboffload'
                ),
                'cancel' => get_string('cancel', 'core'),
                'currentfiles' => get_string(
                    'currentfiles',
                    'assignsubmission_bloboffload'
                ),
                'delete' => get_string('delete', 'assignsubmission_bloboffload'),
                'deleteconfirm' => get_string(
                    'deleteconfirm',
                    'assignsubmission_bloboffload'
                ),
                'download' => get_string('download', 'assignsubmission_bloboffload'),
                'loading' => get_string('loading', 'assignsubmission_bloboffload'),
                'maxbytesexceeded' => get_string(
                    'maxbytesexceeded',
                    'assignsubmission_bloboffload'
                ),
                'maxfilesreached' => get_string(
                    'maxfilesreached',
                    'assignsubmission_bloboffload'
                ),
                'maxsize' => get_string(
                    'maximumsubmissionsize',
                    'assignsubmission_bloboffload'
                ),
                'nofiles' => get_string('nofiles', 'assignsubmission_bloboffload'),
                'uploadfailed' => get_string(
                    'uploadfailed',
                    'assignsubmission_bloboffload'
                ),
                'uploadfiles' => get_string(
                    'uploadfiles',
                    'assignsubmission_bloboffload'
                ),
                'uploading' => get_string(
                    'uploadinprogress',
                    'assignsubmission_bloboffload'
                ),
                'view' => get_string('view', 'assignsubmission_bloboffload'),
            ],
        ];
        $context['reactconfigjson'] = json_encode([
            'component' => '@moodle/lms/assignsubmission_bloboffload/uploader',
            'props' => [
                'assignId' => $this->assignment->get_instance()->id,
                'inputName' => 'assignsubmission_bloboffload_payload',
                'strings' => $context['strings'],
            ],
            'id' => $context['elementid'],
            'class' => 'assignsubmission-bloboffload',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $mform->addElement(
            'html',
            $OUTPUT->render_from_template(
                'assignsubmission_bloboffload/uploader',
                $context
            )
        );

        return true;
    }

    /**
     * Save plugin data.
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        $payload = $this->decode_payload($data);
        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        $allowedfileids = [];

        foreach ($payload['fileids'] as $fileid) {
            $file = $manager->get_file_by_id((int)$fileid);
            if ((int)$file->submissionid !== (int)$submission->id) {
                throw new moodle_exception('invalidpayload', 'assignsubmission_bloboffload');
            }
            $allowedfileids[] = (int)$file->id;
        }

        $manager->sync_submission_files(
            $this->assignment->get_instance()->id,
            $submission->id,
            $allowedfileids
        );
        return true;
    }

    /**
     * Remove plugin data from a submission.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function remove(stdClass $submission) {
        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        $manager->delete_by_submission(
            $this->assignment->get_instance()->id,
            $submission->id
        );
        return true;
    }

    /**
     * Delete instance data.
     *
     * @return bool
     */
    public function delete_instance() {
        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        $manager->delete_by_assignment($this->assignment->get_instance()->id);
        return true;
    }

    /**
     * Whether the submission is empty.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        return count($manager->get_submission_files($submission->id)) === 0;
    }

    /**
     * Whether the incoming submission payload is empty.
     *
     * @param stdClass $data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        $payload = $this->decode_payload($data);
        return empty($payload['fileids']);
    }

    /**
     * Copy blob metadata to a new attempt.
     *
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     * @return bool
     */
    public function copy_submission(
        stdClass $sourcesubmission,
        stdClass $destsubmission
    ) {
        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        $files = $manager->get_submission_files($sourcesubmission->id);
        foreach ($files as $file) {
            $manager->finalize_upload($destsubmission, (int)$file->userid, [
                'uploadtoken' => bin2hex(random_bytes(16)),
                'blobpath' => $file->blobpath,
                'bloburl' => $file->bloburl,
                'originalfilename' => $file->originalfilename,
                'storedfilename' => $file->storedfilename,
                'mimetype' => $file->mimetype,
                'filesize' => (int)$file->filesize,
                'contenthash' => $file->contenthash,
                'etag' => $file->etag,
                'state' => 'available',
                'metadatajson' => json_decode(
                    (string)$file->metadatajson,
                    true
                ) ?: [],
            ]);
        }

        return true;
    }

    /**
     * View summary for status table.
     *
     * @param stdClass $submission
     * @param bool $showviewlink
     * @return string
     */
    public function view_summary(stdClass $submission, & $showviewlink) {
        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        $files = array_values($manager->get_submission_files($submission->id));
        $count = count($files);
        $showviewlink = $count > self::MAXSUMMARYFILES;

        if ($count === 0) {
            return '';
        }

        if ($count > self::MAXSUMMARYFILES) {
            return get_string('countfiles', 'assignsubmission_bloboffload', $count);
        }

        $items = array_map(function($file) {
            $url = new moodle_url(
                '/mod/assign/submission/bloboffload/download.php',
                ['fileid' => $file->id]
            );
            return html_writer::link($url, s($file->originalfilename));
        }, $files);

        return html_writer::alist($items);
    }

    /**
     * Full submission view.
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        global $OUTPUT;

        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        $files = array_values(array_map(
            [$this, 'export_file_for_template'],
            $manager->get_submission_files($submission->id)
        ));
        return $OUTPUT->render_from_template(
            'assignsubmission_bloboffload/file_list',
            [
                'files' => $files,
                'hasfiles' => !empty($files),
                'nofileslabel' => get_string(
                    'nofiles',
                    'assignsubmission_bloboffload'
                ),
            ]
        );
    }

    /**
     * Message summary.
     *
     * @param stdClass $submission
     * @return array
     */
    public function submission_summary_for_messages(stdClass $submission): array {
        $manager = new \assignsubmission_bloboffload\local\submission_metadata_manager();
        $files = $manager->get_submission_files($submission->id);
        if (!$files) {
            return ['', ''];
        }

        $lines = [];
        foreach ($files as $file) {
            $lines[] = '- ' . $file->originalfilename
                . ' (' . display_size($file->filesize) . ')';
        }
        $plaintext = implode("\n", $lines) . "\n";
        $html = html_writer::alist(array_map(
            's',
            array_map(
                fn($file) => $file->originalfilename
                    . ' (' . display_size($file->filesize) . ')',
                $files
            )
        ));
        return [$plaintext, $html];
    }

    /**
     * Return external parameters.
     *
     * @return array
     */
    public function get_external_parameters() {
        return [
            'assignsubmission_bloboffload_payload' => new external_value(
                PARAM_RAW,
                'JSON payload containing the blob offload file ids.',
                VALUE_OPTIONAL
            ),
        ];
    }

    /**
     * Export a file for UI templates.
     *
     * @param stdClass $file
     * @return array
     */
    private function export_file_for_template(stdClass $file): array {
        $downloadurl = new moodle_url(
            '/mod/assign/submission/bloboffload/download.php',
            ['fileid' => $file->id]
        );
        $viewurl = new moodle_url(
            '/mod/assign/submission/bloboffload/download.php',
            ['fileid' => $file->id, 'preview' => 1]
        );
        return [
            'id' => (int)$file->id,
            'filename' => s($file->originalfilename),
            'filesize' => display_size((int)$file->filesize),
            'mimetype' => s((string)$file->mimetype),
            'state' => s($file->state),
            'downloadurl' => $downloadurl->out(false),
            'viewurl' => $viewurl->out(false),
        ];
    }

    /**
     * Decode hidden payload value.
     *
     * @param stdClass $data
     * @return array
     */
    private function decode_payload(stdClass $data): array {
        $raw = (string)($data->assignsubmission_bloboffload_payload ?? '');
        if ($raw === '') {
            return ['fileids' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new moodle_exception('invalidpayload', 'assignsubmission_bloboffload');
        }

        $fileids = array_map('intval', $decoded['fileids'] ?? []);
        $fileids = array_values(array_unique(array_filter(
            $fileids,
            fn($id) => $id > 0
        )));
        return ['fileids' => $fileids];
    }

}
