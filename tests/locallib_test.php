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
 * @package    report_advancedgrading
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
namespace report_advancedgrading;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/mod/assign/externallib.php');

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/report/advancedgrading/locallib.php');

use context_module;
use core_component;
use gradingform_rubric_ranges_controller;

/**
 * Class report
 *
 * Class for tests related to reubrics report events.
 *
 * @package    report_advancedgrading
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
final class locallib_test extends \advanced_testcase {
    // Use the generator helper.
    use \mod_assign_test_generator;

    /**
     * Unique id of course from db
     *
     * @var $courseid
     */
    public $rubricassignid;

    /**
     * guide assignent id
     *
     * @var int
     */
    public $guideassignid;

    /**
     * guide assignent id
     *
     * @var int
     */
    public $rubricrangesassignid;

    /**
     * assignment id
     *
     * @var int
     */
    public $assign;

    /**
     * @var int the id of the generated course
     */
    private int $courseid;

    /**
     * Extract and install the mbz backup of a course
     * containing assignments using the advanced grading
     * methods with user attempts
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        global $USER, $CFG, $DB;
        $this->setAdminUser();

        set_config('import_general_duplicate_admin_allowed', 1, 'backup');

        $foldername = 'backup-advgrade';
        $fp = get_file_packer('application/vnd.moodle.backup');
        $tempdir = make_backup_temp_directory($foldername);
        $fp->extract_to_pathname($CFG->dirroot . '/report/advancedgrading/tests/fixtures/backup-advgrade.mbz', $tempdir);

        $this->courseid = \restore_dbops::create_new_course(
            'Test fullname',
            'Test shortname',
            1
        );
        $controller = new \restore_controller(
            'backup-advgrade',
            $this->courseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $controller->execute_precheck();
        $controller->execute_plan();

        $this->rubricassignid = $DB->get_field('assign', 'id', ['name' => 'Rubric with blind marking']);
        $this->guideassignid = $DB->get_field('assign', 'id', ['name' => 'Marking Guide with blind marking']);

        $generator = $this->getDataGenerator();
        $teacher1 = $generator->create_user(['username' => 't1']);

        $generator->enrol_user($teacher1->id, $this->courseid, 'editingteacher');
    }

    /**
     * Test get_grading_definition function with a rubric grading method
     * Created as a very basic test for the patch contributed to this
     * https://github.com/marcusgreen/moodle-report_advancedgrading/issues/15
     *
     * @covers ::get_grading_definition
     *
     */
    public function test_get_grading_definition(): void {
        $this->resetAfterTest();
        $definition = get_grading_definition($this->rubricassignid);
        $this->assertEquals($definition->activemethod, 'rubric');
    }

    /**
     * check that values in settings configure
     * what userfields are displayed
     *
     * @covers ::user_fields
     *
     * @return void
     */
    public function test_userfields(): void {
        $this->resetAfterTest();
        $cm = get_coursemodule_from_instance('assign', $this->rubricassignid, $this->courseid);
        $data['headerstyle'] = 'style="background-color:#D2D2D2;"';
        $data['reportname'] = get_string('rubricreportname', 'report_advancedgrading');
        $data['grademethod'] = 'rubric';
        $data['modid'] = $cm->id;
        $data['courseid'] = $this->courseid;

        set_config('profilefields', 'username,firstname,lastname,email,groups', 'report_advancedgrading');

        $data = init($data);
        $rubric = new rubric();

        $data['dbrecords'] = $rubric->get_data($data['assign'], $data['cm']);
        $data = user_fields($data, $data['dbrecords']);
        $student = reset($data['students']);
        $this->assertArrayHasKey('firstname', $student);
        $this->assertArrayHasKey('lastname', $student);
        $this->assertArrayHasKey('email', $student);

        $data = add_groups($data, $data['courseid']);
        $data = get_grades($data, $data['dbrecords']);
        $rows = $rubric->get_rows($data);
        $this->assertStringContainsString('Group1', $rows);
    }
    /**
     * Check output of report for rubric grading method
     *
     * @covers ::rubric->get_data
     *
     * @return void
     */
    public function test_rubric(): void {
        $this->resetAfterTest();
        global $DB;
        $cm = get_coursemodule_from_instance('assign', $this->rubricassignid, $this->courseid);
        $data['headerstyle'] = 'style="background-color:#D2D2D2;"';
        $data['reportname'] = get_string('rubricreportname', 'report_advancedgrading');
        $data['grademethod'] = 'rubric';
        $data['modid'] = $cm->id;
        $data['courseid'] = $this->courseid;
        $data = init($data);

        $this->assertContains('username', $data['profilefields']);
        // Assignment was set up with 3 criteria.
        $this->assertCount(3, $data['criteriarecord']);

        $rubric = new rubric();
        $data['dbrecords'] = $rubric->get_data($data['assign'], $data['cm']);
        $gradeduser = reset($data['dbrecords'])->username;
        $enrolledusers = get_enrolled_users($data['context']);
        $enrollednames = [];
        foreach ($enrolledusers as $enuser) {
            $enrollednames[] = $enuser->username;
        }
        // Confirm blind marking prevents showing real names.
        $this->assertNotContains($gradeduser, $enrollednames);

        $teacher = $DB->get_record('user', ['username' => 't1']);
        $this->setUser($teacher);
        // Reveal identities and confirmm that shows in report.
        $data['assign']->reveal_identities();
        $data['dbrecords'] = $rubric->get_data($data['assign'], $data['cm']);
        $gradeduser = reset($data['dbrecords'])->username;
        $this->assertContains($gradeduser, $enrollednames);
        set_config('groups', true, 'report_advancedgrading');
        $data['dbrecords'] = $rubric->get_data($data['assign'], $data['cm']);
        $data = user_fields($data, $data['dbrecords']);
    }

    /**
     * Check output of report for marking guide grading method
     *
     * @covers ::guide->get_data
     *
     * @return void
     */
    public function test_guide(): void {
        $this->resetAfterTest();
        global $DB;
        $cm = get_coursemodule_from_instance('assign', $this->guideassignid, $this->courseid);
        $data['headerstyle'] = 'style="background-color:#D2D2D2;"';
        $data['reportname'] = get_string('rubricreportname', 'report_advancedgrading');
        $data['grademethod'] = 'guide';
        $data['modid'] = $cm->id;
        $data['courseid'] = $this->courseid;
        $data = init($data);

        $this->assertContains('username', $data['profilefields']);
        $this->assertCount(2, $data['criteriarecord']);

        $guide = new guide();
        $data['dbrecords'] = $guide->get_data($data['assign'], $data['cm']);
        $gradeduser = reset($data['dbrecords'])->username;
        $enrolledusers = get_enrolled_users($data['context']);
        $enrollednames = [];
        foreach ($enrolledusers as $enuser) {
            $enrollednames[] = $enuser->username;
        }
        // Confirm blind marking does not show real names.
        $this->assertNotContains($gradeduser, $enrollednames);

        $teacher = $DB->get_record('user', ['username' => 't1']);
        $this->setUser($teacher);
        // Reveal identities and confirmm that shows in repor.
        $data['assign']->reveal_identities();
        $data['dbrecords'] = $guide->get_data($data['assign'], $data['cm']);
        $gradeduser = reset($data['dbrecords'])->username;
        $this->assertContains($gradeduser, $enrollednames);
    }

    /**
     * Check output of report for rubric grading method
     *
     * @covers ::rubric_ranges->get_data
     *
     * @return void
     */
    public function test_rubric_ranges(): void {
        global $DB, $USER;
        $this->resetAfterTest();

        if (core_component::get_plugin_directory('gradingform', 'rubric_ranges') === null) {
            $this->markTestSkipped('Rubric ranges plugin not installed');
        }

        // Fetch generators.
        $generator = \testing_util::get_data_generator();
        $rubricgenerator = $generator->get_plugin_generator('gradingform_rubric_ranges');
        // Create items required for testing.
        $course = $this->getDataGenerator()->create_course();
        $module = $generator->create_module('assign', ['course' => $course, 'name' => 'Rubric Range']);
        $context = context_module::instance($module->cmid);
        $user = $this->getDataGenerator()->create_user();
        $cm = get_coursemodule_from_instance('assign', $module->id);
        $this->setUser($user);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $controllerrange = $rubricgenerator->get_test_rubric_ranges($context, 'assign', 'submissions');
        $itemid = $DB->get_field('assign', 'id', ['name' => 'Rubric Range']);
        $instance = $controllerrange->create_instance($student->id, $itemid);
        // Set assign grade data.
        $assigngrade = new \stdClass();
        $assigngrade->assignment = $module->id;
        $assigngrade->userid = $student->id;
        $assigngrade->timecreated = time();
        $assigngrade->timemodified = $assigngrade->timecreated;
        $assigngrade->grader = $USER->id;
        $assigngrade->grade = 50;
        $assigngrade->attemptnumber = 0;
        // Get an assign grade ID for submitted data.
        $gid = $DB->insert_record('assign_grades', $assigngrade);
        $result = $rubricgenerator->get_submitted_form_data($controllerrange, $gid, [
                'Spelling is important' => [
                        'score' => 5,
                        'remark' => 'Looks good to me',
                ],
                'Pictures' => [
                        'score' => 2,
                        'remark' => 'These picture are ok',
                ],
        ]);
        $instance->update($result);
        $this->assertInstanceOf(gradingform_rubric_ranges_controller::class, $controllerrange);

        $data['headerstyle'] = 'style="background-color:#D2D2D2;"';
        $data['reportname'] = get_string('rubricrangesreportname', 'report_advancedgrading');
        $data['grademethod'] = 'rubric_ranges';
        $data['modid'] = $cm->id;
        $data['courseid'] = $course->id;
        $data = init($data);

        $this->assertContains('username', $data['profilefields']);
        // Assignment was set up with 2 criteria.
        $this->assertCount(2, $data['criteriarecord']);
        $rubricranges = new rubric_ranges();
        $data['dbrecords'] = $rubricranges->get_data($data['assign'], $data['cm']);
        foreach ($data['dbrecords'] as $assigndata) {
            // Test if remark exist in extracted data.
            $this->assertNotEmpty($assigndata->remark);
            if (!empty($result['criteria'][$assigndata->criterionid])) {
                $remark = $result['criteria'][$assigndata->criterionid]['remark'];
                // Test if remark contains the correct comment in extracted data.
                $this->assertEquals($remark, $assigndata->remark);
            }
        }
    }
}
