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
 * Exports an Excel spreadsheet of the  grades in a rubric-graded assignment.
 *
 * @package    report_advancedgrading
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '../../../config.php');
require_once(__DIR__ . '/../../report/advancedgrading/locallib.php');
require_once(__DIR__ . '/../../lib/excellib.class.php');

require_once($CFG->dirroot . '/grade/lib.php');

$data['courseid'] = required_param('id', PARAM_INT); // Course ID.
require_login($data['courseid']);

$dload = optional_param("dload", '', PARAM_BOOL);

$data['headerstyle'] = 'style="background-color:#D2D2D2;"';
$data['reportname'] = get_string('rubricreportname', 'report_advancedgrading');
$data['grademethod'] = 'rubric';
$data['modid'] = required_param('modid', PARAM_INT); // CM ID.

$data = page_setup($data);

$criteria = $DB->get_records_menu('gradingform_rubric_criteria',
    ['definitionid' => (int) $data['gradingdefinition']->definitionid], null, 'id, description');
$data = header_fields($data, $criteria, $data['course'], $data['cm'], $data['gradingdefinition']);

require_capability('mod/assign:grade', $data['context']);
global $PAGE;

$dbrecords = rubric_get_data($data['assign'], $data['cm']);

$data = user_fields($data, $dbrecords);
if (isset($data['students'])) {
    $data = add_groups($data, $data['courseid']);
    $data = get_grades($data, $dbrecords);
}

$data['colcount'] += count($data['criteria']) * 3;
$data['definition'] = get_grading_definition($data['cm']->instance);

$form = $OUTPUT->render_from_template('report_advancedgrading/form', $data);
$table = $OUTPUT->render_from_template('report_advancedgrading/rubric', $data);

$rows = get_rows($data);
if ($rows == "") {
    $rows = '<tr><td colspan=' . $data['colcount'] . '> No marked submissions found </td></tr>';
}
$table .= $rows;
$table .= '   </tbody> </table> </div>';

send_output($form, $dload, $data, $table);

/**
 * Assemble the table rows for grading informationin an array from the database records returned.
 * for each student
 *
 * @param array $data
 * @return string
 */
function get_rows(array $data): string {
    if (isset($data['students'])) {
        $rows = '';
        foreach ($data['students'] as $student) {
            $rows .= '<tr>';
            $rows .= get_student_cells($data, $student);
            foreach (array_keys($data['criterion']) as $crikey) {
                $rows .= '<td>' . $student['grades'][$crikey]['score'] . '</td>';
                $rows .= '<td>' . $student['grades'][$crikey]['definition'] .'</td>';
                $rows .= '<td>' . $student['grades'][$crikey]['feedback'] . '</td>';
            }
            $rows .= get_summary_cells($student);
            $rows .= '</tr>';
        }
    }
    return $rows ?? '';
}
/**
 * Query the database for the student grades.
 *
 * @param integer $assignid
 * @return array
 */
function rubric_get_data(\assign $assign, \cm_info $cm) : array {
     global $DB;
     $sql = "SELECT grf.id as grfid,
                     cm.course, asg.name as assignment,asg.grade as gradeoutof,
                     criteria.description, level.score,
                     level.definition, grf.remark, grf.criterionid,
                     stu.id AS userid, stu.idnumber AS idnumber,
                     stu.firstname, stu.lastname, stu.username,
                     stu.username, stu.email, rubm.username AS grader,
                     gin.timemodified AS modified,
                     ctx.instanceid, ag.grade, asg.blindmarking, assign_comment.commenttext as overallfeedback
                FROM {assign} asg
                JOIN {course_modules} cm ON cm.instance = asg.id
                JOIN {context} ctx ON ctx.instanceid = cm.id
                JOIN {grading_areas}  ga ON ctx.id=ga.contextid
                JOIN {grading_definitions} gd ON ga.id = gd.areaid
                JOIN {gradingform_rubric_criteria} criteria ON (criteria.definitionid = gd.id)
                JOIN {gradingform_rubric_levels} level ON (level.criterionid = criteria.id)
                JOIN {grading_instances} gin ON gin.definitionid = gd.id
                JOIN {assign_grades} ag ON ag.id = gin.itemid
          LEFT  JOIN {assignfeedback_comments} assign_comment on assign_comment.grade = ag.id
                JOIN {user} stu ON stu.id = ag.userid
                JOIN {user} rubm ON rubm.id = gin.raterid
                JOIN {gradingform_rubric_fillings} grf ON (grf.instanceid = gin.id)
                 AND (grf.criterionid = criteria.id) AND (grf.levelid = level.id)
               WHERE cm.id = :assignid AND gin.status = 1
                 AND  stu.deleted = 0
             ORDER BY lastname ASC, firstname ASC, userid ASC, criteria.sortorder ASC";

    $data = $DB->get_records_sql($sql, ['assignid' => $cm->id]);
    $data = set_blindmarking($data, $assign, $cm);
    return $data;
}
