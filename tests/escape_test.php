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
 * Tests that grading data is escaped in report rows (REPORT_ADVANCEDGRADING-SEC-001).
 *
 * @package    report_advancedgrading
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
namespace report_advancedgrading;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/report/advancedgrading/locallib.php');

/**
 * Tests for escaping of report table cells.
 *
 * @package    report_advancedgrading
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('get_student_cells')]
#[\PHPUnit\Framework\Attributes\CoversFunction('get_summary_cells')]
#[\PHPUnit\Framework\Attributes\CoversClass(guide::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rubric::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rubref::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rubric_ranges::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(btec::class)]
final class escape_test extends \advanced_testcase {
    /** @var string markup that must never reach the page as live HTML */
    const PAYLOAD = '<img src=x onerror=alert(1)>MARK<script>alert(2)</script>';

    /**
     * Build report data with the payload in every user-controlled field.
     *
     * @return array
     */
    private function get_report_data(): array {
        $student = [
            'userid' => 1,
            'username' => self::PAYLOAD,
            'grades' => [
                7 => ['score' => 1.0, 'definition' => self::PAYLOAD, 'feedback' => self::PAYLOAD],
            ],
            'gradeinfo' => [
                'overallfeedback' => '<b>BOLD</b>' . self::PAYLOAD,
                'overallfeedbackformat' => FORMAT_HTML,
                'grade' => self::PAYLOAD,
                'grader' => self::PAYLOAD,
                'timegraded' => time(),
            ],
        ];
        return [
            'students' => [1 => $student],
            'profilefields' => ['username'],
            'criterion' => [7 => 'Criterion'],
            'criteriarecord' => [7 => 'Criterion'],
            'context' => \context_system::instance(),
            'colcount' => 1,
        ];
    }

    /**
     * Payloads in feedback, definitions, overall feedback, grade, grader and
     * profile fields are not output as markup by any grading method.
     *
     * @return void
     */
    public function test_rows_escape_markup(): void {
        $this->resetAfterTest();
        $data = $this->get_report_data();
        $classes = [guide::class, rubric::class, rubref::class, rubric_ranges::class, btec::class];
        foreach ($classes as $class) {
            $html = (new $class())->get_rows($data);
            $this->assertStringNotContainsStringIgnoringCase('<script', $html, $class);
            $this->assertDoesNotMatchRegularExpression('/<[^>]*\bonerror\s*=/i', $html, $class);
            $this->assertStringContainsString('&lt;script&gt;', $html, $class);
            // Overall feedback is editor HTML, so safe formatting is kept.
            $this->assertStringContainsString('<b>BOLD</b>', $html, $class);
        }
    }
}
