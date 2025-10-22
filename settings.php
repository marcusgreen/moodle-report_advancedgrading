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
 * Data to control defaults when creating and running a question
 *
 * @package    report_advancedgrading
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $rawchoices = [
        'username',
        'firstname',
        'lastname',
        'idnumber',
        'email',
        'groups',
    ];
    $choices = [];
    foreach ($rawchoices as $choice) {
        $choices[$choice] = new lang_string($choice);
    }

    $settings->add(new admin_setting_configmulticheckbox(
        'report_advancedgrading/profilefields',
        new lang_string('profilefields', 'report_advancedgrading'),
        new lang_string('profilefields_desc', 'report_advancedgrading'),
        ['username' => 1],
        $choices
    ));

    $rawchoicesgradeby = [
            'graderusername',
            'graderfirstname',
            'graderlastname',
            'graderemail',
    ];
    $choices = [];
    foreach ($rawchoicesgradeby as $choice) {
        $choices[$choice] = new lang_string($choice, 'report_advancedgrading');
    }

    $settings->add(new admin_setting_configmulticheckbox(
        'report_advancedgrading/profilefieldsgradeby',
        new lang_string('profilefieldsgradeby', 'report_advancedgrading'),
        new lang_string('profilefieldsgradeby_desc', 'report_advancedgrading'),
        ['graderusername' => 1],
        $choices
    ));
}
