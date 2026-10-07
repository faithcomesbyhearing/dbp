<?php

namespace Tests\Feature;

use App\Http\Controllers\Plan\PlansController;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for PlansController::normalizeRestartStartDate(), the DB-free `start_date` check of
 * POST /plans/{id}/restart (PBI 102554).
 *
 * Restart deletes the user's progress, so unlike Start and Reset (which store any string) it accepts only a
 * real date written YYYY-MM-DD and answers 422 for anything else before writing. A missing or "0" value never
 * reaches this method: checkParam('start_date', true) aborts first with its own 422.
 *
 * Runs without a database or the Laravel container: it only touches a static method.
 *
 * @group plans
 */
class PlanRestartTest extends TestCase
{
    public static function acceptedProvider(): array
    {
        return [
            'a date'                          => ['2026-10-10', '2026-10-10'],
            'leap day in a leap year'         => ['2028-02-29', '2028-02-29'],
            'surrounding spaces (header)'     => ['  2026-10-10 ', '2026-10-10'],
            'first day of the year'           => ['2027-01-01', '2027-01-01'],
        ];
    }

    /**
     * @dataProvider acceptedProvider
     * @test
     */
    public function realDatesInYmdFormAreAccepted(string $value, string $expected)
    {
        $this->assertSame($expected, PlansController::normalizeRestartStartDate($value));
    }

    public static function rejectedProvider(): array
    {
        return [
            'a day that does not exist'       => ['2026-02-30'],
            'leap day in a common year'       => ['2026-02-29'],
            'month 13'                        => ['2026-13-01'],
            'ISO datetime'                    => ['2026-10-10T00:00:00Z'],
            'date and time'                   => ['2026-10-10 08:00:00'],
            'US format'                       => ['10/10/2026'],
            'day first'                       => ['10-10-2026'],
            'no leading zeros'                => ['2026-1-5'],
            'blank after trimming (header)'   => ['   '],
            'words'                           => ['tomorrow'],
            'JSON number'                     => [20261010],
            'JSON true'                       => [true],
            'JSON list'                       => [['2026-10-10']],
            'JSON object'                     => [['date' => '2026-10-10']],
        ];
    }

    /**
     * The type is checked first, so a number or array is a clear 422 rather than a TypeError (HTTP 500).
     *
     * @dataProvider rejectedProvider
     * @test
     */
    public function anythingElseIsRejectedWithTheFormatMessage($value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('start_date must be a date in YYYY-MM-DD format.');
        PlansController::normalizeRestartStartDate($value);
    }
}
