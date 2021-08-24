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
 * Tests for componentgrades report events.
 *
 * @package    report_rubrics
 * @copyright  2021 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/report/rubrics/locallib.php');

/**
 * Class report
 *
 * Class for tests related to reubrics report events.
 *
 * @package    report_rubrics
 * @copyright  2021 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class report_rubrics_locallib_test extends advanced_testcase {

    /**
     * Confirm that students are returned from get_students
     * method and that blind marking is respected
     */

    public function test_get_students() {
        $this->resetAfterTest();
        $generator = \testing_util::get_data_generator();

        $course = $generator->create_course(['name' => 'course01']);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $assign = $generator->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id, $course->id);
        $modcontext = context_module::instance($cm->id);
        $students = report_componentgrades_get_students($modcontext, $cm);
        $student = reset($students);
        $this->assertEquals($user->firstname, $student->firstname);
        $this->assertEquals($user->lastname, $student->lastname);
        $this->assertEquals($user->username, $student->username);

        $blindassign = $generator->create_module('assign', ['course' => $course->id, 'blindmarking' => true]);
        $cm = get_coursemodule_from_instance('assign', $blindassign->id, $course->id);
        $modcontext = context_module::instance($cm->id);
        $students = report_componentgrades_get_students($modcontext, $cm);
        $student = reset($students);

        $this->assertEquals($student->firstname, "");
        $this->assertEquals($student->lastname, "");
        $this->assertNotEquals($student->student, $student->username);
    }
    public function test_get_headers() {
        $this->resetAfterTest();
        $generator = \testing_util::get_data_generator();

        $user = $generator->create_user();
        $coursename = 'course01';
        $assignmentname = 'RubricTestAssignment';

        $course = $generator->create_course(['name' => $coursename]);
        $assign = $generator->create_module('assign', ['course' => $course->id, 'name' => $assignmentname]);
        $definition = 'Rubric Advanced Grading';
        $headeer = get_header($coursename, $assignmentname, 'rubric', $definition);

    }

}