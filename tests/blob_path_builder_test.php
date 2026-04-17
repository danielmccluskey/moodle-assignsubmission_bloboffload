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

use assignsubmission_bloboffload\local\blob_path_builder;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Tests for blob path generation.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(blob_path_builder::class)]
final class blob_path_builder_test extends \advanced_testcase {
    /**
     * Test blob path shape.
     *
     * @return void
     */
    public function test_build_uses_id_based_path(): void {
        $builder = new blob_path_builder();

        $assignment = $this->createMock(\assign::class);
        $assignment->method('get_course')->willReturn((object)['id' => 12]);
        $assignment->method('get_course_module')->willReturn((object)['id' => 34]);
        $assignment->method('get_instance')->willReturn((object)['id' => 56]);
        $submission = (object)['id' => 78, 'attemptnumber' => 2];

        $path = $builder->build(
            $assignment,
            $submission,
            90,
            'Essay Final.docx',
            'abc123'
        );

        $this->assertSame(
            'course-12/cm-34/assign-56/submission-78/attempt-2/'
                . 'user-90/abc123__Essay Final.docx',
            $path
        );
    }

    /**
     * Test invalid filename rejection.
     *
     * @return void
     */
    public function test_invalid_filename_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        $builder = new blob_path_builder();
        $builder->sanitize_filename('../');
    }
}
