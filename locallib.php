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
 * Shared code for the advancedgrading (rubrice)
 * report
 *
 * Language strings to be used by report/rubrics
 *
 * @package    report_advancedgrading
 * @copyright  2021 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/assign/locallib.php');

defined('MOODLE_INTERNAL') || die;

/**
 * Get the structure of this grading definition
 *
 * @param integer $assignid
 * @return \stdClass
 */
function get_grading_definition(int $assignid) :\stdClass {
    global $DB;
    $sql = "SELECT gdef.id AS definitionid, ga.activemethod, gdef.name AS definition
              FROM {assign} assign
              JOIN {course_modules} cm ON cm.instance = assign.id
              JOIN {context} ctx ON ctx.instanceid = cm.id
              JOIN {grading_areas} ga ON ctx.id=ga.contextid
              JOIN {grading_definitions} gdef ON ga.id = gdef.areaid
             WHERE assign.id = :assignid";
    $definition = $DB->get_record_sql($sql, ['assignid' => $assignid]);
    return $definition;
}

/**
 * Get object with list of groups each user is in.
 * Credit to Dan Marsden for this idea/function
 * @param int $courseid
 */
function report_advancedgrading_get_user_groups($courseid) :array {
    global $DB;

    $sql = "SELECT gm.userid,g.id, g.name
              FROM {groups} g
              JOIN {groups_members} gm ON gm.groupid = g.id
             WHERE g.courseid = :courseid
          ORDER BY gm.userid";
    $rs = $DB->get_recordset_sql($sql, ['courseid' => $courseid]);
    foreach ($rs as $row) {
        if (!isset($groupsbyuser[$row->userid])) {
            $groupsbyuser[$row->userid] = [];
        }
        $groupsbyuser[$row->userid][] = $row->name;
    }
    $rs->close();
    return $groupsbyuser ?? [];
}
function get_criteria(string $table, int $definitionid) {
    global $DB;
    $criteria = $DB->get_records_menu($table, ['definitionid' => $definitionid], null, 'id, description');
    return $criteria;
}
/**
 * Get the descriptive header fields that detail
 * the details of the grading setup
 *
 * @param array $data
 * @param array $criteria
 * @param \stdClass $course
 * @param \cm_info $assign
 * @param \stdClass $gdef
 * @return array
 */
function header_fields(array $data, array $criteria, \stdClass $course,\cm_info $assign, \stdClass $gdef) :array{
    foreach ($criteria as $criterion) {
        $data['criteria'][] = [
            'description' => $criterion
        ];
    }

    $data['header'] = [
        'coursename' => $course->fullname,
        'assignment' => $assign->name,
        'gradingmethod' => $gdef->activemethod,
        'definition' => $gdef->definition
    ];

    $criterion = [];
    $data['studentheaders'] = "";
    foreach ($data['profilefields'] as $field) {
        $data['studentheaders'] .= "<th ".$data['headerstyle']. "><b>" . ucfirst($field) . "</b></th>";
    }
    return $data;
}

/**
 * Assemble the profile fields as configured in
 * settings and also the criterion
 *
 * @param array $data
 * @param array $dbrecords
 * @return array
 */
function user_fields(array $data, array $dbrecords) : array{
    foreach ($dbrecords as $grade) {
        $student['userid'] = $grade->userid;
        foreach ($data['profilefields'] as $field) {
            if ($field == 'groups') {
                continue;
            }
            $student[$field] = $grade->$field;
        }
        $data['students'][$grade->userid] = $student;
    }
    return $data;
}
function page_setup(array $data) : array{
    global $PAGE, $DB;
    $data['courseid'] = required_param('id', PARAM_INT); // Course ID.
    require_login($data['courseid']);
    $data['modid'] = required_param('modid', PARAM_INT); // CM ID


    $profileconfig = trim(get_config('report_advancedgrading', 'profilefields'));
    $data['profilefields'] = empty($profileconfig) ? [] : explode(',', $profileconfig);

    $modinfo = get_fast_modinfo($data['courseid']);
    $data['cm'] = $modinfo->get_cm($data['modid']);
    $data['course'] = $DB->get_record('course', array('id' => $data['courseid']), '*', MUST_EXIST);
    $data['gradingdefinition'] = get_grading_definition($data['cm']->instance);

    $urlparams['id'] = $data['courseid'];
    $urlparams['modid'] = $data['modid'];

    $url = new moodle_url('/report/advancedgrading/'.$data['gradeplugin'].'.php', $urlparams);
    $PAGE->set_url($url, $urlparams);
    $returnurl =  new moodle_url('/mod/assign/view.php',['id'=> $data['modid']]);
    $PAGE->navbar->add($data['cm']->name,$returnurl);
    $PAGE->navbar->add($data['reportname']);

    $PAGE->set_context(context_course::instance($data['courseid']));
    $PAGE->requires->js_call_amd('report_advancedgrading/table_sort', 'init');
    $PAGE->requires->jquery();
    $PAGE->set_pagelayout('report');
    $PAGE->set_title($data['reportname']);

    return $data;
}

