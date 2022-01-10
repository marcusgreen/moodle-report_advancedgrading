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

$dload = optional_param("dload", '', PARAM_BOOL);

$courseid  = required_param('id', PARAM_INT); // Course ID.
$data['courseid'] = $courseid;
$data['modid'] = required_param('modid', PARAM_INT); // CM I

global $PAGE;

$PAGE->requires->js_call_amd('report_advancedgrading/table_sort', 'init');
$PAGE->requires->jquery();

$PAGE->set_url(new moodle_url('/report/advancedgrading/index.php', $data));

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);

$modinfo = get_fast_modinfo($courseid);
$assign = $modinfo->get_cm($data['modid']);

$modcontext = context_module::instance($assign->id);
require_capability('mod/assign:grade', $modcontext);

$context = context_course::instance($course->id);

$renderer = $PAGE->get_renderer('core_user');

$PAGE->set_title('Rubric Report');
$PAGE->set_heading('Report Name');

// Profile fields.
$profileconfig = trim(get_config('report_advancedgrading', 'profilefields'));
$data['profilefields'] = empty($profileconfig) ? [] : explode(',', $profileconfig);

$gdef = get_grading_definition($assign->instance);

$cm = get_coursemodule_from_instance('assign', $assign->instance, $course->id);
$criteria = $DB->get_records_menu('gradingform_rubric_criteria', ['definitionid' => (int) $gdef->definitionid], null, 'id, description');

$data['silverbackground'] = "background-color:#D2D2D2;'";

$data = header_fields($data, $criteria, $course, $assign, $gdef);
$dbrecords = rubric_get_data($assign->id);

$data = user_fields($data, $dbrecords);
if(isset($data['students'])) {
    $data = add_groups($data, $courseid);
    $data = get_grades($data, $dbrecords);
}

$data['definition'] = get_grading_definition($cm->instance);
$data['dodload'] = true;
$data['studentspan'] = count($data['profilefields']);

$data['grademethod'] = 'rubric';

$form = $OUTPUT->render_from_template('report_advancedgrading/form', $data);
$table = $OUTPUT->render_from_template('report_advancedgrading/rubric', $data);

$rows = get_rows($data);

$table .= $rows;
$table .= '   </tbody> </table> </div>';
if ($dload) {
    download($table, $data['grademethod']);
    echo $OUTPUT->header();
} else {
    $html = $form . $table;
    $PAGE->set_pagelayout('standard');
    echo $OUTPUT->header();
    echo $OUTPUT->container($html, 'advancedgrading-main');
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
        foreach ($data['students'] as $student) {
            $row = '<tr>';
            $row .= get_student_cells($data,$student);
            foreach (array_keys($data['criterion']) as $crikey) {
                $row .= '<td>' . number_format($student['grades'][$crikey]['score'], 2) . '</td>';
                $row .= '<td>' . $student['grades'][$crikey]['definition'] .'</td>';
                $row .= '<td>' . $student['grades'][$crikey]['feedback'] . '</td>';
            }
            $row .= get_summary_cells($student);
            $row .= '</tr>';
        }
    }
    return $row ?? '';
}
/**
 * Query the database for the student grades.
 *
 * @param integer $assignid
 * @return array
 */
function rubric_get_data(int $assignid) : array {
     global $DB;
     $sql = "SELECT grf.id as grfid,
                     cm.course, asg.name as assignment,
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
                JOIN {assignfeedback_comments} assign_comment on assign_comment.grade = ag.id
                JOIN {user} stu ON stu.id = ag.userid
                JOIN {user} rubm ON rubm.id = gin.raterid
                JOIN {gradingform_rubric_fillings} grf ON (grf.instanceid = gin.id)
                AND (grf.criterionid = criteria.id) AND (grf.levelid = level.id)
                WHERE cm.id = :assignid AND gin.status = 1
                AND  stu.deleted = 0
                ORDER BY lastname ASC, firstname ASC, userid ASC, criteria.sortorder ASC";

    $data = $DB->get_records_sql($sql, ['assignid' => $assignid]);
    $data = set_blindmarking($data, $assignid);
    return $data;
}
