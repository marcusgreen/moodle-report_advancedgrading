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

/**
 * Tests for group mode filtering of report rows.
 *
 * @package    report_advancedgrading
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
namespace report_advancedgrading;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/report/advancedgrading/locallib.php');

/**
 * Tests for report_advancedgrading_filter_by_group().
 *
 * @package    report_advancedgrading
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('report_advancedgrading_filter_by_group')]
#[\PHPUnit\Framework\Attributes\CoversFunction('report_advancedgrading_get_user_groups')]
final class group_filter_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass student in group 1 */
    private \stdClass $student1;

    /** @var \stdClass student in group 2 */
    private \stdClass $student2;

    /** @var \stdClass non-editing teacher in group 1 */
    private \stdClass $teacher;

    /** @var \stdClass */
    private \stdClass $group1;

    /** @var \stdClass */
    private \stdClass $group2;

    /** @var \stdClass */
    private \stdClass $grouping;

    /** @var array fake grading records, keyed per filling, several per student */
    private array $dbrecords;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        $this->course = $gen->create_course();
        $this->student1 = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
        $this->teacher = $gen->create_and_enrol($this->course, 'teacher');

        $this->group1 = $gen->create_group(['courseid' => $this->course->id, 'name' => 'Group1']);
        $this->group2 = $gen->create_group(['courseid' => $this->course->id, 'name' => 'Group2']);
        $this->grouping = $gen->create_grouping(['courseid' => $this->course->id]);
        $gen->create_grouping_group(['groupingid' => $this->grouping->id, 'groupid' => $this->group1->id]);
        $gen->create_grouping_group(['groupingid' => $this->grouping->id, 'groupid' => $this->group2->id]);

        $gen->create_group_member(['groupid' => $this->group1->id, 'userid' => $this->student1->id]);
        $gen->create_group_member(['groupid' => $this->group2->id, 'userid' => $this->student2->id]);
        $gen->create_group_member(['groupid' => $this->group1->id, 'userid' => $this->teacher->id]);

        // Shape matches the get_data() records, which are keyed per filling.
        $this->dbrecords = [
            1 => (object) ['userid' => $this->student1->id],
            2 => (object) ['userid' => $this->student1->id],
            3 => (object) ['userid' => $this->student2->id],
        ];

        // Non-editing teacher normally has accessallgroups, remove it as in the bug report.
        $teacherrole = \core\di::get(\moodle_database::class)->get_record('role', ['shortname' => 'teacher']);
        assign_capability(
            'moodle/site:accessallgroups',
            CAP_PROHIBIT,
            $teacherrole->id,
            \context_course::instance($this->course->id)
        );
    }

    /**
     * Create an assignment with the given group mode and return its cm_info.
     *
     * @param int $groupmode
     * @return \cm_info
     */
    private function create_cm(int $groupmode): \cm_info {
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'groupmode' => $groupmode,
            'groupingid' => $this->grouping->id,
        ]);
        return get_fast_modinfo($this->course->id)->get_cm($assign->cmid);
    }

    /**
     * Return the distinct user ids left in the filtered records.
     *
     * @param array $records
     * @return array
     */
    private function userids(array $records): array {
        $ids = array_unique(array_column($records, 'userid'));
        sort($ids);
        return $ids;
    }

    public function test_separate_groups_without_accessallgroups(): void {
        $cm = $this->create_cm(SEPARATEGROUPS);
        $this->setUser($this->teacher);

        $filtered = report_advancedgrading_filter_by_group($this->dbrecords, $cm);
        $this->assertEquals([$this->student1->id], $this->userids($filtered));
        $this->assertCount(2, $filtered);
    }

    public function test_separate_groups_teacher_in_no_group(): void {
        $cm = $this->create_cm(SEPARATEGROUPS);
        groups_remove_member($this->group1->id, $this->teacher->id);
        $this->setUser($this->teacher);

        $this->assertSame([], report_advancedgrading_filter_by_group($this->dbrecords, $cm));
    }

    public function test_separate_groups_with_accessallgroups(): void {
        $cm = $this->create_cm(SEPARATEGROUPS);
        $this->setAdminUser();

        $filtered = report_advancedgrading_filter_by_group($this->dbrecords, $cm);
        $this->assertCount(3, $filtered);
    }

    public function test_visible_groups_shows_all(): void {
        $cm = $this->create_cm(VISIBLEGROUPS);
        $this->setUser($this->teacher);

        // Active group defaults to the teacher's group, so switch to all participants.
        $_POST['group'] = 0;
        $filtered = report_advancedgrading_filter_by_group($this->dbrecords, $cm);
        unset($_POST['group']);
        $this->assertCount(3, $filtered);
    }

    public function test_no_groups_shows_all(): void {
        $cm = $this->create_cm(NOGROUPS);
        $this->setUser($this->teacher);

        $this->assertCount(3, report_advancedgrading_filter_by_group($this->dbrecords, $cm));
    }

    public function test_user_groups_limited_to_grouping(): void {
        $other = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Outside']);
        $this->getDataGenerator()->create_group_member(['groupid' => $other->id, 'userid' => $this->student1->id]);

        $all = report_advancedgrading_get_user_groups($this->course->id);
        $this->assertEqualsCanonicalizing(['Group1', 'Outside'], $all[$this->student1->id]);

        $ingrouping = report_advancedgrading_get_user_groups($this->course->id, $this->grouping->id);
        $this->assertEquals(['Group1'], $ingrouping[$this->student1->id]);
    }
}
