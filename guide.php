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
 * Exports an Excel spreadsheet of the component grades in a rubric-graded assignment.
 *
 * @package    report_advancedgrading
 * @copyright  2021 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '../../../config.php');
require_once(__DIR__ . '/../../report/advancedgrading/locallib.php');
require_once(__DIR__ . '/../../lib/excellib.class.php');

require_once $CFG->dirroot . '/grade/lib.php';
global $PAGE;

$dload = optional_param("dload", '', PARAM_BOOL);

$data['headerstyle'] = 'style="background-color:#D2D2D2;"';
$data['reportname'] = 'Marking guide report';
$data['gradeplugin'] = 'guide';
$data = page_setup($data);
$criteria = $DB->get_records_menu('gradingform_guide_criteria', ['definitionid' => (int) $data['gradingdefinition']->definitionid], null, 'id, description');
$data = header_fields($data, $criteria, $data['course'], $data['cm'], $data['gradingdefinition']);

$context = context_module::instance($data['cm']->id);
$assign = new assign($context, $data['cm'], $data['cm']->get_course());

require_capability('mod/assign:grade', $context);

$dbrecords = guide_get_data($assign, $data['cm']);

$data = user_fields($data, $dbrecords);
if(isset($data['students'])) {
    $data = add_groups($data, $data['courseid']);
    $data = get_grades($data, $dbrecords);
}

$data['colcount'] = $data['studentspan'] = count($data['profilefields']);
$data['colcount'] += count($data['criteria']) * 2;
$data['colcount'] += 4; //Always 4 cols in the summary;

$form = $OUTPUT->render_from_template('report_advancedgrading/form', $data);
$table = $OUTPUT->render_from_template('report_advancedgrading/guide', $data);
$rows = get_rows($data);

$table .= $rows;
$table .= '   </tbody> </table> </div>';
if ($dload) {
    download($table, $data['grademethod']);
    echo $OUTPUT->header();
} else {
    $html = $form . $table;
    $output = $PAGE->get_renderer('report_log');
    $PAGE->set_pagelayout('report');
    echo $output->header();
    echo $output->container($html, 'advancedgrading-main');
}
echo $OUTPUT->footer();


/**
 * Assemble the table rows for grading informationin an array from the database records returned.
 * for each student
 *
 * @param array $data
 * @return string
 */
function get_rows(array $data): string {
    if (isset($data['students'])) {
        $row = '<tr>';
        foreach ($data['students'] as $student) {
            $row .= get_student_cells($data,$student);
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
function guide_get_data($assign, $cm) :array {
    global $DB;
        $sql = "SELECT fillings.id AS ggfid, cm.course AS course, asg.name AS assignment, gd.name AS guide,
        criteria.description,
        criteria.shortname, fillings.score, fillings.remark, fillings.criterionid, rubm.username AS grader,
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