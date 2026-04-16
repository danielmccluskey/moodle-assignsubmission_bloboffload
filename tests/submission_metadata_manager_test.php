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

namespace assignsubmission_bloboffload;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_bloboffload\local\submission_metadata_manager;

/**
 * Tests for metadata persistence.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \assignsubmission_bloboffload\local\submission_metadata_manager
 */
final class submission_metadata_manager_test extends \advanced_testcase {
    /**
     * Test sync_submission_files marks only selected files active.
     *
     * @return void
     */
    public function test_sync_submission_files_updates_states(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new submission_metadata_manager();

        $summary = (object)[
            'assignment' => 10,
            'submission' => 20,
            'filecount' => 0,
            'lastuploadtime' => 0,
            'timemodified' => time(),
        ];
        $DB->insert_record('assignsubmission_bloboffload', $summary);

        $file1 = (object)[
            'submissionid' => 20,
            'attemptnumber' => 0,
            'userid' => 5,
            'uploadtoken' => 'token1',
            'blobpath' => 'a',
            'bloburl' => 'https://example.invalid/a',
            'originalfilename' => 'a.txt',
            'storedfilename' => 'a.txt',
            'mimetype' => 'text/plain',
            'filesize' => 100,
            'contenthash' => '',
            'etag' => '',
            'state' => 'uploaded',
            'metadatajson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $file2 = clone $file1;
        $file2->uploadtoken = 'token2';
        $file2->blobpath = 'b';
        $file2->bloburl = 'https://example.invalid/b';
        $file2->originalfilename = 'b.txt';
        $file2->storedfilename = 'b.txt';

        $file1->id = $DB->insert_record('assignsubmission_bloboffload_file', $file1);
        $file2->id = $DB->insert_record('assignsubmission_bloboffload_file', $file2);

        $manager->sync_submission_files(10, 20, [$file1->id]);

        $this->assertSame(
            'available',
            $DB->get_field(
                'assignsubmission_bloboffload_file',
                'state',
                ['id' => $file1->id]
            )
        );
        $this->assertSame(
            'deleted',
            $DB->get_field(
                'assignsubmission_bloboffload_file',
                'state',
                ['id' => $file2->id]
            )
        );
    }
}
