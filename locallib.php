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
 * Shared code for the advancedgrading methods
 *
 *
 * @package    report_advancedgrading
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

defined('MOODLE_INTERNAL') || die;

/**
 * Get the structure of this grading definition
 * This function  should be renamed or moved into a class something like
 * report_advancedgrading\lib to avoid name clashes in the future.
 *
 * @param int $assignid
 * @return false|\stdClass
 */
function get_grading_definition(int $assignid) {
    global $DB;
    $sql = "SELECT gdef.id AS definitionid, ga.activemethod, gdef.name AS definition
              FROM {assign} assign
              JOIN {course_modules} cm ON cm.instance = assign.id
              JOIN {context} ctx ON ctx.instanceid = cm.id
              JOIN {grading_areas} ga ON ctx.id = ga.contextid
              JOIN {grading_definitions} gdef ON (ga.id = gdef.areaid AND ga.activemethod = gdef.method)
             WHERE assign.id = :assignid AND gdef.method = ga.activemethod";
    $definition = $DB->get_record_sql($sql, ['assignid' => $assignid]);
    return $definition;
}

/**
 * Get object with list of groups each user is in.
 * Credit to Dan Marsden for this idea/function
 * @param int $courseid
 */
function report_advancedgrading_get_user_groups($courseid): array {
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
function header_fields(array $data, array $criteria, \stdClass $course, \cm_info $assign, \stdClass $gdef): array {
    foreach ($criteria as $criterion) {
        $data['criteria'][] = [
            'description' => $criterion,
        ];
    }

    $data['header'] = [
        'coursename' => format_string($course->fullname),
        'assignment' => format_string($assign->name),
        'gradingmethod' => get_string('pluginname', 'gradingform_' . $gdef->activemethod),
        'definition' => $gdef->definition,
    ];

    $criterion = [];
    $data['studentheaders'] = "";
    foreach ($data['profilefields'] as $field) {
            $data['studentheaders'] .= "<th " . $data['headerstyle'] . "><b>" . get_string($field) . "</b></th>";
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
function user_fields(array $data, array $dbrecords): array {
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

/**
 * Send output either to the browser or
 * to a file download
 *
 * @param string $form
 * @param string $dload
 * @param array $data
 * @param string $page
 * @return void
 */
function send_output(string $form, string $dload, array $data, string $page): void {
    global $OUTPUT, $PAGE;
    $PAGE->set_cm($data['cm']);
    if ($dload) {
        download($page, $data);
        echo $OUTPUT->header();
    } else {
        $html = $form . $page;
        $PAGE->set_pagelayout('report');
        echo $OUTPUT->header();
        echo $OUTPUT->container($html, 'advancedgrading-main');
    }
    echo $OUTPUT->footer();
}
/**
 * Initialise stuff common to all grading methods
 *
 * @param array $data
 * @return array
 */
function init(array $data): array {
    global $PAGE, $DB;

    $profileconfig = trim(get_config('report_advancedgrading', 'profilefields'));

    $data['courseidvalue'] = 'value=' . $data['courseid'];
    $data['profilefields'] = empty($profileconfig) ? [] : explode(',', $profileconfig);
    $data['studentspan'] = count($data['profilefields']);

    $data['colcount'] = count($data['profilefields']);
    $data['colcount'] += 4; // Always 4 cols in the summary.

    $modinfo = get_fast_modinfo($data['courseid']);
    $data['cm'] = $modinfo->get_cm($data['modid']);
    $data['course'] = $DB->get_record('course', ['id' => $data['courseid']], '*', MUST_EXIST);
    $data['gradingdefinition'] = get_grading_definition($data['cm']->instance);

    $data['context'] = \context_module::instance($data['cm']->id);
    $data['assign'] = new assign($data['context'], $data['cm'], $data['cm']->get_course());

    if ($data['grademethod'] == 'rubric_ranges') {
        $criteriatable = 'gradingform_' . $data['grademethod'] . '_c';
    } else {
        $criteriatable = 'gradingform_' . $data['grademethod'] . '_criteria';
    }
    $data['criteriarecord'] = $DB->get_records_menu(
        $criteriatable,
        ['definitionid' => (int) $data['gradingdefinition']->definitionid],
        'sortorder',
        'id, description'
    );
    $data = header_fields($data, $data['criteriarecord'], $data['course'], $data['cm'], $data['gradingdefinition']);
    $data['definition'] = get_grading_definition($data['cm']->instance);
    $data['formaction'] = 'action=' . $data['grademethod'] . '.php?id=' . $data['courseid'] . '&modid=' . $data['modid'];
    // Summary always has 4 columns.
    $data['summaryspan'] = 4;
    // TODO check if headerspanis actually used.
    $data['headerspan'] = $data['colcount'];

    $event = \report_advancedgrading\event\report_viewed::create([
        'context' => $data['context'],
        'other' => [
            'gradingmethod' => $data['grademethod'],
        ],
    ]);
    $event->add_record_snapshot('course_modules', $data['cm']);
    $event->trigger();

    $urlparams['id'] = $data['courseid'];
    $urlparams['modid'] = $data['modid'];
    $url = new moodle_url('/report/advancedgrading/' . $data['grademethod'] . '.php', $urlparams);

    $PAGE->set_url($url, $urlparams);
    $returnurl = new moodle_url('/mod/assign/view.php', ['id' => $data['cm']->id]);
    $PAGE->navbar->add($data['cm']->name, $returnurl);
    $PAGE->navbar->add($data['reportname']);

    $PAGE->set_context($data['context']);

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
function get_grades(array $data, array $dbrecords): array {
    global $DB;
    $gradeoutof = reset($dbrecords)->gradeoutof;
    if ($gradeoutof < 0) {
        $scale = $DB->get_record('scale', ['id' => - ($gradeoutof)], 'scale');
        $scaleoptions = make_menu_from_list($scale->scale);
    }

    foreach ($dbrecords as $grade) {
        $data['criterion'][$grade->criterionid] = $grade->description;
        $g[$grade->userid][$grade->criterionid] = [
            'userid' => $grade->userid,
            'score' => number_format($grade->grade_rub_range ?? $grade->score, 2),
            'definition' => $grade->definition ?? "",
            'feedback' => $grade->remark,
        ];
        if (isset($scaleoptions)) {
            $formattedgrade = $scaleoptions[round($grade->grade)] ?? $scaleoptions[1];
        } else {
            $formattedgrade = number_format($grade->grade, 2);
        }

        $gi = [
            'overallfeedback' => $grade->overallfeedback,
            'grader' => gradedbydata($grade),
            'timegraded' => $grade->modified,
            'grade' => $formattedgrade,
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
 * @param int $courseid
 * @return array
 */
function add_groups(array $data, int $courseid): array {
    $groups = report_advancedgrading_get_user_groups($courseid);
    foreach (array_keys($data['students']) as $userid) {
        if (isset($groups[$userid])) {
            $data['students'][$userid]['groups'] = implode(" ", $groups[$userid]);
        } else {
            $data['students'][$userid]['groups'] = "";
        }
    }
    return $data;
}
/**
 * Get student data for each
 * student column
 *
 * @param array $data
 * @param array $student
 * @return string
 */
function get_student_cells(array $data, array $student): string {
    $cell = '';
    foreach ($data['profilefields'] as $field) {
        $cell .= '<td>' . $student[$field] . '</td>';
    }
    return $cell;
}
/**
 * Obscure the identify of students when blind marking
 * is enabled. These identities will match those shown
 * in gthe gradebook.
 *
 * @param array $data
 * @param [type] $assign
 * @param [type] $cm
 * @return array
 */
function set_blindmarking(array $data, $assign, $cm): array {
    if ($assign->is_blind_marking()) {
        foreach ($data as &$user) {
            $anonymousid = get_string('participant', 'report_advancedgrading') .
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
/**
 * Columns shown at the end of the tabale summarisng
 * The marking and who did it.
 * @param mixed $student
 * @return string
 */
function get_summary_cells($student): string {
    $cell = '<td>' . $student['gradeinfo']['overallfeedback'] . '</td>';
    $cell .= '<td>' . $student['gradeinfo']['grade'] . '</td>';
    $cell .= '<td>' . $student['gradeinfo']['grader'] . '</td>';
    $cell .= '<td>' . \userdate($student['gradeinfo']['timegraded'], "%d %b %Y %I:%M %p") . '</td>';
    return $cell;
}
/**
 * Download the formatted spreadsheet or
 * CSV (comma separated values) file.
 * The file name is the Course shortame with a dash
 * Then the activity name with any spaces removed.
 *
 * @param string $spreadsheet
 * @param array $data
 * @return void
 */
function download(string $spreadsheet, array $data) {
    $spreadsheet = preg_replace('/<(\s*)img[^<>]*>/i', '', $spreadsheet);

    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
    $spreadsheet = $reader->loadFromString($spreadsheet);
    $csvdownload = optional_param('csvdownload', '', PARAM_TEXT);
    $viewsubmissions = optional_param('viewsubmissions', '', PARAM_TEXT);

    if ($viewsubmissions > "") {
        $params = [
            'id' => $data['modid'],
            'action' => 'grading',
        ];
        redirect(new moodle_url("/mod/assign/view.php?", $params));
    }
    $coursename = str_replace(' ', '_', $data['course']->fullname);
    $assignmentname = str_replace(' ', '_', $data['cm']->name);
    $filename = $coursename . '-' . $assignmentname;

    if ($csvdownload > "") {
        $filetype = 'Csv';
    } else {
        $filetype = 'Xlsx';
    }
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, $filetype);
    if ($filetype == 'Xlsx') {
        $sheet = $writer->getSpreadsheet()->getActiveSheet();
        $colcount = $data['colcount'];
        $colcount = $data['colcount'];

        // Generate the alphabet based on the column quantity.
        $alphabet = [];
        for ($l = 'A', $i = 0; $i <= $colcount + 1; $l++, $i++) {
            $alphabet[] = $l;
        }

        $lastcol = $alphabet[$colcount + 1];
        // Merge the header cells containing metadata like course name etc.
        $sheet->mergeCells('A1:' . $lastcol . '1');
        $sheet->mergeCells('A2:' . $lastcol . '2');
        $sheet->mergeCells('A3:' . $lastcol . '3');
        $sheet->mergeCells('A4:' . $lastcol . '4');

        $color = new Color();
        $color->setRGB('CDCDCD');
        $color2 = new Color();
        $color2->setRGB('D3D3D3');
        $sheet->getStyle('A6:' . $lastcol . '7')->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR);
        $sheet->getStyle('A6:' . $lastcol . '7')->getFill()->setStartColor($color);
        $sheet->getStyle('A6:' . $lastcol . '7')->getFill()->setEndColor($color2);
        $sheet->getColumnDimension($lastcol)->setAutoSize(true);

        // Spreadsheet titles are limited to 31 characters.
        $title = substr($assignmentname, 0, 30);
        $sheet->setTitle($title);
    }
    output_header($filename, $filetype);
    $writer->save('php://output');
    exit();
}

/**
 * Output the http header
 *
 * @param string $filename
 * @param string $filetype
 * @return boolean
 */
function output_header(string $filename, string $filetype): bool {
    if ($filetype == 'Xlsx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    } else {
        header('Content-Type: application/text/csv');
    }
    $filetype = strtolower($filetype);
    $filename = $filename . "." . $filetype;
    header('Content-Disposition: attachment;filename="' . $filename);
    return true;
}

/**
 * Return the data for the graded by column following the config.
 *
 * @param array $gradedata
 * @return string The corresponding data from the settings.
 */
function gradedbydata($gradedata): string {
    $graderprofileconfig = trim(get_config('report_advancedgrading', 'profilefieldsgradeby'));
    $gradedbyfields = empty($graderprofileconfig) ? [] : explode(',', $graderprofileconfig);
    $fielddata = [];
    foreach ($gradedbyfields as $field) {
        $fielddata[] = $gradedata->$field;
    }
    return implode(' - ', $fielddata);
}
