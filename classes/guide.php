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
 * This is the external API for this report.
 *
 * @package    report_advancedgrading
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_advancedgrading;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/form/lib.php');

/**
 * Logic to process data for assignments using the marking guide
 *
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guide {
    /**
     * Assemble the table rows for grading informationin an array from the database records returned.
     * for each student
     *
     * @param array $data
     * @return string
     */
    public function get_rows(array $data): string {
        $rows = '';
        if (isset($data['students'])) {
            foreach ($data['students'] as $student) {
                $rows .= '<tr>';
                $rows .= get_student_cells($data, $student);
                foreach (array_keys($data['criteriarecord']) as $crikey) {
                    if (isset($student['grades'][$crikey]['score'])) {
                        $rows .= '<td>' . number_format($student['grades'][$crikey]['score'], 2) . '</td>';
                        $rows .= '<td>' . $student['grades'][$crikey]['feedback'] . '</td>';
                    } else {
                        $rows .= '<td></td>';
                        $rows .= '<td></td>';
                    }
                }
                $rows .= get_summary_cells($student);
                $rows .= '</tr>';
            }
        }
        if ($rows == "") {
            $rows .= '<tr> <td>' . get_string('nomarkedsubmissions', 'report_advancedgrading') . '</td>';
            for ($i = 0; $i < $data['colcount'] - 1; $i++) {
                $rows .= '<td></td>';
            }
            $rows .= '</tr>';
        }
        return $rows;
    }

    /**
     * Assemble the table rows for grading informationin an array from the database records returned.
     * for each student
     *
     * @param \assign $assign
     * @param \cm_info $cm
     * @return array
     */
    public function get_data(\assign $assign, \cm_info $cm): array {
        global $DB;
            $sql = "SELECT fillings.id AS ggfid, cm.course AS course, asg.name AS assignment,
                    asg.grade as gradeoutof,gd.name AS guide,
                    criteria.description,criteria.shortname,
                    fillings.score, fillings.remark, fillings.criterionid, rubm.username AS graderusername,
                        rubm.firstname AS graderfirstname, rubm.lastname AS graderlastname,
                        rubm.email AS graderemail,
                    stu.id AS userid, stu.idnumber AS idnumber, stu.firstname, stu.lastname,
                    stu.username, stu.email, ag.timemodified AS modified, ag.grade,
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
            JOIN {user} rubm ON rubm.id = ag.grader
            JOIN {gradingform_guide_fillings} fillings ON (fillings.instanceid = gin.id)
             AND (fillings.criterionid = criteria.id)
           WHERE cm.id = :assignid AND gin.status = :instancestatus
             AND  stu.deleted = 0
        ORDER BY lastname ASC, firstname ASC, userid ASC, criteria.sortorder ASC";
        $data = $DB->get_records_sql($sql, ['assignid' => $cm->id,
            'instancestatus' => \gradingform_instance::INSTANCE_STATUS_ACTIVE]);
        $data = set_blindmarking($data, $assign, $cm);
        return $data;
    }
}
