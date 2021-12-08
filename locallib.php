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
 * Lang strings.
 *
 * Language strings to be used by report/rubrics
 *
 * @package    report_rubrics
 * @copyright  2021 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/mod/assign/locallib.php');


defined('MOODLE_INTERNAL') || die;
/**
 * Get all students given the course/module details
 * @param \context_module $modcontext
 * @param \stdClass $cm
 * @return array
 */
function report_componentgrades_get_students(\context_module $modcontext, \stdClass $cm) :array {
    global $DB;
    $assign = new assign($modcontext, $cm, $cm->course);
    $params = ['courseid' => $cm->course];
    $sql = 'SELECT stu.id AS userid, stu.idnumber AS idnumber, stu.firstname, stu.lastname,
                   stu.username AS username
              FROM {user} stu
              JOIN {user_enrolments} ue
                ON ue.userid = stu.id
              JOIN {enrol} enr
                ON ue.enrolid = enr.id
             WHERE enr.courseid = :courseid
          ORDER BY lastname ASC, firstname ASC, userid ASC';
         $users = $DB->get_records_sql($sql, $params);

    if ($assign->is_blind_marking()) {
        foreach ($users as &$user) {
            $user->firstname = '';
            $user->lastname = '';
            $user->student = get_string('participant', 'assign') .
             ' ' . \assign::get_uniqueid_for_user_static($cm->instance, $user->userid);
        }
    }
    return $users;
}
function get_grading_definition(int $assignid) {
    global $DB;
    $sql = "SELECT gdef.id as definitionid, ga.activemethod, gdef.name as definition from {assign} assign
            JOIN {course_modules} cm ON cm.instance = assign.id
            JOIN {context} ctx ON ctx.instanceid = cm.id
            JOIN {grading_areas} ga ON ctx.id=ga.contextid
            JOIN {grading_definitions} gdef ON ga.id = gdef.areaid
            WHERE assign.id = :assignid";
    $definition = $DB->get_record_sql($sql, ['assignid' => $assignid]);
    return $definition;
}
function rubric_get_criteria(int $definitionid) {
    global $DB;

    $criteria = $DB->get_records_menu('gradingform_rubric_criteria', ['definitionid' => $definitionid], null, 'id, description');
    return $criteria;
}

