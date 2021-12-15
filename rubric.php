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

require(__DIR__ .'../../../config.php');
global $CFG;
require_once(__DIR__ .'/../../report/advancedgrading/locallib.php');
require_once(__DIR__ .'/../../lib/excellib.class.php');
$dload = optional_param("dload", '', PARAM_BOOL);


$courseid  = required_param('id', PARAM_INT);// Course ID.
$assignid  = required_param('modid', PARAM_INT);// CM ID.

$params['id'] = $courseid;
$params['modid'] = $assignid;

global $PAGE;
$PAGE->requires->js_call_amd('report_advancedgrading/rubric_header', 'init');

$PAGE->set_url('/report/advancedgrading/index.php', $params);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);

$modinfo = get_fast_modinfo($courseid);
$assign = $modinfo->get_cm($assignid);

$modcontext = context_module::instance($assign->id);
require_capability('mod/assign:grade', $modcontext);

$context = context_course::instance($course->id);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');


$PAGE->set_title('Rubric Report');
$PAGE->set_heading( 'Report Name');


$gdef = get_grading_definition($assign->instance);
$data = [];
$criteria = rubric_get_criteria((int) $gdef->definitionid);
$data['studentcolspan'] = 2; // Firstname and Lasname
$data['showidnumber'] = true;
if ($data['showidnumber']) {
    $data['studentcolspan']++;
}
foreach ($criteria as $key => $criterion) {
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
$cm = get_coursemodule_from_instance('assign', $assign->instance, $course->id);
$grading = rubric_get_data($assign->id);

$ids = [];
$criterion = [];
$lookup = [];
foreach ($grading as $grade) {
     $data['students'][$grade->userid] = [
        'userid'    => $grade->userid,
        'firstname' => $grade->firstname,
        'lastname' => $grade->lastname,
        'idnumber' => $grade->idnumber
     ];
     $criterion[$grade->criterionid] = $grade->description;

}
foreach ($grading as $grade) {
    $g[$grade->userid][$grade->criterionid] = [
        'userid' => $grade->userid,
        'score' => $grade->score,
        'feedback' => $grade->remark
    ];
    $gi = [
        'grader' => $grade->grader,
        'timegraded' => $grade->modified
    ];

    foreach ($data['students'] as $student) {
        if ($student['userid'] == $grade->userid) {
            $data['students'][$grade->userid]['grades'] = $g[$grade->userid];
            $data['students'][$grade->userid]['gradeinfo'] = $gi;
        }
    }
}
$data['definition'] = get_grading_definition($cm->instance);
$data['scoring'] = rubric_get_data($cm->id);
$data['id'] = 17;
$data['modid'] = 117;
$data['dodload'] = true;

$form = $OUTPUT->render_from_template('report_advancedgrading/rubric/header_form', $data);
$table = $OUTPUT->render_from_template('report_advancedgrading/rubric/header', $data);

$row = '';
foreach ($data['students'] as $key => $student) {
    $row .= '<tr>';
    $row .= '<td>'.$student['firstname'].'</td>';
    $row .= '<td>'.$student['lastname'].'</td>';
    $row .= '<td></td>';
    foreach($criterion as $crikey => $criteria) {
        $row .= '<td>'.$student['grades'][$crikey]['score'] .'</td>';
        $row .= '<td>'.$student['grades'][$crikey]['feedback'] .'</td>';
    }
    $row .= '<td>'.$student['gradeinfo']['grader'] .'</td>';
    $row .= '<td>'.$student['gradeinfo']['timegraded'] .'</td>';
    $row .= '</tr>';
}

//$table .= $row;
$table .= '    </tbody> </table>';
if ($dload) {
    download($table);
    echo $OUTPUT->header();
} else {
    echo $OUTPUT->header();

    echo $form;
    echo $table;
}

echo $OUTPUT->footer();

    // hout('mavg77');
    // $writer->save('/Users/marcusgreen/Downloads/mavg.xlsx');
    // $writer->save('php://output');
    // exit();
    // echo $OUTPUT->header();


    // $retval = $writer->save('/Users/marcusgreen/Downloads/mavg.xls');

function download($spreadsheet) {
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
    $spreadsheet = $reader->loadFromString($spreadsheet);

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    hout('rubric');
    $writer->save('php://output');
    exit();

}
function hout($filename) {

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    return true;
    $filename = preg_replace('/\.xlsx?$/i', '', $filename);

    $mimetype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $filename = $filename.'.xlsx';

    if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
        header('Cache-Control: max-age=10');
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header('Pragma: ');
    } else { // normal http - prevent caching at all cost
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header('Pragma: no-cache');
    }

    if (core_useragent::is_ie() || core_useragent::is_edge()) {
        $filename = rawurlencode($filename);
    } else {
        $filename = s($filename);
    }

    header('Content-Type: '.$mimetype);
    header('Content-Disposition: attachment;filename="'.$filename.'"');

}

    /**
     * Close the Moodle Workbook
     */
    // public function close() {
    // global $CFG;

    // foreach ($this->objspreadsheet->getAllSheets() as $sheet) {
    // $sheet->setSelectedCells('A1');
    // }
    // $this->objspreadsheet->setActiveSheetIndex(0);

    // $filename = preg_replace('/\.xlsx?$/i', '', $this->filename);

    // $mimetype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    // $filename = $filename.'.xlsx';

    // if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
    // header('Cache-Control: max-age=10');
    // header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    // header('Pragma: ');
    // } else { // normal http - prevent caching at all cost
    // header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
    // header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    // header('Pragma: no-cache');
    // }

    // if (core_useragent::is_ie() || core_useragent::is_edge()) {
    // $filename = rawurlencode($filename);
    // } else {
    // $filename = s($filename);
    // }

    // header('Content-Type: '.$mimetype);
    // header('Content-Disposition: attachment;filename="'.$filename.'"');

    // $objwriter = IOFactory::createWriter($this->objspreadsheet, $this->type);
    // $objwriter->save('php://output');
    // }



    // $workbook = new MoodleExcelWorkbook("-");
    // $workbook->send($filename);
    // $sheet = $workbook->add_worksheet($assign->name);

    // $header1 = $workbook->add_format(['size' => 12, 'bold' => 1]);
    // $header2 = $workbook->add_format(['bold' => 1]);

    // $firstrow = $firstcol = $lastrow = 0;
    // $lastcol = 3;
    // $sheet->merge_cells($firstrow, $firstcol, $lastrow, $lastcol);
    // $firstrow = $lastrow = 1;
    // $sheet->merge_cells($firstrow, $firstcol, $lastrow, $lastcol);

    // foreach ($headers as $cell) {
    // $sheet->write_string($cell['row'], $cell['col'], $cell['value'], $header1);
    // }
    // $row = 5;
    // $col = 0;
    // $sheet->write_string($row, $col, 'Student', $header2);

    // $workbook->close();

    // exit;


