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

use report_advancedgrading\rubric;
use report_advancedgrading\guide;

/**
 * Class report
 *
 * Class for tests related to reubrics report events.
 *
 * @package    report_advancedgrading
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class locallib_test extends \advanced_testcase {

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
     * assignment id
     *
     * @var int
     */
    public $assign;

    /**
     * Extract and install the mbz backup of a course
     * containing assignments using the advanced grading
     * methods with user attempts
     *
     * @return void
     */
    public function setUp(): void {
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


    // Use the generator helper.
    use \mod_assign_test_generator;
    /**
     * check that values in settings configure
     * what userfields are displayed
     *
     * @covers ::user_fields
     *
     * @return void
     */
    public function test_userfields() {
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
    public function test_rubric() {
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
    public function test_guide() {
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
}
