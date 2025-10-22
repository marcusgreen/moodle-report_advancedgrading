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
 * Logic to process data for assignments using the rubric grading ethod
 *
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class btec {
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
                foreach (array_keys($data['criterion']) as $crikey) {
                    $rows .= '<td>' . $student['grades'][$crikey]['score'] . '</td>';
                    $rows .= '<td>' . $student['grades'][$crikey]['definition'] . '</td>';
                    $rows .= '<td>' . $student['grades'][$crikey]['feedback'] . '</td>';
                }
                $rows .= get_summary_cells($student);
                $rows .= '</tr>';
            }
        }
        if ($rows == "") {
            $rows .= '<tr> <td>' . get_string('nomarkedsubmissions', 'report_advancedgrading') . '</td>';
            for ($i = 0; $i < $data['colcount'] + 2; $i++) {
                $rows .= '<td></td>';
            }
            $rows .= '</tr>';
        }
        return $rows;
    }
    /**
     * Query the database for the student grades.
     *
     * @param \cm_info $cm
     * @return array
     */
    public function get_data(\cm_info $cm): array {
        global $DB;
        $sql = "SELECT gbf.id AS ggfid, crs.shortname AS course, asg.name AS assignment, asg.grade as gradeoutof, gd.name AS btec,
                                        criteria.shortname, criteria.description as definition, criteria.description , gbf.score,
                                        gbf.remark, gbf.criterionid, marker.username AS graderusername,
                                        marker.firstname AS graderfirstname, marker.lastname AS graderlastname,
                                        marker.email AS graderemail,
                                        stu.id AS userid, stu.idnumber AS idnumber, stu.firstname, stu.lastname,
                                        stu.username AS username, gin.timemodified AS modified,ag.id, ag.grade,
                                        assign_comment.commenttext as overallfeedback
                                FROM {course} crs
                                JOIN {course_modules} cm ON crs.id = cm.course
                                JOIN {assign} asg ON asg.id = cm.instance
                                JOIN {context} c ON cm.id = c.instanceid
                                JOIN {grading_areas} ga ON c.id=ga.contextid
                                JOIN {grading_definitions} gd ON ga.id = gd.areaid
                                JOIN {gradingform_btec_criteria}  criteria ON (criteria.definitionid = gd.id)
                                JOIN {grading_instances} gin ON gin.definitionid = gd.id
                                JOIN {assign_grades} ag ON ag.id = gin.itemid
                                JOIN {assignfeedback_comments} assign_comment on ag.id=assign_comment.grade
                                JOIN {user} stu ON stu.id = ag.userid
                                JOIN {user} marker ON marker.id = ag.grader
                                JOIN {gradingform_btec_fillings} gbf ON (gbf.instanceid = gin.id)
                                 AND (gbf.criterionid = criteria.id)
                               WHERE cm.id = :cmid AND gin.status = :instancestatus
                            ORDER BY lastname ASC, firstname ASC, userid ASC, criteria.sortorder ASC,
                                criteria.shortname ASC";

        $data = $DB->get_records_sql($sql, ['cmid' => $cm->id, 'instancestatus' => \gradingform_instance::INSTANCE_STATUS_ACTIVE]);
        return $data;
    }
}
