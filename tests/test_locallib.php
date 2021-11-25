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
 * Tests for rubrics report events.
 *
 * @package    report_rubrics
 * @copyright  2021 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/report/advancedgrading/locallib.php');
require_once($CFG->dirroot . '/report/advancedgrading/rubric.php');

require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/mod/assign/externallib.php');

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

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

    public $courseid;
    public $assign;

    public function setUp() : void {
       // $this->preventResetByRollback();
        global $USER, $CFG, $DB;
        $this->setAdminUser();

        $DB->update_record('user', (object) ['id' => $USER->id, 'username' => 'admin_xyz']);
        $foldername = 'backup-advgrade';
        $fp = get_file_packer('application/vnd.moodle.backup');
        $tempdir = make_backup_temp_directory($foldername);
        $fp->extract_to_pathname($CFG->dirroot . '/report/advancedgrading/tests/fixtures/backup-advgrade.mbz', $tempdir);

        $this->courseid = restore_dbops::create_new_course(
            'Test fullname', 'Test shortname', 1);
        $controller = new restore_controller(
            'backup-advgrade',
            $this->courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $controller->execute_precheck();
        $controller->execute_plan();
        $this->assignid = $DB->get_field('assign', 'id', ['name' => 'Rubric Assignment']);
    }

     // Use the generator helper.
     use mod_assign_test_generator;


    public function test_get_data() {
        global $DB;

        $course = $DB->get_record('course', ['id' => $this->courseid]);
        $cm = get_coursemodule_from_instance('assign', $this->assignid, $this->courseid);
        $gdef = get_grading_definition($cm->instance);
        $header = report_advancedgrading_get_header($course->fullname, $cm->name, $gdef->activemethod, $gdef->definition);

        $assign = context_module::instance($cm->id);
        $students = report_componentgrades_get_students($assign, $cm);

    }
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

    public function test_get_rubric_data(){
        global $DB;
        $this->resetAfterTest();

        // Fetch generators.
        $generator = \testing_util::get_data_generator();
        $rubricgenerator = $generator->get_plugin_generator('gradingform_rubric');

        // Create items required for testing.
        $course = $generator->create_course();
        $assign = $this->create_instance($course, [
        'name' => 'Rubric Assign',
        'course' => $course,
        'assignsubmission_onlinetext_enabled' => true
         ]);
        $student = $generator->create_user();

        $context = $assign->get_context();

        // Data for testing.
        $name = 'myfirstrubric';
        $description = 'My first rubric';
        $criteria = [
            'Alphabet' => [
                'Not known' => 0,
                'Letters known but out of order' => 1,
                'Letters known in order ascending' => 2,
                'Letters known and can recite forwards and backwards' => 4,
            ],
            'Times tables' => [
                'Not known' => 0,
                '2 times table known' => 2,
                '2 and 5 times table known' => 4,
                '2, 5, and 10 times table known' => 8,
            ],
        ];

         // Unit under test.
        $this->setUser($student);
        $controller = $rubricgenerator->create_instance($context, 'mod_assign', 'submission', $name, $description, $criteria);
        $submission = $assign->get_user_submission($student->id, true);
        $data = (object) [
             'onlinetext_editor' => [
                 'itemid' => file_get_unused_draft_itemid(),
                 'text' => 'Submission text',
                 'format' => FORMAT_PLAIN,
             ],
         ];

         $plugin = $assign->get_submission_plugin_by_type('onlinetext');
         $plugin->save($submission, $data);

         $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
         $assign->testable_update_submission($submission, $student->id, true, false);
         $filling = (object) [
            'instanceid' => $submission->id,
            'criterionid' => 1,
            'levelid' => 2,
            'remark' => "Well done",
            'remarkformat' => 0
         ];
         $DB->insert_record('gradingform_rubric_fillings', $filling);

    }
    public function test_get_headers() {
        $this->resetAfterTest();
        $generator = \testing_util::get_data_generator();

        $header = get_header('coursename', 'modname', 'method', 'methodname');
        $user = $generator->create_user();
        $coursename = 'course01';
        $assignmentname = 'RubricTestAssignment';

        $course = $generator->create_course(['name' => $coursename]);
        $assign = $generator->create_module('assign', ['course' => $course->id, 'name' => $assignmentname]);
        $definition = 'Rubric Advanced Grading';
        $headeer = get_header($coursename, $assignmentname, 'rubric', $definition);

    }

}