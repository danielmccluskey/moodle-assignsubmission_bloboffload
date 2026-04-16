<?php
// This file is part of Moodle - http://moodle.org/

namespace assignsubmission_bloboffload\privacy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assignsubmission_bloboffload\local\submission_metadata_manager;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;

/**
 * Privacy provider for blob offload submissions.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \mod_assign\privacy\assignsubmission_provider,
        \mod_assign\privacy\assignsubmission_user_provider {

    /**
     * Describe metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('assignsubmission_bloboffload', [
            'assignment' => 'privacy:metadata:assignsubmission_bloboffload',
            'submission' => 'privacy:metadata:assignsubmission_bloboffload',
        ], 'privacy:metadata:assignsubmission_bloboffload');

        $collection->add_database_table('assignsubmission_bloboffload_file', [
            'originalfilename' => 'privacy:metadata:assignsubmission_bloboffload_file:originalfilename',
            'storedfilename' => 'privacy:metadata:assignsubmission_bloboffload_file:storedfilename',
            'blobpath' => 'privacy:metadata:assignsubmission_bloboffload_file:blobpath',
            'bloburl' => 'privacy:metadata:assignsubmission_bloboffload_file:bloburl',
            'mimetype' => 'privacy:metadata:assignsubmission_bloboffload_file:mimetype',
            'filesize' => 'privacy:metadata:assignsubmission_bloboffload_file:filesize',
            'etag' => 'privacy:metadata:assignsubmission_bloboffload_file:etag',
            'state' => 'privacy:metadata:assignsubmission_bloboffload_file:state',
            'userid' => 'privacy:metadata:assignsubmission_bloboffload_file:userid',
            'submissionid' => 'privacy:metadata:assignsubmission_bloboffload_file:submissionid',
        ], 'privacy:metadata:assignsubmission_bloboffload_file');

        return $collection;
    }

    /**
     * Already covered by mod_assign.
     *
     * @param int $userid
     * @param contextlist $contextlist
     * @return void
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
    }

    /**
     * Already covered by mod_assign.
     *
     * @param \mod_assign\privacy\useridlist $useridlist
     * @return void
     */
    public static function get_student_user_ids(\mod_assign\privacy\useridlist $useridlist) {
    }

    /**
     * Not required.
     *
     * @param \core_privacy\local\request\userlist $userlist
     * @return void
     */
    public static function get_userids_from_context(\core_privacy\local\request\userlist $userlist) {
    }

    /**
     * Export metadata for a submission.
     *
     * @param assign_plugin_request_data $exportdata
     * @return void
     */
    public static function export_submission_user_data(assign_plugin_request_data $exportdata) {
        if ($exportdata->get_user() !== null) {
            return;
        }

        $manager = new submission_metadata_manager();
        $files = $manager->get_submission_files($exportdata->get_pluginobject()->id);
        $export = [];
        foreach ($files as $file) {
            $export[] = [
                'filename' => $file->originalfilename,
                'blobpath' => $file->blobpath,
                'mimetype' => $file->mimetype,
                'filesize' => $file->filesize,
                'state' => $file->state,
            ];
        }

        writer::with_context($exportdata->get_context())->export_data($exportdata->get_subcontext(), (object)[
            'files' => $export,
        ]);
    }

    /**
     * Delete all plugin data for a context.
     *
     * @param assign_plugin_request_data $requestdata
     * @return void
     */
    public static function delete_submission_for_context(assign_plugin_request_data $requestdata) {
        $manager = new submission_metadata_manager();
        $manager->delete_by_assignment($requestdata->get_assign()->get_instance()->id);
    }

    /**
     * Delete a user's plugin data.
     *
     * @param assign_plugin_request_data $deletedata
     * @return void
     */
    public static function delete_submission_for_userid(assign_plugin_request_data $deletedata) {
        $manager = new submission_metadata_manager();
        $manager->delete_by_submission($deletedata->get_assignid(), $deletedata->get_pluginobject()->id);
    }

    /**
     * Delete a list of submissions.
     *
     * @param assign_plugin_request_data $deletedata
     * @return void
     */
    public static function delete_submissions(assign_plugin_request_data $deletedata) {
        $manager = new submission_metadata_manager();
        foreach ($deletedata->get_submissionids() as $submissionid) {
            $manager->delete_by_submission($deletedata->get_assignid(), $submissionid);
        }
    }
}
