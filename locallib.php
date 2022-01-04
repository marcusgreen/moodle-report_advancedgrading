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

require_once($CFG->dirroot . '/mod/assign/locallib.php');


defined('MOODLE_INTERNAL') || die;

function get_grading_definition(int $assignid) {
    global $DB;
    $sql = "SELECT gdef.id as definitionid, ga.activemethod, gdef.name as definition from {assign} assign
            JOIN {course_modules} cm ON cm.instance = assign.id
            JOIN {context} ctx ON ctx.instanceid = cm.id
            JOIN {grading_areas} ga ON ctx.id=ga.contextid
            JOIN {grading_definitions} gdef ON ga.id = gdef.areaid
            WHERE assign.id = :assignid";
    $definition = $DB->get_record_sql($sql, ['assignid' => $assignid]);
    return $definition;
}

// function rubric_get_data(int $assignid) {
//     global $DB;
//     $sql = "SELECT grf.id as grfid,
//                         cm.course,
//                         asg.name as assignment,
//                         grc.description,  grl.score,  grf.remark, grf.criterionid,
//                         stu.id AS userid,
//                         stu.idnumber AS idnumber,
//                         stu.firstname, stu.lastname, stu.username,
//                         stu.username AS student,
//                         stu.email,
//                         rubm.username AS grader,
//                         gin.timemodified AS modified,
//                         ctx.instanceid, ag.grade, asg.blindmarking
//                         FROM {assign} asg
//                         JOIN {course_modules} cm ON cm.instance = asg.id
//                         JOIN {context} ctx ON ctx.instanceid = cm.id
//                         JOIN {grading_areas}  ga ON ctx.id=ga.contextid
//                         JOIN {grading_definitions} gd ON ga.id = gd.areaid
//                         JOIN {gradingform_rubric_criteria} grc ON (grc.definitionid = gd.id)
//                         JOIN {gradingform_rubric_levels} grl ON (grl.criterionid = grc.id)
//                         JOIN {grading_instances} gin ON gin.definitionid = gd.id
//                         JOIN {assign_grades} ag ON ag.id = gin.itemid
//                         JOIN {user} stu ON stu.id = ag.userid
//                         JOIN {user} rubm ON rubm.id = gin.raterid
//                         JOIN {gradingform_rubric_fillings} grf ON (grf.instanceid = gin.id)
//                          AND (grf.criterionid = grc.id) AND (grf.levelid = grl.id)
//                        WHERE cm.id = :assignid AND gin.status = 1
//                         AND  stu.deleted = 0";

//     $data = $DB->get_records_sql($sql, ['assignid' => $assignid]);
//     $firstrecord = reset($data);
//     if ($firstrecord && $firstrecord->blindmarking == 1) {
//         $modinfo = get_fast_modinfo($firstrecord->course);
//         $assign = $modinfo->get_cm($assignid);
//         $cm = get_coursemodule_from_instance('assign', $assign->instance, $firstrecord->course);
//         foreach ($data as &$user) {
//             $user->firstname = '';
//             $user->lastname = '';
//             $user->student = get_string('participant', 'assign') .
//                 ' ' . \assign::get_uniqueid_for_user_static($cm->instance, $user->userid);
//         }
//     }
//     return $data;
// }
/**
 * Get object with list of groups each user is in.
 * Credit to Dan Marsden for this idea/function
 * @param int $courseid
 */
function report_advancedgrading_get_user_groups($courseid) {
    global $DB;

    $sql = "SELECT g.id, g.name, gm.userid
              FROM {groups} g
              JOIN {groups_members} gm ON gm.groupid = g.id
             WHERE g.courseid = ?
          ORDER BY gm.userid";

    $rs = $DB->get_recordset_sql($sql, [$courseid]);
    foreach ($rs as $row) {
        if (!isset($groupsbyuser[$row->userid])) {
            $groupsbyuser[$row->userid] = [];
        }
        $groupsbyuser[$row->userid][] = $row->name;
    }
    $rs->close();
    return $groupsbyuser;
}
function get_criteria(string $table, int $definitionid) {
    global $DB;
    $criteria = $DB->get_records_menu($table, ['definitionid' => $definitionid], null, 'id, description');
    return $criteria;
}
function header_fields($data, $criteria, $course, $assign, $gdef) {
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

    $criterion = [];
    $data['studentheaders'] = "";
    foreach ($data['profilefields'] as $field) {
        $data['studentheaders'] .= "<th><b>" . ucfirst($field) . "</b></th>";
    }
    return $data;
}
function user_fields($data, $dbrecords) {
    foreach ($dbrecords as $grade) {
        $student['userid'] = $grade->userid;
        foreach ($data['profilefields'] as $key => $field) {
            if ($field == 'groups') {
                continue;
            }
            $student[$field] = $grade->$field;
        }
        $data['students'][$grade->userid] = $student;
        $data['criterion'][$grade->criterionid] = $grade->description;
    }
    return $data;
}
function get_grades($data, $dbrecords){
    foreach ($dbrecords as $grade) {
        $g[$grade->userid][$grade->criterionid] = [
            'userid' => $grade->userid,
            'score' => $grade->score,
            'feedback' => $grade->remark
        ];
        $gi = [
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
function add_groups($data, $courseid) {
    $groups = report_advancedgrading_get_user_groups($courseid);

    foreach ($data['students'] as $userid => $student) {
        $data['students'][$userid]['groups'] = implode(" ", $groups[$userid]);
    }
    return $data;
}