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
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Rubric criterion entity: represents one criterion/level/filling row of rubric grading data.
 *
 * @package    report_advancedgrading
 * @copyright  2025 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rubric_criterion extends base {

    /**
     * Database tables used by this entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'gradingform_rubric_fillings',
            'gradingform_rubric_criteria',
            'gradingform_rubric_levels',
        ];
    }

    /**
     * Default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('rubriccriterion', 'report_advancedgrading');
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
        $criteria = $this->get_table_alias('gradingform_rubric_criteria');
        $level    = $this->get_table_alias('gradingform_rubric_levels');
        $grf      = $this->get_table_alias('gradingform_rubric_fillings');

        $columns = [];

        // Criterion description.
        $columns[] = (new column(
            'description',
            new lang_string('description'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$criteria}.description")
            ->set_is_sortable(true);

        // Level score (the score value of the selected rubric level).
        $columns[] = (new column(
            'score',
            new lang_string('score', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$level}.score")
            ->set_is_sortable(true)
            ->add_callback(static function(?float $value): string {
                if ($value === null) {
                    return '';
                }
                return format_float($value, 2);
            });

        // Level definition (the text description of the selected level).
        $columns[] = (new column(
            'leveldefinition',
            new lang_string('definition', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$level}.definition")
            ->set_is_sortable(false);

        // Per-criterion remark / feedback.
        $columns[] = (new column(
            'remark',
            new lang_string('feedback', 'report_advancedgrading'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$grf}.remark")
            ->set_is_sortable(false);

        return $columns;
    }

    /**
     * Return all filters available for this entity.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $criteria = $this->get_table_alias('gradingform_rubric_criteria');
        $level    = $this->get_table_alias('gradingform_rubric_levels');

        $filters = [];

        $filters[] = (new filter(
            text::class,
            'description',
            new lang_string('description'),
            $this->get_entity_name(),
            "{$criteria}.description"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'score',
            new lang_string('score', 'report_advancedgrading'),
            $this->get_entity_name(),
            "{$level}.score"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
