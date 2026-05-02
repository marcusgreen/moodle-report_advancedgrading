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

declare(strict_types=1);

namespace report_advancedgrading\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\helpers\database;
use report_advancedgrading\reportbuilder\local\entities\grader;
use report_advancedgrading\reportbuilder\local\entities\rubric_assignment;
use report_advancedgrading\reportbuilder\local\entities\rubric_criterion;
use report_advancedgrading\reportbuilder\local\entities\student;

/**
 * Rubric grading datasource.
 *
 * Each row in this report represents one rubric criterion filling for a student submission:
 * a student × assignment × criterion combination with score, level definition, and feedback.
 *
 * To see all criteria for a student on one assignment, group by student and assignment, or
 * use the report to view a detailed per-criterion breakdown across multiple rows.
 *
 * @package    report_advancedgrading
 * @copyright  2025 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rubric_grading extends datasource {

    /**
     * Return the user-facing name of this datasource.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rubricgradingdatasource', 'report_advancedgrading');
    }

    /**
     * Initialise the datasource, setting the main table and joining all entities.
     *
     * Data model:
     *   gradingform_rubric_fillings (one row per criterion per graded submission)
     *     → grading_instances → assign_grades → assign → course
     *     → grading_definitions → grading_areas → context → course_modules
     *     → gradingform_rubric_criteria (criterion text)
     *     → gradingform_rubric_levels (level score and definition)
     *     → user stu (student)
     *     → user rubm (grader)
     *     ← assignfeedback_comments (overall feedback, optional)
     */
    protected function initialise(): void {
        // ------------------------------------------------------------------ //
        // 1. Rubric criterion entity – owns the main table.                   //
        // ------------------------------------------------------------------ //
        $criterionentity = new rubric_criterion();
        $grf      = $criterionentity->get_table_alias('gradingform_rubric_fillings');
        $criteria = $criterionentity->get_table_alias('gradingform_rubric_criteria');
        $level    = $criterionentity->get_table_alias('gradingform_rubric_levels');

        $this->set_main_table('gradingform_rubric_fillings', $grf);

        $this->add_entity($criterionentity
            ->add_join("JOIN {gradingform_rubric_criteria} {$criteria}
                           ON {$criteria}.id = {$grf}.criterionid")
            ->add_join("JOIN {gradingform_rubric_levels} {$level}
                           ON {$level}.id = {$grf}.levelid")
        );

        // ------------------------------------------------------------------ //
        // 2. Assignment grading entity – grade record, assignment, and course. //
        // ------------------------------------------------------------------ //
        $assignmententity = new rubric_assignment();
        $gin = $assignmententity->get_table_alias('grading_instances');
        $ag  = $assignmententity->get_table_alias('assign_grades');
        $gd  = $assignmententity->get_table_alias('grading_definitions');
        $ga  = $assignmententity->get_table_alias('grading_areas');
        $ctx = $assignmententity->get_table_alias('context');
        $cm  = $assignmententity->get_table_alias('course_modules');
        $asg = $assignmententity->get_table_alias('assign');
        $c   = $assignmententity->get_table_alias('course');
        $afc = $assignmententity->get_table_alias('assignfeedback_comments');

        $this->add_entity($assignmententity
            ->add_join("JOIN {grading_instances} {$gin}
                           ON {$gin}.id = {$grf}.instanceid")
            ->add_join("JOIN {assign_grades} {$ag}
                           ON {$ag}.id = {$gin}.itemid")
            ->add_join("JOIN {grading_definitions} {$gd}
                           ON {$gd}.id = {$gin}.definitionid")
            ->add_join("JOIN {grading_areas} {$ga}
                           ON {$ga}.id = {$gd}.areaid")
            ->add_join("JOIN {context} {$ctx}
                           ON {$ctx}.id = {$ga}.contextid")
            ->add_join("JOIN {course_modules} {$cm}
                           ON {$cm}.id = {$ctx}.instanceid")
            ->add_join("JOIN {assign} {$asg}
                           ON {$asg}.id = {$cm}.instance")
            ->add_join("JOIN {course} {$c}
                           ON {$c}.id = {$cm}.course")
            ->add_join("LEFT JOIN {assignfeedback_comments} {$afc}
                           ON {$afc}.grade = {$ag}.id")
        );

        // ------------------------------------------------------------------ //
        // 3. Student entity.                                                  //
        // ------------------------------------------------------------------ //
        $studententity = new student();
        $stu = $studententity->get_table_alias('user');

        $this->add_entity($studententity
            ->add_joins($assignmententity->get_joins())
            ->add_join("JOIN {user} {$stu}
                           ON {$stu}.id = {$ag}.userid")
        );

        // ------------------------------------------------------------------ //
        // 4. Grader entity (second join to user table, different alias).      //
        // ------------------------------------------------------------------ //
        $graderentity = new grader();
        $rubm = $graderentity->get_table_alias('user');

        $this->add_entity($graderentity
            ->add_joins($assignmententity->get_joins())
            ->add_join("JOIN {user} {$rubm}
                           ON {$rubm}.id = {$ag}.grader")
        );

        // ------------------------------------------------------------------ //
        // 5. Base conditions: only active grading instances, living students. //
        // ------------------------------------------------------------------ //
        // gradingform_instance::INSTANCE_STATUS_ACTIVE = 1.
        $paramstatus  = database::generate_param_name();
        $paramdeleted = database::generate_param_name();
        $this->add_base_condition_sql(
            "{$gin}.status = :{$paramstatus} AND {$stu}.deleted = :{$paramdeleted}",
            [$paramstatus => 1, $paramdeleted => 0]
        );

        // ------------------------------------------------------------------ //
        // 6. Expose all columns, filters, and conditions from every entity.  //
        // ------------------------------------------------------------------ //
        $this->add_all_from_entity($criterionentity->get_entity_name());
        $this->add_all_from_entity($assignmententity->get_entity_name());
        $this->add_all_from_entity($studententity->get_entity_name());
        $this->add_all_from_entity($graderentity->get_entity_name());
    }

    /**
     * Columns shown when a new report is created from this datasource.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'student:fullname',
            'rubric_assignment:name',
            'rubric_criterion:description',
            'rubric_criterion:score',
            'rubric_criterion:leveldefinition',
            'rubric_criterion:remark',
            'rubric_assignment:grade',
            'grader:fullname',
            'rubric_assignment:timegraded',
        ];
    }

    /**
     * Default sort order for the report.
     *
     * @return array
     */
    public function get_default_column_sorting(): array {
        return [
            'student:lastname'            => SORT_ASC,
            'student:firstname'           => SORT_ASC,
            'rubric_assignment:name'      => SORT_ASC,
            'rubric_criterion:description' => SORT_ASC,
        ];
    }

    /**
     * Filters shown by default when a new report is created.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'rubric_assignment:name',
            'rubric_assignment:coursefullname',
            'student:lastname',
        ];
    }

    /**
     * Conditions applied by default when a new report is created.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'rubric_assignment:coursefullname',
        ];
    }
}
