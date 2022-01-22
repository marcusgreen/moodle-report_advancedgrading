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
class test_locallib extends advanced_testcase {

    /**
     * Unique id of course from db
     *
     * @var $courseid
     */
    public $courseid;

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
    public function setUp() : void {
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
        $assigns = $DB->get_records('assign');
        foreach($assigns as $a) {
           // echo $a->name;
        }
        $this->rubricassignid = $DB->get_field('assign', 'id', ['name' => 'Rubric with Blind Marking']);
        $this->guideassignid = $DB->get_field('assign', 'id', ['name' => 'Marking Guide With Blind Marking']);

    }

     // Use the generator helper.
     use mod_assign_test_generator;

    public function test_rubric() {
        $this->resetAfterTest();

        $cm = get_coursemodule_from_instance('assign', $this->rubricassignid, $this->courseid);
        $data['headerstyle'] = 'style="background-color:#D2D2D2;"';
        $data['reportname'] = get_string('rubricreportname', 'report_advancedgrading');
        $data['grademethod'] = 'rubric';
        $data['modid'] = $cm->id;
        $data['courseid'] = $this->courseid;
        $data = init($data);

        $this->assertContains('username', $data['profilefields']);
        $this->assertCount(2,$data['criteriarecord']);

        $rubric = new rubric();
        $data['dbrecords']= $rubric->get_data($data['assign'], $data['cm']);
        $gradeduser = reset($data['dbrecords'])->username;
        $enrolledusers = get_enrolled_users($data['context']);
        $enrollednames = [];
        foreach($enrolledusers as $enuser) {
            $enrollednames[] = $enuser->username;
        }
        // Confirme blind marking does not show real names.
       $this->assertNotContains($gradeduser, $enrollednames);

    }
    public function test_guide() {
        $this->resetAfterTest();
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
        $data['dbrecords']= $guide->get_data($data['assign'], $data['cm']);
        $gradeduser = reset($data['dbrecords'])->username;
        $enrolledusers = get_enrolled_users($data['context']);
        $enrollednames = [];
        foreach($enrolledusers as $enuser) {
            $enrollednames[] = $enuser->username;
        }
        // Confirme blind marking does not show real names.
       $this->assertNotContains($gradeduser, $enrollednames);

    }


}