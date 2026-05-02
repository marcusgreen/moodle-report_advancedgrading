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

namespace report_advancedgrading\reportbuilder\local\entities;

use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Assignment grading entity: covers assignment metadata, grades, course, and grading definition.
 *
 * @package    report_advancedgrading
 * @copyright  2025 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rubric_assignment extends base {

    /**
     * Database tables used by this entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'assign',
            'assign_grades',
            'assignfeedback_comments',
            'grading_instances',
            'grading_definitions',
            'grading_areas',
            'context',
            'course_modules',
            'course',
        ];
    }

    /**
     * Default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('assignmentgrading', 'report_advancedgrading');
    }

    /**
     * Initialise the entity columns and filters.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }
        foreach ($this->get_all_filters() as $filter) {
            $this->add_filter($filter)->add_condition($filter);
        }
        return $this;
    }

    /**
     * Return all columns available for this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $asg = $this->get_table_alias('assign');
        $ag  = $this->get_table_alias('assign_grades');
        $afc = $this->get_table_alias('assignfeedback_comments');
        $gd  = $this->get_table_alias('grading_definitions');
        $c   = $this->get_table_alias('course');

        $columns = [];

        // Assignment name.
        $columns[] = (new column(
            'name',
            new lang_string('assignment', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$asg}.name")
            ->set_is_sortable(true);

        // Overall grade awarded.
        $columns[] = (new column(
            'grade',
            new lang_string('grade', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$ag}.grade")
            ->set_is_sortable(true)
            ->add_callback(static function(?float $value): string {
                if ($value === null) {
                    return '';
                }
                return format_float($value, 2);
            });

        // Maximum grade the assignment is out of.
        $columns[] = (new column(
            'gradeoutof',
            new lang_string('gradeoutof', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$asg}.grade")
            ->set_is_sortable(true)
            ->add_callback(static function(?float $value): string {
                if ($value === null || $value < 0) {
                    return '';
                }
                return format_float($value, 2);
            });

        // Time the submission was graded.
        $columns[] = (new column(
            'timegraded',
            new lang_string('timegraded', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$ag}.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Overall feedback comment for the submission.
        $columns[] = (new column(
            'overallfeedback',
            new lang_string('overall_feedback', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$afc}.commenttext")
            ->set_is_sortable(false);

        // Name of the grading definition used.
        $columns[] = (new column(
            'gradingdefinition',
            new lang_string('gradingdefinition', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$gd}.name")
            ->set_is_sortable(true);

        // Course full name.
        $columns[] = (new column(
            'coursefullname',
            new lang_string('coursefullname', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$c}.fullname")
            ->set_is_sortable(true);

        // Course short name.
        $columns[] = (new column(
            'courseshortname',
            new lang_string('courseshortname', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$c}.shortname")
            ->set_is_sortable(true);

        return $columns;
    }

    /**
     * Return all filters available for this entity.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $asg = $this->get_table_alias('assign');
        $ag  = $this->get_table_alias('assign_grades');
        $c   = $this->get_table_alias('course');

        $filters = [];

        $filters[] = (new filter(
            text::class,
            'name',
            new lang_string('assignment', 'report_advancedgrading'),
            $this->get_entity_name(),
            "{$asg}.name"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timegraded',
            new lang_string('timegraded', 'report_advancedgrading'),
            $this->get_entity_name(),
            "{$ag}.timemodified"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'grade',
            new lang_string('grade', 'report_advancedgrading'),
            $this->get_entity_name(),
            "{$ag}.grade"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'coursefullname',
            new lang_string('coursefullname', 'report_advancedgrading'),
            $this->get_entity_name(),
            "{$c}.fullname"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'courseshortname',
            new lang_string('courseshortname', 'report_advancedgrading'),
            $this->get_entity_name(),
            "{$c}.shortname"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
