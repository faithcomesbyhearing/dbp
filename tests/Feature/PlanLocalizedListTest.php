<?php

namespace Tests\Feature;

use App\Http\Controllers\Plan\PlansController;
use App\Models\Plan\Plan;
use App\Models\Plan\PlanLocalization;
use App\Models\User\User;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit coverage for the DB-free parts of the localized plan lists, GET /api/plans?localized=true (API task 102556):
 *  - PlansController::parseAppLocale(), the localized/iso/alt_locale parse shared with Plan Details (104292): when
 *    the flag is on, every 422 message and their order;
 *  - PlansController::resolveLocalizedSort(): the accepted sort_by/sort_dir values in any case, the 400 messages
 *    and their order, so no value reaches the query as a 500 or an unknown column;
 *  - Plan::localizedSortColumn();
 *  - the SQL and raw bindings of Plan::scopeWithAppLocalization() (inner join for Discover, left join for the user's
 *    own plans, with and without alt_locale, with the last_interaction joins) and of
 *    PlanLocalization::scopeForAppLocale() on its own, plus the pagination count query;
 *  - Plan::applyAppLocalization(): a matched row, a row with no thumbnail, no row, and the serialised key order.
 *
 * Which row MySQL picks for a locale, and the responses themselves, are covered by
 * tests/Integration/PlanIndexLocalizedTest.php.
 *
 * Runs without a database or the Laravel container: the models get a MySQL connection whose PDO closure throws and
 * whose select() only records the query, and the DB facade (used by scopeSortByLastInteraction) resolves to that
 * connection. Both are restored after each test.
 *
 * @group plans
 */
class PlanLocalizedListTest extends TestCase
{
    private const SORT_BY_ERROR = 'sort_by must be one of: name, created_at, updated_at, last_interaction';
    private const SORT_DIR_ERROR = 'sort_dir must be asc or desc';
    private const FEATURED_LAST_INTERACTION_ERROR = 'Sort by last_interaction is not supported for featured plans';

    private const LOCALIZATION_JOIN = 'join `plans_localization` as `localization` on `localization`.`plan_id` = `plans`.`id` '
        . 'and `localization`.`iso` = ? and `localization`.`id` = (select `plans_localization`.`id` from '
        . '`plans_localization` where `plans_localization`.`plan_id` = `plans`.`id` and `plans_localization`.`iso` = ? '
        . 'order by plans_localization.alt_locale <=> ? DESC, plans_localization.alt_locale IS NULL DESC, '
        . '`plans_localization`.`id` asc limit 1)';

    private const LOCALIZATION_SELECT = '`localization`.`id` as `localization_row_id`, '
        . '`localization`.`iso` as `localization_row_iso`, '
        . '`localization`.`alt_locale` as `localization_row_alt_locale`, '
        . '`localization`.`bible_id` as `localization_row_bible_id`, '
        . '`localization`.`name` as `localization_row_name`, '
        . '`localization`.`thumbnail` as `localization_row_thumbnail`';

    private $connection;
    private $previous_resolver;
    private $previous_facade_application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new class(function () {
            throw new RuntimeException('PlanLocalizedListTest must not reach a database.');
        }, 'dbp_users', '', ['name' => 'dbp_users']) extends MySqlConnection {
            public $selects = [];
            public $rows = [];

            public function select($query, $bindings = [], $useReadPdo = true)
            {
                $this->selects[] = [$query, $bindings];
                return $this->rows;
            }
        };

        $this->previous_resolver = Model::getConnectionResolver();
        Model::setConnectionResolver(new ConnectionResolver(['dbp_users' => $this->connection]));

        // scopeSortByLastInteraction() calls \DB::raw(), which the app's alias loader provides in a request. With no
        // facade application, swap() only sets the instance the facade resolves to; no container is touched.
        if (!class_exists('DB')) {
            class_alias(DB::class, 'DB');
        }
        $this->previous_facade_application = Facade::getFacadeApplication();
        Facade::setFacadeApplication(null);
        DB::swap($this->connection);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstance('db');
        Facade::setFacadeApplication($this->previous_facade_application);

        if ($this->previous_resolver) {
            Model::setConnectionResolver($this->previous_resolver);
        } else {
            Model::unsetConnectionResolver();
        }

