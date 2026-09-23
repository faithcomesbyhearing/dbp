<?php

namespace Tests\Feature;

use App\Models\Plan\UserPlan;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for UserPlan::resolveStatus(), the pure mapping behind the optional
 * `user_status` key on GET /api/plans?user_status=true.
 *
 * The status is derived from exact playlist item counts rather than from the stored
 * user_plans.percentage_completed, because that column is an integer and rounds on write:
 * one completed day of a 365-day plan is stored as 0 and 364 of 365 as 100. The cases below
 * lock in both ends of that scale as in_progress, so the Discover page never shows
 * "Not started" for a plan the user has touched or "Completed" for one they have not finished.
 *
 * Runs without a database or the Laravel container: it only touches a static method.
 *
 * @group plans
 */
class UserPlanStatusTest extends TestCase
{
    public static function statusProvider(): array
    {
        return [
            'no items, nothing completed'                       => [0, 0, UserPlan::STATUS_NOT_STARTED],
            'items, nothing completed'                          => [10, 0, UserPlan::STATUS_NOT_STARTED],
            'three-year plan, nothing completed'                => [1095, 0, UserPlan::STATUS_NOT_STARTED],
            'one item of a three-year plan (rounds to 0%)'      => [1095, 1, UserPlan::STATUS_IN_PROGRESS],
            'half way'                                          => [10, 5, UserPlan::STATUS_IN_PROGRESS],
            'all but one of a three-year plan (rounds to 100%)' => [1095, 1094, UserPlan::STATUS_IN_PROGRESS],
            'every item completed'                              => [10, 10, UserPlan::STATUS_COMPLETED],
            'single-item plan completed'                        => [1, 1, UserPlan::STATUS_COMPLETED],
            'duplicate completion rows cannot hide a finished plan' => [10, 11, UserPlan::STATUS_COMPLETED],
        ];
    }

    /**
     * @dataProvider statusProvider
     * @test
     */
    public function resolvesStatusFromExactCounts(int $total_items, int $total_items_completed, string $expected)
    {
        $this->assertSame($expected, UserPlan::resolveStatus($total_items, $total_items_completed));
    }

    /**
     * The string values are the documented enum; clients switch on them.
     *
     * @test
     */
    public function statusValuesAreTheDocumentedEnum()
    {
        $this->assertSame('not_started', UserPlan::STATUS_NOT_STARTED);
        $this->assertSame('in_progress', UserPlan::STATUS_IN_PROGRESS);
        $this->assertSame('completed', UserPlan::STATUS_COMPLETED);
    }
}
