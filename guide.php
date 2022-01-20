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
 * Exports an Excel spreadsheet of the grades in a marking guide graded assignment.
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

global $PAGE;

$dload = optional_param("dload", '', PARAM_BOOL);
$data['headerstyle'] = 'style="background-color:#D2D2D2;"';
$data['reportname'] = get_string('guidereportname', 'report_advancedgrading');
$data['grademethod'] = 'guide';
$data['modid'] = required_param('modid', PARAM_INT); // CM ID.

$data = page_setup($data);

require_capability('mod/assign:grade', $data['context']);

$dbrecords = guide_get_data($data['assign'], $data['cm']);

$data = user_fields($data, $dbrecords);
if (isset($data['students'])) {
    $data = add_groups($data, $data['courseid']);
    $data = get_grades($data, $dbrecords);
}

$data['colcount'] += count($data['criteria']) * 2;
$form = $OUTPUT->render_from_template('report_advancedgrading/form', $data);
$table = $OUTPUT->render_from_template('report_advancedgrading/guide', $data);
$rows = get_rows($data);
$table .= $rows;
$table .= '  </tbody> </table> </div>';

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
        $row = '';
        foreach ($data['students'] as $student) {
            $row .= '<tr>';
            $row .= get_student_cells($data, $student);
            foreach (array_keys($data['criterion']) as $crikey) {
                $row .= '<td>' . number_format($student['grades'][$crikey]['score'], 2) . '</td>';
                $row .= '<td>' . $student['grades'][$crikey]['feedback'] . '</td>';
            }
            $row .= get_summary_cells($student);
            $row .= '</tr>';
        }

    }
    return $row ?? "";
}
/**
 * Assemble the table rows for grading informationin an array from the database records returned.
 * for each student
 *
 * @param array $data
 * @return array
 */
function guide_get_data(\assign $assign, \cm_info $cm) :array {
    global $DB;
        $sql = "SELECT fillings.id AS ggfid, cm.course AS course, asg.name AS assignment,
                asg.grade as gradeoutof,gd.name AS guide,
                criteria.description,criteria.shortname,
                fillings.score, fillings.remark, fillings.criterionid, rubm.username AS grader,
                stu.id AS userid, stu.idnumber AS idnumber, stu.firstname, stu.lastname,
                stu.username, stu.email, gin.timemodified AS modified, ag.grade,
                assign_comment.commenttext as overallfeedback
        FROM {assign} asg
        JOIN {course_modules} cm ON cm.instance = asg.id
        JOIN {context} ctx ON ctx.instanceid = cm.id
        JOIN {grading_areas}  ga ON ctx.id = ga.contextid
        JOIN {grading_definitions} gd ON ga.id = gd.areaid
        JOIN {gradingform_guide_criteria} criteria ON (criteria.definitionid = gd.id)
        JOIN {grading_instances} gin ON gin.definitionid = gd.id
        JOIN {assign_grades} ag ON ag.id = gin.itemid
  LEFT  JOIN {assignfeedback_comments} assign_comment on assign_comment.grade = ag.id
        JOIN {user} stu ON stu.id = ag.userid
        JOIN {user} rubm ON rubm.id = gin.raterid
        JOIN {gradingform_guide_fillings} fillings ON (fillings.instanceid = gin.id)
         AND (fillings.criterionid = criteria.id)
         WHERE cm.id = :assignid AND gin.status = 1
         AND  stu.deleted = 0
         ORDER BY lastname ASC, firstname ASC, userid ASC, criteria.sortorder ASC";
    $data = $DB->get_records_sql($sql, ['assignid' => $cm->id]);
    $data = set_blindmarking($data, $assign, $cm);
    return $data;
}