        parent::tearDown();
    }

    // parseAppLocale()

    public static function flagOffProvider(): array
    {
        return [
            'absent'      => [null],
            'empty'       => [''],
            '1'           => ['1'],
            'yes'         => ['yes'],
            'on'          => ['on'],
            'false'       => ['false'],
            'JSON false'  => [false],
            'JSON 1'      => [1],
            'list'        => [['true']],
            'true padded' => [' true'],
        ];
    }

    /**
     * Anything but the word true or a JSON true leaves the call as it is, whatever iso and alt_locale hold, even when
     * they would be errors with the flag on (AC12).
     *
     * @dataProvider flagOffProvider
     * @test
     */
    public function theFlagIsOffForAnyOtherValue($localized)
    {
        $this->assertNull(PlansController::parseAppLocale($localized, 'eng', 'fr-CA'));
        $this->assertNull(PlansController::parseAppLocale($localized, null, null));
        $this->assertNull(PlansController::parseAppLocale($localized, ['eng'], ['fr-CA']));
    }

    public static function flagOnProvider(): array
    {
        return [
            'true'      => ['true'],
            'TRUE'      => ['TRUE'],
            'True'      => ['True'],
            'JSON true' => [true],
        ];
    }

    /**
     * @dataProvider flagOnProvider
     * @test
     */
    public function theFlagIsOnForTheWordTrueInAnyCase($localized)
    {
        $this->assertSame(
            ['iso' => 'eng', 'alt_locale' => null, 'error' => null],
            PlansController::parseAppLocale($localized, 'eng', null)
        );
    }

    /**
     * iso and alt_locale are passed on as sent: matching them is case-insensitive in the database, so nothing is
     * lower-cased here. An empty alt_locale counts as not sent.
     *
     * @test
     */
    public function isoAndAltLocaleArePassedOnAsSent()
    {
        $this->assertSame(
            ['iso' => 'fra', 'alt_locale' => 'fr-CA', 'error' => null],
            PlansController::parseAppLocale('true', 'fra', 'fr-CA')
        );
        $this->assertSame(
            ['iso' => 'FRA', 'alt_locale' => 'FR-ca', 'error' => null],
            PlansController::parseAppLocale('true', 'FRA', 'FR-ca')
        );
        $this->assertSame(
            ['iso' => 'fra', 'alt_locale' => null, 'error' => null],
            PlansController::parseAppLocale('true', 'fra', '')
        );
    }

    public static function localeErrorProvider(): array
    {
        $missing = 'iso is required when localized=true';
        $iso_list = 'iso must be a single value';
        $alt_list = 'alt_locale must be a single value';

        return [
            'iso missing'                       => [null, null, $missing],
            'iso empty'                         => ['', null, $missing],
            'iso missing, alt_locale sent'      => [null, 'fr-CA', $missing],
            'iso list'                          => [['eng'], null, $iso_list],
            'iso empty list'                    => [[], null, $iso_list],
            'alt_locale list'                   => ['fra', ['fr-CA'], $alt_list],
            // AC9 order: list-valued iso, then list-valued alt_locale, then missing iso
            'iso list beats alt_locale list'    => [['eng'], ['fr-CA'], $iso_list],
            'alt_locale list beats missing iso' => [null, ['fr-CA'], $alt_list],
        ];
    }

    /**
     * The 422 messages (AC8, AC9), the whole message in `error`, the first failing check in AC9's order.
     *
     * @dataProvider localeErrorProvider
     * @test
     */
    public function eachLocaleErrorIsTheWholeMessage($iso, $alt_locale, string $expected)
    {
        $this->assertSame(
            ['iso' => null, 'alt_locale' => null, 'error' => $expected],
            PlansController::parseAppLocale('true', $iso, $alt_locale)
        );
    }

    // resolveLocalizedSort()

    public static function acceptedSortProvider(): array
    {
        return [
            'name'                         => ['name', 'asc', true, 'name', 'asc'],
            'NAME desc'                    => ['NAME', 'DESC', true, 'name', 'desc'],
            'Name Asc'                     => ['Name', 'Asc', false, 'name', 'asc'],
            'created_at desc'              => ['created_at', 'desc', true, 'created_at', 'desc'],
            'CREATED_AT'                   => ['CREATED_AT', 'asc', false, 'created_at', 'asc'],
            'updated_at'                   => ['updated_at', 'asc', true, 'updated_at', 'asc'],
            'Updated_At'                   => ['Updated_At', 'desc', false, 'updated_at', 'desc'],
            'last_interaction, my plans'   => ['last_interaction', 'desc', false, 'last_interaction', 'desc'],
            'Last_Interaction, my plans'   => ['Last_Interaction', 'asc', false, 'last_interaction', 'asc'],
            'LAST_INTERACTION, my plans'   => ['LAST_INTERACTION', 'DESC', false, 'last_interaction', 'desc'],
        ];
    }

    /**
     * The accepted values in any case (AC6). sort_by comes back lower-cased, so getPlans() still recognises
     * last_interaction and adds the joins its alias needs.
     *
     * @dataProvider acceptedSortProvider
     * @test
     */
    public function acceptedSortValuesInAnyCase(
        string $sort_by,
        string $sort_dir,
        bool $featured,
        string $expected_sort_by,
        string $expected_sort_dir
    ) {
        $sort = PlansController::resolveLocalizedSort($sort_by, $sort_dir, $featured);

        $this->assertNull($sort['error']);
        $this->assertSame($expected_sort_by, $sort['sort_by']);
        $this->assertSame($expected_sort_dir, $sort['sort_dir']);
        $this->assertEquals(Plan::localizedSortColumn($expected_sort_by), $sort['column']);
    }

    public static function sortErrorProvider(): array
    {
        return [
            'unknown sort_by'                         => ['foo', 'asc', false, self::SORT_BY_ERROR],
            'a plan column not offered'               => ['total_days', 'asc', false, self::SORT_BY_ERROR],
            'id is not offered'                       => ['id', 'desc', true, self::SORT_BY_ERROR],
            'empty sort_by'                           => ['', 'asc', false, self::SORT_BY_ERROR],
            'missing sort_by'                         => [null, 'asc', false, self::SORT_BY_ERROR],
            'list sort_by'                            => [['name'], 'asc', false, self::SORT_BY_ERROR],
            'a qualified column'                      => ['plans.name', 'asc', false, self::SORT_BY_ERROR],
            'last_interaction on Discover'            => ['last_interaction', 'asc', true, self::FEATURED_LAST_INTERACTION_ERROR],
            'LAST_INTERACTION on Discover'            => ['LAST_INTERACTION', 'asc', true, self::FEATURED_LAST_INTERACTION_ERROR],
            'Last_Interaction on Discover'            => ['Last_Interaction', 'desc', true, self::FEATURED_LAST_INTERACTION_ERROR],
            'unknown sort_dir'                        => ['name', 'up', false, self::SORT_DIR_ERROR],
            'empty sort_dir'                          => ['name', '', false, self::SORT_DIR_ERROR],
            'missing sort_dir'                        => ['name', null, false, self::SORT_DIR_ERROR],
            'list sort_dir'                           => ['name', ['asc'], false, self::SORT_DIR_ERROR],
            'sort_dir with a space'                   => ['name', 'asc ', false, self::SORT_DIR_ERROR],
            // AC9 order: sort_by, then Discover + last_interaction, then sort_dir
            'bad sort_by beats bad sort_dir'          => ['foo', 'up', false, self::SORT_BY_ERROR],
            'bad sort_by beats Discover'              => ['foo', 'asc', true, self::SORT_BY_ERROR],
            'Discover last_interaction beats sort_dir' => ['Last_Interaction', 'up', true, self::FEATURED_LAST_INTERACTION_ERROR],
        ];
    }

    /**
     * Each refused value is a 400 message, never a value that reaches orderBy() (AC6, AC7, AC9).
     *
     * @dataProvider sortErrorProvider
     * @test
     */
    public function refusedSortValuesGiveTheMessage($sort_by, $sort_dir, bool $featured, string $expected)
    {
        $this->assertSame(
            ['sort_by' => null, 'sort_dir' => null, 'column' => null, 'error' => $expected],
            PlansController::resolveLocalizedSort($sort_by, $sort_dir, $featured)
        );
    }

    // localizedSortColumn()

    /** @test */
    public function sortColumnsAreQualifiedAndNameIsTheDisplayedName()
    {
        $name = Plan::localizedSortColumn('name');
        $this->assertInstanceOf(Expression::class, $name);
        $this->assertSame('COALESCE(localization.name, plans.name)', $name->getValue($this->connection->getQueryGrammar()));

        $this->assertSame('plans.created_at', Plan::localizedSortColumn('created_at'));
        $this->assertSame('plans.updated_at', Plan::localizedSortColumn('updated_at'));
        $this->assertSame('last_interaction', Plan::localizedSortColumn('last_interaction'));

        // The caller lower-cases first; anything else is not a column here.
        $this->assertNull(Plan::localizedSortColumn('NAME'));
        $this->assertNull(Plan::localizedSortColumn('total_days'));
        $this->assertNull(Plan::localizedSortColumn(''));
    }

    // SQL and bindings

    /**
     * The user's own plans: a left join, so a plan with no row stays (AC5); the plan's columns come first and the
     * row's columns only under aliases; ordered by the displayed name, then the plan id. Bindings in SQL order:
     * the user, the iso of the join, the iso and alt_locale of the subquery, then the draft filter.
     *
     * @test
     */
    public function myPlansLeftJoinTheMatchingRow()
    {
        $query = $this->myPlansQuery()
            ->withAppLocalization('fra', 'fr-CA', false)
            ->orderBy(Plan::localizedSortColumn('name'), 'asc')
            ->orderBy('plans.id');

        $this->assertSame(
            'select `plans`.*, `user_plans`.`start_date`, `user_plans`.`percentage_completed`, '
            . self::LOCALIZATION_SELECT
            . ' from `plans` inner join `user_plans` on `user_plans`.`plan_id` = `plans`.`id` and `user_plans`.`user_id` = ? '
            . 'left ' . self::LOCALIZATION_JOIN
            . ' where `plans`.`draft` = ? and `plans`.`deleted_at` is null'
            . ' order by COALESCE(localization.name, plans.name) asc, `plans`.`id` asc',
            $query->toSql()
        );
        $this->assertSame([42, 'fra', 'fra', 'fr-CA', 0], $query->getBindings());
        $this->assertSame([42, 'fra', 'fra', 'fr-CA'], $query->getQuery()->getRawBindings()['join']);
    }

    /**
     * Discover: an inner join, so a plan with no row in the language is left out (AC1, AC2); `plans.*` is selected
     * first because the query had no select yet. Without alt_locale the subquery's `<=>` compares with NULL.
     *
     * @test
     */
    public function discoverInnerJoinsTheMatchingRow()
    {
        $query = $this->discoverQuery()
            ->withAppLocalization('eng', null, true)
            ->orderBy(Plan::localizedSortColumn('created_at'), 'desc')
            ->orderBy('plans.id');

        $this->assertSame(
            'select `plans`.*, ' . self::LOCALIZATION_SELECT
            . ' from `plans` inner ' . self::LOCALIZATION_JOIN
            . ' where `plans`.`draft` = ? and `plans`.`featured` = ? and `plans`.`deleted_at` is null'
            . ' order by `plans`.`created_at` desc, `plans`.`id` asc',
            $query->toSql()
        );
        $this->assertSame(['eng', 'eng', null, 0, '1'], $query->getBindings());
    }

    /**
     * With sort_by=last_interaction the two interaction sub-joins come before the localization join, so their user
     * bindings come first (AC6).
     *
     * @test
     */
    public function lastInteractionJoinsKeepTheBindingOrder()
    {
        $user = new User();
        $user->forceFill(['id' => 42]);

        // Step by step, in getPlans()'s order: the interaction joins and their alias come before the plan's columns.
        $query = Plan::query();
        $query->where('plans.draft', 0);
        $query->join('user_plans', function ($join) {
            $join->on('user_plans.plan_id', '=', 'plans.id')->where('user_plans.user_id', 42);
        });
        $query->sortByLastInteraction($user);
        $query->addSelect(['plans.*', 'user_plans.start_date', 'user_plans.percentage_completed']);
        $query->withAppLocalization('fra', 'fr-CA', false);
        $query->orderBy(Plan::localizedSortColumn('last_interaction'), 'desc');
        $query->orderBy('plans.id');

        $sql = $query->toSql();
        $this->assertSame([42, 42, 42, 'fra', 'fra', 'fr-CA'], $query->getQuery()->getRawBindings()['join']);
        $this->assertSame([42, 42, 42, 'fra', 'fra', 'fr-CA', 0], $query->getBindings());
        $this->assertStringContainsString('as last_interaction, `plans`.*, `user_plans`.`start_date`', $sql);
        $this->assertStringContainsString('left ' . self::LOCALIZATION_JOIN, $sql);
        $this->assertStringEndsWith('order by `last_interaction` desc, `plans`.`id` asc', $sql);
    }

    /**
     * A select that already exists is kept as it is; plans.* is added only when there was none.
     *
     * @test
     */
    public function anExistingSelectIsNotRepeated()
    {
        $columns = $this->myPlansQuery()->withAppLocalization('eng', null, false)->getQuery()->columns;

        $this->assertSame(['plans.*', 'user_plans.start_date', 'user_plans.percentage_completed'], array_slice($columns, 0, 3));
        $this->assertCount(9, $columns);
        $this->assertSame(1, count(array_keys($columns, 'plans.*', true)));
    }

    /**
     * The pagination count keeps the join and its bindings, so on Discover `total` counts only the plans that have
     * a row in the language (AC2, AC10).
     *
     * @test
     */
    public function theCountQueryCountsOnlyJoinedPlans()
    {
        $this->connection->rows = [(object) ['aggregate' => 4]];

        $total = $this->discoverQuery()
            ->withAppLocalization('eng', null, true)
            ->orderBy(Plan::localizedSortColumn('name'), 'asc')
            ->orderBy('plans.id')
            ->toBase()
            ->getCountForPagination();

        $this->assertSame(4, $total);
        $this->assertCount(1, $this->connection->selects);
        [$sql, $bindings] = $this->connection->selects[0];
        $this->assertSame(
            'select count(*) as aggregate from `plans` inner ' . self::LOCALIZATION_JOIN
            . ' where `plans`.`draft` = ? and `plans`.`featured` = ? and `plans`.`deleted_at` is null',
            $sql
        );
        $this->assertSame(['eng', 'eng', null, 0, '1'], $bindings);
    }

    /**
     * The rule on its own, as Plan Details (104292) uses it: only the language filter and the ordering.
     *
     * @test
     */
    public function forAppLocaleOnlyFiltersTheLanguageAndOrders()
    {
        $query = PlanLocalization::where('plan_id', 209)->forAppLocale('fra', 'fr-CA');

        $this->assertSame(
            'select * from `plans_localization` where `plan_id` = ? and `plans_localization`.`iso` = ?'
            . ' order by plans_localization.alt_locale <=> ? DESC, plans_localization.alt_locale IS NULL DESC,'
            . ' `plans_localization`.`id` asc',
            $query->toSql()
        );
        $this->assertSame([209, 'fra', 'fr-CA'], $query->getBindings());
    }

    // applyAppLocalization()

    /**
     * A matched row: its name and thumbnail are shown, `localization` names it, the plan's id stays, and no alias is
     * left (AC1).
     *
     * @test
     */
    public function aMatchedRowReplacesNameAndThumbnail()
    {
        $plan = $this->plan([
            'localization_row_id'         => 7,
            'localization_row_iso'        => 'fra',
            'localization_row_alt_locale' => 'fr-CA',
            'localization_row_bible_id'   => 'FRALSG',
            'localization_row_name'       => 'La Bible en une année',
            'localization_row_thumbnail'  => 'https://example.org/fr.png',
        ]);

        $plan->applyAppLocalization();

        $this->assertSame(209, $plan->getAttribute('id'));
        $this->assertSame('La Bible en une année', $plan->getAttribute('name'));
        $this->assertSame('https://example.org/fr.png', $plan->getAttribute('thumbnail'));
        $this->assertSame(
            ['id' => 7, 'iso' => 'fra', 'alt_locale' => 'fr-CA', 'bible_id' => 'FRALSG'],
            $plan->getAttribute('localization')
        );
        $this->assertNoAliasLeft($plan);
    }

    /**
     * A row with no thumbnail keeps the plan's (AC4); its id comes back as an int even if the driver gave a string.
     *
     * @test
     */
    public function aRowWithoutThumbnailKeepsThePlansThumbnail()
    {
        $plan = $this->plan([
            'localization_row_id'         => '3',
            'localization_row_iso'        => 'fra',
            'localization_row_alt_locale' => null,
            'localization_row_bible_id'   => null,
            'localization_row_name'       => 'La Bible en un an',
            'localization_row_thumbnail'  => null,
        ]);

        $plan->applyAppLocalization();

        $this->assertSame('La Bible en un an', $plan->getAttribute('name'));
        $this->assertSame('https://example.org/en.png', $plan->getAttribute('thumbnail'));
        $this->assertSame(
            ['id' => 3, 'iso' => 'fra', 'alt_locale' => null, 'bible_id' => null],
            $plan->getAttribute('localization')
        );
        $this->assertNoAliasLeft($plan);
    }

    /**
     * No row (the left join's NULLs): the plan keeps its own values and gets `localization: null` (AC5).
     *
     * @test
     */
    public function noRowKeepsThePlansOwnValues()
    {
        $plan = $this->plan(array_fill_keys(array_keys(Plan::LOCALIZATION_ROW_COLUMNS), null));

        $plan->applyAppLocalization();

        $this->assertSame(209, $plan->getAttribute('id'));
        $this->assertSame('Bible in a Year', $plan->getAttribute('name'));
        $this->assertSame('https://example.org/en.png', $plan->getAttribute('thumbnail'));
        $this->assertArrayHasKey('localization', $plan->getAttributes());
        $this->assertNull($plan->getAttribute('localization'));
        $this->assertNoAliasLeft($plan);
    }

    /**
     * The serialised item: the plan's keys in their places, `localization` after the per-user keys getPlans() adds
     * and before the `user` relation, no alias, and the hidden columns still hidden (§5 of the design).
     *
     * @test
     */
    public function theSerialisedItemKeepsTheKeyOrder()
    {
        $plan = $this->plan([
            'localization_row_id'         => 3,
            'localization_row_iso'        => 'eng',
            'localization_row_alt_locale' => null,
            'localization_row_bible_id'   => 'ENGESV',
            'localization_row_name'       => ' Psalm A Day',
            'localization_row_thumbnail'  => 'https://example.org/3.png',
        ]);
        $plan->total_days = 150;
        $plan->user_status = null;
        $plan->completed_days = 0;
        $user = new User();
        $user->forceFill(['id' => 1920998, 'name' => 'FCBH Global Apps']);
        $plan->setRelation('user', $user);

        $plan->applyAppLocalization();
        $item = $plan->toArray();

        $this->assertSame(
            ['id', 'name', 'thumbnail', 'featured', 'draft', 'total_days', 'user_status', 'completed_days', 'localization', 'user'],
            array_keys($item)
        );
        $this->assertTrue($item['featured']);
        $this->assertFalse($item['draft']);
        $this->assertSame(' Psalm A Day', $item['name']);
        $this->assertSame(['id' => 3, 'iso' => 'eng', 'alt_locale' => null, 'bible_id' => 'ENGESV'], $item['localization']);
    }

    private function myPlansQuery()
    {
        return Plan::query()
            ->where('plans.draft', 0)
            ->join('user_plans', function ($join) {
                $join->on('user_plans.plan_id', '=', 'plans.id')->where('user_plans.user_id', 42);
            })
            ->addSelect(['plans.*', 'user_plans.start_date', 'user_plans.percentage_completed']);
    }

    private function discoverQuery()
    {
        return Plan::query()
            ->where('plans.draft', 0)
            ->where('plans.featured', '1');
    }

    /**
     * A plan as the localized query returns it: its own columns (user_id and language_id are hidden), then the
     * localization_row_* aliases.
     */
    private function plan(array $row_columns): Plan
    {
        $plan = new Plan();
        $plan->setRawAttributes(array_merge([
            'id'          => 209,
            'user_id'     => 1920998,
            'name'        => 'Bible in a Year',
            'thumbnail'   => 'https://example.org/en.png',
            'featured'    => 1,
            'draft'       => 0,
            'language_id' => 17045,
        ], $row_columns), true);
        $plan->exists = true;

        return $plan;
    }

    private function assertNoAliasLeft(Plan $plan): void
    {
        foreach (array_keys(Plan::LOCALIZATION_ROW_COLUMNS) as $alias) {
            $this->assertArrayNotHasKey($alias, $plan->getAttributes());
        }
        $this->assertSame([], preg_grep('/^localization_row_/', array_keys($plan->toArray())));
    }
}
