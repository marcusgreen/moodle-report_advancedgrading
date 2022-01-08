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
$PAGE->set_url(new moodle_url('/report/advancedgrading/index.php', $data));

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);

$modinfo = get_fast_modinfo($courseid);
$assign = $modinfo->get_cm($data['modid']);

$modcontext = context_module::instance($assign->id);
require_capability('mod/assign:grade', $modcontext);

$context = context_course::instance($course->id);

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$renderer = $PAGE->get_renderer('core_user');

$PAGE->set_title('Marking Guide Report');
$PAGE->set_heading('Report Name');

// Profile fields.
$profileconfig = trim(get_config('report_advancedgrading', 'profilefields'));
$data['profilefields'] = empty($profileconfig) ? [] : explode(',', $profileconfig);

$gdef = get_grading_definition($assign->instance);
$cm = get_coursemodule_from_instance('assign', $assign->instance, $course->id);
$criteria = get_criteria('gradingform_guide_criteria', (int) $gdef->definitionid);

$data['silverbackground'] = "background-color:#D2D2D2;'";
$data = header_fields($data, $criteria, $course, $assign, $gdef);
$dbrecords = guide_get_data($assign);

$data = user_fields($data, $dbrecords);
$data = add_groups($data, $courseid);
$data = get_grades($data, $dbrecords);

$data['definition'] = get_grading_definition($cm->instance);
$data['dodload'] = true;
$data['studentspan'] = count($data['profilefields']);
$data['grademethod'] =  'guide';

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
    $PAGE->set_pagelayout('standard');
    echo $OUTPUT->header();
    echo $OUTPUT->container($html, 'advancedgrading-main');
}
echo $OUTPUT->footer();

/**
 * Assemble the table rows for grading informationin an array from the database records returned.
 * for eCh student
 *
 * @param array $data
 * @return string
 */
function guide_get_data($cm) :array{
        global $DB;
        $sql = "SELECT ggf.id AS ggfid, crs.shortname AS course, asg.name AS assignment, gd.name AS guide,
                ggc.description,
                ggc.shortname, ggf.score, ggf.remark, ggf.criterionid, rubm.username AS grader,
                stu.id AS userid, stu.idnumber AS idnumber, stu.firstname, stu.lastname,
                stu.username AS student, gin.timemodified AS modified, ag.grade,
                assign_comment.commenttext as overallfeedback
                FROM {course} crs
                JOIN {course_modules} cm ON crs.id = cm.course
                JOIN {assign} asg ON asg.id = cm.instance
                JOIN {context} c ON cm.id = c.instanceid
                JOIN {grading_areas} ga ON c.id=ga.contextid
                JOIN {grading_definitions} gd ON ga.id = gd.areaid
                JOIN {gradingform_guide_criteria} ggc ON (ggc.definitionid = gd.id)
                JOIN {grading_instances} gin ON gin.definitionid = gd.id
                JOIN {assign_grades} ag ON ag.id = gin.itemid
                JOIN {assignfeedback_comments} assign_comment on assign_comment.grade = ag.id
                JOIN {user} stu ON stu.id = ag.userid
                JOIN {user} rubm ON rubm.id = gin.raterid
                JOIN {gradingform_guide_fillings} ggf ON (ggf.instanceid = gin.id)
                 AND (ggf.criterionid = ggc.id)
               WHERE cm.id = ? AND gin.status = 1
                 AND stu.deleted = 0
            ORDER BY lastname ASC, firstname ASC,
                     userid ASC,
                     ggc.sortorder ASC,
                     ggc.shortname ASC";
        $data = $DB->get_records_sql($sql,[$cm->id]);
        return $data;
}

/**
 * Assemble the table rows for grading informationin an array from the database records returned.
 * for eCh student
 *
 * @param array $data
 * @return string
 */
function get_rows(array $data): string {
    $row = '';
    $criterion = $data['criterion'];
    if ($data['students']) {
        foreach ($data['students'] as $student) {
            $row .= '<tr>';
            foreach ($data['profilefields'] as $field) {
                $row .= '<td>' . $student[$field] . '</td>';
            }
            foreach (array_keys($criterion) as $crikey) {
                $row .= '<td>' . number_format($student['grades'][$crikey]['score'], 2) . '</td>';
                $row .= '<td>' . $student['grades'][$crikey]['feedback'] . '</td>';
            }
            $row .= '<td>' . $student['gradeinfo']['overallfeedback'] . '</td>';
            $row .= '<td>' . number_format($student['gradeinfo']['grade'], 2) . '</td>';
            $row .= '<td>' . $student['gradeinfo']['grader'] . '</td>';
            $row .= '<td>' . \userdate($student['gradeinfo']['timegraded'], "% %d %b %Y %I:%M %p") . '</td>';
            $row .= '</tr>';
        }
    }
    return $row;
}