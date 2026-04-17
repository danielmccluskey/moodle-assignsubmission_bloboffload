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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Shared helpers to resolve assign instances and submissions.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_resolver {
    /**
     * Resolve an assign object from assign id.
     *
     * @param int $assignid
     * @return \assign
     */
    public function get_assign(int $assignid): \assign {
        $cm = get_coursemodule_from_instance(
            'assign',
            $assignid,
            0,
            false,
            MUST_EXIST
        );
        $context = \context_module::instance($cm->id);
        return new \assign($context, $cm, get_course($cm->course));
    }

    /**
     * Resolve current user or group submission.
     *
     * @param \assign $assignment
     * @param int $userid
     * @param bool $create
     * @return \stdClass|false
     */
    public function get_submission(\assign $assignment, int $userid, bool $create) {
        if ($assignment->get_instance()->teamsubmission) {
            return $assignment->get_group_submission($userid, 0, $create);
        }

        return $assignment->get_user_submission($userid, $create);
    }

    /**
     * Ensure the current user can edit this assignment submission.
     *
     * @param \assign $assignment
     * @param int $userid
     * @return void
     */
    public function require_editable_submission(\assign $assignment, int $userid): void {
        require_capability('mod/assign:submit', $assignment->get_context());
        if (!$this->can_edit_submission($assignment, $userid)) {
            throw new \moodle_exception(
                'error:submissionnoteditable',
                'assignsubmission_bloboffload'
            );
        }
    }

    /**
     * Whether the user can edit the submission right now.
     *
     * @param \assign $assignment
     * @param int $userid
     * @return bool
     */
    public function can_edit_submission(\assign $assignment, int $userid): bool {
        return has_capability('mod/assign:submit', $assignment->get_context()) &&
            $assignment->can_edit_submission($userid, $userid) &&
            $assignment->submissions_open($userid);
    }
}
