<?php
// This file is part of Moodle - http://moodle.org/

namespace assignsubmission_bloboffload;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_bloboffload\local\blob_path_builder;

/**
 * Tests for blob path generation.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \assignsubmission_bloboffload\local\blob_path_builder
 */
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
