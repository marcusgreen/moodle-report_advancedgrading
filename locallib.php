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


defined('MOODLE_INTERNAL') || die;

/**
 * Get all students given the course/module details
 * @param \context_module $modcontext
 * @param \stdClass $cm
 * @return array
 */
function report_componentgrades_get_students($modcontext, $cm) :array {
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

/**
 * Add header text to report, name of course etc

 */
function get_header($coursename, $modname, $method, $methodname) {

    // Course, assignment, marking guide / rubric names.
    $sheet = [];
    $cells = [];
    $format = ['size' => 18, 'bold' => 1];
    $cells[0] = ['row' => 0, 'col' => 24, 'range' => true];
    // $sheet->set_row(0, 24, $format);
    // $format = $workbook->add_format(array('size' => 16, 'bold' => 1));
    // $sheet->write_string(1, 0, $modname, $format);
    // $sheet->set_row(1, 21, $format);
    // $methodname = ($method == 'rubric' ? 'Rubric: ' : 'Marking guide: ') . $methodname;
    // $sheet->write_string(2, 0, $methodname, $format);
    // $sheet->set_row(2, 21, $format);

    // // Column headers - two rows for grouping.
    // $format = $workbook->add_format(array('size' => 12, 'bold' => 1));
    // $format2 = $workbook->add_format(array('bold' => 1));
    // $sheet->write_string(HEADINGSROW, 0, get_string('student', 'report_componentgrades'), $format);
    // $sheet->merge_cells(HEADINGSROW, 0, HEADINGSROW, 2, $format);
    // $col = 0;
    // $sheet->write_string(5, $col++, get_string('firstname', 'report_componentgrades'), $format2);
    // $sheet->write_string(5, $col++, get_string('lastname', 'report_componentgrades'), $format2);
    // $sheet->write_string(5, $col++, get_string('username', 'report_componentgrades'), $format2);
    // if (get_config('report_componentgrades', 'showstudentid')) {
    //     $sheet->write_string(5, $col, get_string('studentid', 'report_componentgrades'), $format2);
    //     $col++;
    // }
    // $sheet->set_column(0, $col, 10); // Set column widths to 10.
    // /* TODO returning an arbitrary number needs fixing */
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
