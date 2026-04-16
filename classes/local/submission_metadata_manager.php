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

namespace assignsubmission_bloboffload\local;

/**
 * Handles plugin metadata persistence.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_metadata_manager {
    /** @var string[] */
    public const ACTIVE_STATES = ['uploaded', 'available'];

    /**
     * Get non-deleted files for a submission.
     *
     * @param int $submissionid
     * @return array
     */
    public function get_submission_files(int $submissionid): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(self::ACTIVE_STATES, SQL_PARAMS_NAMED);
        $params['submissionid'] = $submissionid;
        return $DB->get_records_select(
            'assignsubmission_bloboffload_file',
            "submissionid = :submissionid AND state $insql",
            $params,
            'timecreated ASC, id ASC'
        );
    }

    /**
     * Get all files for a submission.
     *
     * @param int $submissionid
     * @return array
     */
    public function get_all_submission_files(int $submissionid): array {
        global $DB;
        return $DB->get_records('assignsubmission_bloboffload_file', ['submissionid' => $submissionid], 'timecreated ASC, id ASC');
    }

    /**
     * Get file record by id.
     *
     * @param int $fileid
     * @return \stdClass
     */
    public function get_file_by_id(int $fileid): \stdClass {
        global $DB;
        return $DB->get_record('assignsubmission_bloboffload_file', ['id' => $fileid], '*', MUST_EXIST);
    }

    /**
     * Get an active file record by id.
     *
     * @param int $fileid
     * @return \stdClass
     */
    public function get_active_file_by_id(int $fileid): \stdClass {
        $file = $this->get_file_by_id($fileid);
        if (!in_array((string)$file->state, self::ACTIVE_STATES, true)) {
            throw new \moodle_exception('error:filenotfound', 'assignsubmission_bloboffload');
        }

        return $file;
    }

    /**
     * Get file record by upload token.
     *
     * @param string $uploadtoken
     * @return \stdClass|false
     */
    public function get_file_by_token(string $uploadtoken) {
        global $DB;
        return $DB->get_record('assignsubmission_bloboffload_file', ['uploadtoken' => $uploadtoken]);
    }

    /**
     * Upsert a file metadata record after upload.
     *
     * @param \stdClass $submission
     * @param int $userid
     * @param array $recorddata
     * @return \stdClass
     */
    public function finalize_upload(\stdClass $submission, int $userid, array $recorddata): \stdClass {
        global $DB;

        $now = time();
        $existing = $this->get_file_by_token($recorddata['uploadtoken']);
        if ($existing) {
            $existing->blobpath = $recorddata['blobpath'];
            $existing->bloburl = $recorddata['bloburl'];
            $existing->originalfilename = $recorddata['originalfilename'];
            $existing->storedfilename = $recorddata['storedfilename'];
            $existing->mimetype = $recorddata['mimetype'];
            $existing->filesize = $recorddata['filesize'];
            $existing->contenthash = $recorddata['contenthash'];
            $existing->etag = $recorddata['etag'];
            $existing->metadatajson = json_encode($recorddata['metadatajson']);
            $existing->state = $recorddata['state'];
            $existing->timemodified = $now;
            $DB->update_record('assignsubmission_bloboffload_file', $existing);
            $file = $existing;
        } else {
            $file = (object)[
                'submissionid' => $submission->id,
                'attemptnumber' => $submission->attemptnumber,
                'userid' => $userid,
                'uploadtoken' => $recorddata['uploadtoken'],
                'blobpath' => $recorddata['blobpath'],
                'bloburl' => $recorddata['bloburl'],
                'originalfilename' => $recorddata['originalfilename'],
                'storedfilename' => $recorddata['storedfilename'],
                'mimetype' => $recorddata['mimetype'],
                'filesize' => $recorddata['filesize'],
                'contenthash' => $recorddata['contenthash'],
                'etag' => $recorddata['etag'],
                'state' => $recorddata['state'],
                'metadatajson' => json_encode($recorddata['metadatajson']),
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $file->id = $DB->insert_record('assignsubmission_bloboffload_file', $file);
        }

        $this->refresh_submission_summary($submission->assignment, $submission->id);
        return $file;
    }

    /**
     * Save the set of active files for a submission.
     *
     * @param int $assignmentid
     * @param int $submissionid
     * @param array $fileids
     * @return void
     */
    public function sync_submission_files(int $assignmentid, int $submissionid, array $fileids): void {
        global $DB;

        $allfiles = $this->get_all_submission_files($submissionid);
        foreach ($allfiles as $file) {
            $file->state = in_array((int)$file->id, $fileids) ? 'available' : 'deleted';
            $file->timemodified = time();
            $DB->update_record('assignsubmission_bloboffload_file', $file);
        }

        $this->refresh_submission_summary($assignmentid, $submissionid);
    }

    /**
     * Mark a file as deleted.
     *
     * @param int $fileid
     * @return void
     */
    public function mark_deleted(int $fileid): void {
        global $DB;
        $file = $this->get_file_by_id($fileid);
        $file->state = 'deleted';
        $file->timemodified = time();
        $DB->update_record('assignsubmission_bloboffload_file', $file);
        $submission = $DB->get_record('assign_submission', ['id' => $file->submissionid], 'id, assignment', MUST_EXIST);
        $this->refresh_submission_summary((int)$submission->assignment, (int)$submission->id);
    }

    /**
     * Delete all plugin data for an assignment instance.
     *
     * @param int $assignmentid
     * @return void
     */
    public function delete_by_assignment(int $assignmentid): void {
        global $DB;
        $submissionids = $DB->get_fieldset_select('assignsubmission_bloboffload', 'submission', 'assignment = :assignment', [
            'assignment' => $assignmentid,
        ]);
        if (!empty($submissionids)) {
            [$insql, $params] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('assignsubmission_bloboffload_file', "submissionid $insql", $params);
        }
        $DB->delete_records('assignsubmission_bloboffload', ['assignment' => $assignmentid]);
    }

    /**
     * Delete plugin data for a single submission.
     *
     * @param int $assignmentid
     * @param int $submissionid
     * @return void
     */
    public function delete_by_submission(int $assignmentid, int $submissionid): void {
        global $DB;
        $DB->delete_records('assignsubmission_bloboffload_file', ['submissionid' => $submissionid]);
        $DB->delete_records('assignsubmission_bloboffload', ['assignment' => $assignmentid, 'submission' => $submissionid]);
    }

    /**
     * Refresh the summary row for a submission.
     *
     * @param int $assignmentid
     * @param int $submissionid
     * @return void
     */
    public function refresh_submission_summary(int $assignmentid, int $submissionid): void {
        global $DB;

        $files = $this->get_submission_files($submissionid);
        $summary = $DB->get_record('assignsubmission_bloboffload', ['submission' => $submissionid]);
        $now = time();
        $lastuploadtime = 0;
        foreach ($files as $file) {
            $lastuploadtime = max($lastuploadtime, (int)$file->timemodified);
        }

        if ($summary) {
            $summary->filecount = count($files);
            $summary->lastuploadtime = $lastuploadtime;
            $summary->timemodified = $now;
            $DB->update_record('assignsubmission_bloboffload', $summary);
        } else {
            $summary = (object)[
                'assignment' => $assignmentid,
                'submission' => $submissionid,
                'filecount' => count($files),
                'lastuploadtime' => $lastuploadtime,
                'timemodified' => $now,
            ];
            $DB->insert_record('assignsubmission_bloboffload', $summary);
        }
    }
}
