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
 * Tests for handling of cell content that looks like a formula.
 *
 * @package    report_advancedgrading
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
namespace report_advancedgrading;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/report/advancedgrading/locallib.php');

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Html;

/**
 * Tests for report_advancedgrading_neutralise_formulas().
 *
 * @package    report_advancedgrading
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('report_advancedgrading_neutralise_formulas')]
final class formula_test extends \advanced_testcase {
    /**
     * Build a spreadsheet the same way download() does.
     *
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    private function load_sheet(): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $html = '<table><tr><td>=Good. Needs more detail</td><td>=HYPERLINK("http://example.com","x")</td>'
            . '<td>5</td><td>Good work</td></tr></table>';
        $spreadsheet = (new Html())->loadFromString($html);
        report_advancedgrading_neutralise_formulas($spreadsheet);
        return $spreadsheet;
    }

    /**
     * Content starting with "=" is stored as text, other cells unchanged.
     */
    public function test_formulas_become_text(): void {
        $sheet = $this->load_sheet()->getActiveSheet();

        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A1')->getDataType());
        $this->assertSame('=Good. Needs more detail', $sheet->getCell('A1')->getValue());
        $this->assertTrue($sheet->getCell('A1')->getStyle()->getQuotePrefix());

        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B1')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('C1')->getDataType());
        $this->assertSame('Good work', $sheet->getCell('D1')->getValue());
    }

    /**
     * Writing the sheet no longer throws a formula error (issue #53).
     */
    public function test_export_does_not_throw(): void {
        $spreadsheet = $this->load_sheet();
        foreach (['Xlsx', 'Csv'] as $filetype) {
            $writer = IOFactory::createWriter($spreadsheet, $filetype);
            ob_start();
            $writer->save('php://output');
            $output = ob_get_clean();
            $this->assertNotEmpty($output);
        }
    }
}
