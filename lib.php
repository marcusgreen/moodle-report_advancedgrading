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
 *
 * Extend navigation so a link to report is displayed
 *
 * @package    report_advancedgrading
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/report/advancedgrading/locallib.php');

global $PAGE;
/**
 * Add an item to the dropdown when viewing the assignment
 *
 * @param navigation_node $navigation
 * @param cm_info $cm
 * @return void
 */
function report_advancedgrading_extend_navigation_module(navigation_node $navigation, cm_info $cm) {
    $context = $cm->context;
    $assignid = $cm->instance;

    if (
        $cm->modname == 'assign' &&
            has_capability('report/advancedgrading:view', $context) &&
            !empty(get_grading_definition($assignid))
    ) {
        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');
        switch ($gradingmanager->get_active_method()) {
            case 'rubric_ranges':
                $url = new moodle_url('/report/advancedgrading/rubric_ranges.php', ['id' => $cm->course, 'modid' => $cm->id]);
                $navigation->add(
                    get_string('rubricrangesgrades', 'report_advancedgrading'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'rubricrangesgrades'
                );
                break;
            case 'rubric':
                $url = new moodle_url('/report/advancedgrading/rubric.php', ['id' => $cm->course, 'modid' => $cm->id]);
                $navigation->add(
                    get_string('rubricgrades', 'report_advancedgrading'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'rubricgrades'
                );
                break;
            case 'rubref':
                $url = new moodle_url('/report/advancedgrading/rubref.php', ['id' => $cm->course, 'modid' => $cm->id]);
                $navigation->add(
                    get_string('rubricgrades', 'report_advancedgrading'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'rubrefgrades'
                );
                break;
            case 'guide':
                $url = new moodle_url('/report/advancedgrading/guide.php', ['id' => $cm->course, 'modid' => $cm->id]);
                $navigation->add(
                    get_string('guidegrades', 'report_advancedgrading'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'guidegrades'
                );
                break;
            case 'btec':
                $url = new moodle_url('/report/advancedgrading/btec.php', ['id' => $cm->course, 'modid' => $cm->id]);
                $navigation->add(
                    get_string('btecgrades', 'report_advancedgrading'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'btecgrades'
                );
        }
    }
}
