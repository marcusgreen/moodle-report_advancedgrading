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


$courseid  = required_param('id', PARAM_INT);// Course ID.
$assignid  = required_param('modid', PARAM_INT);// CM ID.

$params['id'] = $courseid;
$params['modid'] = $assignid;

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
// echo $OUTPUT->header();
// echo $OUTPUT->footer();


// $filename = $course->shortname . ' - ' . $assign->name . '.xls';

$gdef = get_grading_definition($assign->instance);
$headers = report_advancedgrading_get_header($course->fullname, $assign->name, $gdef->activemethod, $gdef->definition);
$cm = get_coursemodule_from_instance('assign', $assign->instance, $course->id);
$students = report_componentgrades_get_students($modcontext, $cm);
$data = rubric_get_data($cm->id);

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
//     $sheet->write_string($cell['row'], $cell['col'], $cell['value'], $header1);
// }
// $row = 5;
// $col = 0;
// $sheet->write_string($row, $col, 'Student', $header2);

// $workbook->close();

// exit;


