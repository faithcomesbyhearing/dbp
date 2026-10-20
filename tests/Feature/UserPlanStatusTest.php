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
 * "Completed" for a plan the user has not finished.
 *
 * resolveStatus() is only asked about plans the user has a user_plans row for. The start date alone
 * decides started: no start date is saved, whatever is completed; a started plan is completed when
 * every item is completed, otherwise in_progress. A plan the user has no relationship with never
 * reaches here; the caller reports it as null.
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
            // Started (the user_plans row has a start_date)
            'started, no items'                                 => [0, 0, true, UserPlan::STATUS_IN_PROGRESS],
            'started, nothing completed'                        => [10, 0, true, UserPlan::STATUS_IN_PROGRESS],
            'started three-year plan, nothing completed'        => [1095, 0, true, UserPlan::STATUS_IN_PROGRESS],
            'one item of a three-year plan (rounds to 0%)'      => [1095, 1, true, UserPlan::STATUS_IN_PROGRESS],
            'half way'                                          => [10, 5, true, UserPlan::STATUS_IN_PROGRESS],
            'all but one of a three-year plan (rounds to 100%)' => [1095, 1094, true, UserPlan::STATUS_IN_PROGRESS],
            'every item completed'                              => [10, 10, true, UserPlan::STATUS_COMPLETED],
            'single-item plan completed'                        => [1, 1, true, UserPlan::STATUS_COMPLETED],
            'duplicate completion rows cannot hide a finished plan' => [10, 11, true, UserPlan::STATUS_COMPLETED],
            // Never started (no start_date): always saved, whatever is completed (clients cannot complete
            // items of an unstarted plan, but old data and translate's copied progress can)
            'not started, nothing completed'                    => [10, 0, false, UserPlan::STATUS_SAVED],
            'not started, no items'                             => [0, 0, false, UserPlan::STATUS_SAVED],
            'not started, one item completed'                   => [10, 1, false, UserPlan::STATUS_SAVED],
            'not started, one item of a three-year plan'        => [1095, 1, false, UserPlan::STATUS_SAVED],
            'not started, every item completed'                 => [10, 10, false, UserPlan::STATUS_SAVED],
            'not started, duplicate completion rows'            => [10, 11, false, UserPlan::STATUS_SAVED],
        ];
    }

    /**
     * @dataProvider statusProvider
     * @test
     */
    public function resolvesStatusFromExactCounts(
        int $total_items,
        int $total_items_completed,
        bool $started,
        string $expected
    ) {
        $this->assertSame($expected, UserPlan::resolveStatus($total_items, $total_items_completed, $started));
    }

    /**
     * The string values are the documented enum; clients switch on them.
     *
     * @test
     */
    public function statusValuesAreTheDocumentedEnum()
    {
        $this->assertSame('saved', UserPlan::STATUS_SAVED);
        $this->assertSame('in_progress', UserPlan::STATUS_IN_PROGRESS);
        $this->assertSame('completed', UserPlan::STATUS_COMPLETED);
    }

    /**
     * There is no "not started" status: a plan with no start date is saved, and one with a start
     * date is in_progress even with nothing completed. Guards against the
     * constant being reintroduced without the enum and the swagger being updated with it.
     *
     * @test
     */
    public function thereIsNoNotStartedStatus()
    {
        $this->assertFalse(
            defined(UserPlan::class . '::STATUS_NOT_STARTED'),
            'not_started is not a status: see saved and in_progress in the user_status enum in PlansController.'
        );
    }
}