function x_rubric_get_data(int $assignid) {
    global $DB;
    $data = $DB->get_records_sql("SELECT  grf.id AS grfid, crs.shortname AS course, asg.name AS assignment,
                                          grc.description, grl.definition, grl.score, grf.remark, grf.criterionid,
                                          rubm.username AS grader, stu.id AS userid, stu.idnumber AS idnumber, stu.firstname,
                                          stu.lastname, stu.username AS student, gin.timemodified AS modified
                                    FROM {course} crs
                                    JOIN {course_modules} cm ON crs.id = cm.course
                                    JOIN {assign} asg ON asg.id = cm.instance
                                    JOIN {context} c ON cm.id = c.instanceid
                                    JOIN {grading_areas}  ga ON c.id=ga.contextid
                                    JOIN {grading_definitions} gd ON ga.id = gd.areaid
                                    JOIN {gradingform_rubric_criteria} grc ON (grc.definitionid = gd.id)
                                    JOIN {gradingform_rubric_levels} grl ON (grl.criterionid = grc.id)
                                    JOIN {grading_instances} gin ON gin.definitionid = gd.id
                                    JOIN {assign_grades} ag ON ag.id = gin.itemid
                                    JOIN {user} stu ON stu.id = ag.userid
                                    JOIN {user} rubm ON rubm.id = gin.raterid
                                    JOIN {gradingform_rubric_fillings} grf ON (grf.instanceid = gin.id)
                                    AND (grf.criterionid = grc.id) AND (grf.levelid = grl.id)
                                    WHERE cm.id = :assignid AND gin.status = 1
                                    AND  stu.deleted = 0
                                    ORDER BY lastname ASC, firstname ASC, userid ASC, grc.sortorder ASC,
                                    grc.description ASC", ['assignid' => $assignid]);
                                    return $data;
}

function rubric_get_data(int $assignid) {
         global $DB;
         $sql = "SELECT grf.id as grfid,
                        cm.course,
                        asg.name as assignment,
                        grc.description,  grl.score,  grf.remark, grf.criterionid,
                        stu.id AS userid,
                        stu.idnumber AS idnumber,
                        stu.firstname, stu.lastname,
                        stu.username AS student,
                        rubm.username AS grader,
                        gin.timemodified AS modified
                        FROM {assign} asg
                        JOIN {course_modules} cm ON cm.instance = asg.id
                        JOIN {context} ctx ON ctx.instanceid = cm.id
                        JOIN {grading_areas}  ga ON ctx.id=ga.contextid
                        JOIN {grading_definitions} gd ON ga.id = gd.areaid
                        JOIN {gradingform_rubric_criteria} grc ON (grc.definitionid = gd.id)
                        JOIN {gradingform_rubric_levels} grl ON (grl.criterionid = grc.id)
                        JOIN {grading_instances} gin ON gin.definitionid = gd.id
                        JOIN {assign_grades} ag ON ag.id = gin.itemid
                        JOIN {user} stu ON stu.id = ag.userid
                        JOIN {user} rubm ON rubm.id = gin.raterid
                        JOIN {gradingform_rubric_fillings} grf ON (grf.instanceid = gin.id)
                         AND (grf.criterionid = grc.id) AND (grf.levelid = grl.id)
                       WHERE cm.id = :assignid AND gin.status = 1
                        AND  stu.deleted = 0";

                        $data = $DB->get_records_sql($sql, ['assignid' => $assignid] );

                        return $data;
}

function get_grades(int $assignid) {
        //     $sql = "SELECT
        //     mc.instanceid,
        //     mga.areaname,
        //     mga.activemethod,
        //     mgd.name,
        //     mgi.rawgrade,
        //     mgi.raterid,
        //     mgi.itemid,
        //     mgi.status,
        //     mgi.feedback,
        //     mgrf.remark
        // FROM
        //     mdl311.mdl_grading_areas mga
        // INNER JOIN mdl311.mdl_context mc ON
        //     mga.contextid = mc.id
        // INNER JOIN mdl311.mdl_grading_definitions mgd ON
        //     mga.id = mgd.areaid
        // INNER JOIN mdl311.mdl_grading_instances mgi ON
        //     mgd.id = mgi.definitionid
        // INNER JOIN mdl311.mdl_gradingform_rubric_fillings mgrf ON
        //     mgrf.instanceid = mgi.id
        // INNER JOIN mdl311.mdl_assign_grades mag ON
        //     mag.id = mgi.itemid
        // WHERE
        //     mc.instanceid = 117;"
}
/**
 * Add header text to report, name of course etc

 */
function report_advancedgrading_get_header($coursename, $assignmentname, $method, $definition) {

    $cells[]  = [
        'row' => 0,
        'col' => 0,
        'value' => $coursename
    ];
    $cells[]  = [
        'row' => 1,
        'col' => 0,
        'value' => $assignmentname
    ];
    $cells[]  = [
        'row' => 2,
        'col' => 0,
        'value' => get_string($method, 'report_advancedgrading').":"
    ];
    $cells[]  = [
        'row' => 2,
        'col' => 1,
        'value' => $definition
    ];
    return $cells;

    // $sheet->write_string(1, 0, $modname, $format);
    // $methodname = ($method == 'rubric' ? 'Rubric: ' : 'Marking guide: ') . $methodname;
    // $sheet->write_string(2, 0, $methodname, $format);

    // $sheet->write_string(HEADINGSROW, 0, get_string('student', 'report_componentgrades'), $format);
    // $sheet->merge_cells(HEADINGSROW, 0, HEADINGSROW, 2, $format);
    // $sheet->write_string(5, $col++, get_string('firstname', 'report_componentgrades'), $format2);
    // $sheet->write_string(5, $col++, get_string('lastname', 'report_componentgrades'), $format2);
    // $sheet->write_string(5, $col++, get_string('username', 'report_componentgrades'), $format2);
    // if (get_config('report_componentgrades', 'showstudentid')) {
    //     $sheet->write_string(5, $col, get_string('studentid', 'report_componentgrades'), $format2);
    //     $col++;
    // }
    // return $col;

}

class cell {
    private $text = "";
    public function get_text() :string {
        return $this->text;
    }
    public function set_text(string $textvalue) {
        $this->text = $textvalue;
    }


}