/**
 * Assemble the grades into the data array from
 * the returned deatabase records.
 *
 * @param array $data
 * @param array $dbrecords
 * @return array
 */
function get_grades(array $data, array $dbrecords) : array{
    foreach ($dbrecords as $grade) {
        $data['criterion'][$grade->criterionid] = $grade->description;
        $g[$grade->userid][$grade->criterionid] = [
            'userid' => $grade->userid,
            'score' => number_format($grade->score,2),
            'definition' => $grade->definition ?? "",
            'feedback' => $grade->remark
        ];
        $gi = [
            'overallfeedback' => $grade->overallfeedback,
            'grader' => $grade->grader,
            'timegraded' => $grade->modified,
            'grade' => $grade->grade
        ];

        foreach ($data['students'] as $student) {
            if ($student['userid'] == $grade->userid) {
                $data['students'][$grade->userid]['grades'] = $g[$grade->userid];
                $data['students'][$grade->userid]['gradeinfo'] = $gi;
            }
        }
    }
    return $data;
}
/**
 * Add group membership to student data
 *
 * @param array $data
 * @param integer $courseid
 * @return array
 */
function add_groups(array$data, int $courseid) :array {
    $groups = report_advancedgrading_get_user_groups($courseid);
    foreach (array_keys($data['students']) as $userid) {
        if(isset($groups[$userid])) {
              $data['students'][$userid]['groups'] = implode(" ", $groups[$userid]);
        } else {
            $data['students'][$userid]['groups'] ="";
        }
    }
    return $data;
}
function get_student_cells(array $data, array $student) {
        $cell ='';
        foreach ($data['profilefields'] as $field) {
                $cell .= '<td style="'.$data['headerstyle']. '>' . $student[$field] . '</td>';
            }
    return $cell;
}
function set_blindmarking(array $data, $assign,$cm) : array {
        if($assign->is_blind_marking()){
         foreach ($data as &$user) {
            $anonymousid = get_string('participant','report_advancedgrading') .
            ' ' . \assign::get_uniqueid_for_user_static($cm->instance, $user->userid);
            $user->username = $anonymousid;
            $user->firstname = $anonymousid;
            $user->lastname = $anonymousid;
            $user->email = $anonymousid;
            $user->idnumber = $anonymousid;
        }

}
    return $data;
}
function get_summary_cells($student){
    $cell= '<td>' . $student['gradeinfo']['overallfeedback'] . '</td>';
    $cell .= '<td>' . number_format($student['gradeinfo']['grade'], 2) . '</td>';
    $cell.= '<td>' . $student['gradeinfo']['grader'] . '</td>';
    $cell .= '<td>' . \userdate($student['gradeinfo']['timegraded'], "% %d %b %Y %I:%M %p") . '</td>';
    return $cell;
}
/**
 * Download the Excel format spreadsheet
 * with the name of the grading method
 *
 * @param string $spreadsheet
 * @param string $filename
 * @return void
 */
function download(string $spreadsheet, string $filename) {
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
    $spreadsheet = $reader->loadFromString($spreadsheet);

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    output_header($filename);
    $writer->save('php://output');
    exit();
}

/**
 * Output the http header
 *
 * @param string $filename
 * @return void
 */
function output_header(string $filename) {

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    return true;
    $filename = preg_replace('/\.xlsx?$/i', '', $filename);

    $mimetype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $filename = $filename . '.xlsx';

    if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
        header('Cache-Control: max-age=10');
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header('Pragma: ');
    } else { // normal http - prevent caching at all cost
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header('Pragma: no-cache');
    }

    if (core_useragent::is_ie() || core_useragent::is_edge()) {
        $filename = rawurlencode($filename);
    } else {
        $filename = s($filename);
    }

    header('Content-Type: ' . $mimetype);
    header('Content-Disposition: attachment;filename="' . $filename . '"');
}