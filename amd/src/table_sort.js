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
 * JavaScript report_advancedgrading
 *
 * @copyright  2021 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//  */
// import {DataTable} from 'report_advancedgrading/sdt/datatable';

// export const init = () => {
//     const dataTable = new DataTable("#rubric-header", {
//         sortable: true,
//         paging: true,
//         // searchable: true
//     } );
// };

import 'report_advancedgrading/datatables';
// import 'report_advancedgrading/dataTables.fixedColumns';

export const init = () => {
        var table =  $("#rubric-header").DataTable({
            paging: true,
            fixedColumns:   {
                left: 2
            }
        });
};