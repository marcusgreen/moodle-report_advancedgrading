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
use stdClass;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Student entity: basic user fields for the student who submitted the assignment.
 *
 * @package    report_advancedgrading
 * @copyright  2025 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student extends base {

    /**
     * Database tables used by this entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['user'];
    }

    /**
     * Default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('student', 'report_advancedgrading');
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
        $u = $this->get_table_alias('user');

        $columns = [];

        $columns[] = (new column(
            'firstname',
            new lang_string('firstname'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$u}.firstname")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'lastname',
            new lang_string('lastname'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$u}.lastname")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'fullname',
            new lang_string('fullname'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$u}.firstname")
            ->add_field("{$u}.lastname")
            ->add_field("{$u}.firstnamephonetic")
            ->add_field("{$u}.lastnamephonetic")
            ->add_field("{$u}.middlename")
            ->add_field("{$u}.alternatename")
            ->set_is_sortable(true, ["{$u}.lastname", "{$u}.firstname"])
            ->add_callback(static function(?string $value, stdClass $row): string {
                return fullname($row);
            });

        $columns[] = (new column(
            'username',
            new lang_string('username'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$u}.username")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'email',
            new lang_string('email'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$u}.email")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'idnumber',
            new lang_string('idnumber'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$u}.idnumber")
            ->set_is_sortable(true);

        return $columns;
    }

    /**
     * Return all filters available for this entity.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $u = $this->get_table_alias('user');

        $filters = [];

        $filters[] = (new filter(
            text::class,
            'firstname',
            new lang_string('firstname'),
            $this->get_entity_name(),
            "{$u}.firstname"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'lastname',
            new lang_string('lastname'),
            $this->get_entity_name(),
            "{$u}.lastname"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'username',
            new lang_string('username'),
            $this->get_entity_name(),
            "{$u}.username"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'email',
            new lang_string('email'),
            $this->get_entity_name(),
            "{$u}.email"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'idnumber',
            new lang_string('idnumber'),
            $this->get_entity_name(),
            "{$u}.idnumber"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
