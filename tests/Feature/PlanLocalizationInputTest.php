<?php

namespace Tests\Feature;

use App\Http\Controllers\Plan\PlanLocalizationsController;
use App\Models\Plan\Plan;
use App\Traits\CheckProjectMembership;
use App\Traits\PlanEditorGuard;
use Illuminate\Database\MySqlConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit coverage for the DB-free parts of the plan localization CRUD (API task 102557):
 *  - PlanLocalizationsController::normalizeFields(), the JSON body check for create and update (presence vs null,
 *    every field rule, plan_id echoes, all messages in one reply);
 *  - canonicalAltLocale() and the pure iso/alt_locale decision isoMatchesAltLocale();
 *  - the 409 message;
 *  - PlanEditorGuard's canEditPlan(), its role accessor and denyUnlessCanEditPlan(), on an anonymous class that uses
 *    the trait (its statics and constant are never reached through the trait's name: deprecated, and fatal for the
 *    constant);
 *  - Laravel's own duplicate-key classification, which the 409 path relies on to tell 1062 from a foreign-key error.
 *
 * In a request, TrimStrings and ConvertEmptyStringsToNull have already cleaned the values; here they arrive raw, so
 * a trailing newline must be rejected, not trimmed away. Whether iso and bible_id match a row, and the two iso1
 * lookups, are DB questions covered by tests/Integration/PlanLocalizationCrudTest.php.
 *
 * Runs without a database or the Laravel container.
 *
 * @group plans
 */
class PlanLocalizationInputTest extends TestCase
{
    private const PLAN_ID = 209;

    private function create(array $body): array
    {
        return PlanLocalizationsController::normalizeFields($body, true, self::PLAN_ID);
    }

    private function update(array $body): array
    {
        return PlanLocalizationsController::normalizeFields($body, false, self::PLAN_ID);
    }

    /** @test */
    public function aFullCreateBodyIsCleaned()
    {
        $result = $this->create([
            'iso'         => 'FRA',
            'alt_locale'  => 'fr-ca',
            'name'        => 'La Bible en un an',
            'thumbnail'   => 'https://example.org/fr.png',
            'description' => 'Une description',
            'bible_id'    => 'fralsg',
            'plan_id'     => self::PLAN_ID,
            'id'          => 12,
            'created_at'  => 'ignored',
            'unknown'     => 'ignored',
        ]);

        $this->assertSame([], $result['errors']);
        // bible_id keeps the sent case here; the lookup replaces it with the Bible's own spelling.
        $this->assertSame([
            'iso'         => 'fra',
            'alt_locale'  => 'fr-CA',
            'name'        => 'La Bible en un an',
            'thumbnail'   => 'https://example.org/fr.png',
            'description' => 'Une description',
            'bible_id'    => 'fralsg',
        ], $result['values']);
    }

    /** @test */
    public function createNeedsIsoAndNameAndReportsBothAtOnce()
    {
        $result = $this->create(['description' => 'x']);

        $this->assertSame(['iso is required.', 'name is required.'], $result['errors']);
    }

    public static function blankProvider(): array
    {
        return [
            'null'         => [null],
            'empty string' => [''],
            'spaces only'  => ['   '],
            'newline only' => ["\n"],
        ];
    }

    /**
     * @dataProvider blankProvider
     * @test
     */
    public function aBlankRequiredFieldIsMissingOnCreateAndCannotBeEmptyOnUpdate($blank)
    {
        $this->assertSame(
            ['iso is required.', 'name is required.'],
            $this->create(['iso' => $blank, 'name' => $blank])['errors']
        );
        $this->assertSame(
            ['iso cannot be empty.', 'name cannot be empty.'],
            $this->update(['iso' => $blank, 'name' => $blank])['errors']
        );
    }

    /**
     * @dataProvider blankProvider
     * @test
     */
    public function aBlankOptionalFieldClearsIt($blank)
    {
        $result = $this->update(['alt_locale' => $blank, 'thumbnail' => $blank, 'description' => $blank, 'bible_id' => $blank]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(
            ['alt_locale' => null, 'thumbnail' => null, 'description' => null, 'bible_id' => null],
            $result['values']
        );
    }

    /** @test */
    public function anUpdateChangesOnlyTheFieldsSent()
    {
        $result = $this->update(['description' => 'Nouvelle description']);

        $this->assertSame([], $result['errors']);
        $this->assertSame(['description' => 'Nouvelle description'], $result['values']);
    }

    public static function nothingToChangeProvider(): array
    {
        return [
            'empty object'               => [[]],
            'only the read-only fields'  => [['id' => 12, 'plan_id' => self::PLAN_ID, 'created_at' => 'a', 'updated_at' => 'b']],
            'only unknown keys'          => [['plan_role' => 'plan_admin', 'featured' => true]],
        ];
    }

    /**
     * @dataProvider nothingToChangeProvider
     * @test
     */
    public function anUpdateWithNoEditableFieldIsRefused(array $body)
    {
        $this->assertSame(['Send at least one field to change.'], $this->update($body)['errors']);
    }

    public static function wrongTypeProvider(): array
    {
        return [
            'integer' => [123],
            'float'   => [1.5],
            'boolean' => [true],
            'array'   => [['fra']],
            'object'  => [['a' => 'b']],
        ];
    }

    /**
     * @dataProvider wrongTypeProvider
     * @test
     */
    public function everyFieldMustBeAString($value)
    {
        $result = $this->create([
            'iso' => $value, 'alt_locale' => $value, 'name' => $value,
            'thumbnail' => $value, 'description' => $value, 'bible_id' => $value,
        ]);

        $this->assertSame([
            'iso must be a string.',
            'alt_locale must be a string.',
            'name must be a string.',
            'thumbnail must be a string.',
            'description must be a string.',
            'bible_id must be a string.',
        ], $result['errors']);
        $this->assertSame([], $result['values']);
    }

    public static function badIsoProvider(): array
    {
        return [
            'two letters'      => ['fr'],
            'four letters'     => ['fran'],
            'digit'            => ['fr1'],
            'trailing newline' => ["fra\n"],
            'leading space'    => [' fra'],
            'locale'           => ['fr-CA'],
            'non-ascii'        => ['frä'],
        ];
    }

    /**
     * @dataProvider badIsoProvider
     * @test
     */
    public function isoMustBeThreeLetters(string $iso)
    {
        $this->assertSame(['iso must be a 3-letter language code.'], $this->update(['iso' => $iso])['errors']);
    }

    /** @test */
    public function isoIsLowerCased()
    {
        $this->assertSame(['iso' => 'eng'], $this->update(['iso' => 'EnG'])['values']);
    }

    public static function altLocaleProvider(): array
    {
        return [
            'region'                    => ['fr-ca', 'fr-CA'],
            'region already canonical'  => ['fr-CA', 'fr-CA'],
            'script and region'         => ['zh-hant-hk', 'zh-Hant-HK'],
            'script and region, upper'  => ['ZH-HANT-HK', 'zh-Hant-HK'],
            'script only'               => ['zh-HANS', 'zh-Hans'],
            'numeric region'            => ['es-419', 'es-419'],
            '3-letter language'         => ['YUE-hk', 'yue-HK'],
            'longest form, 12 chars'    => ['yue-Hant-419', 'yue-Hant-419'],
            'language only'             => ['fr', null],
            '3-letter language only'    => ['fra', null],
            'underscore'                => ['fr_CA', null],
            'trailing newline'          => ["fr-CA\n", null],
            'trailing dash'             => ['fr-', null],
            '3-letter region'           => ['fr-CAN', null],
            '2-digit region'            => ['es-41', null],
            'script after region'       => ['zh-HK-Hant', null],
            'extension'                 => ['zh-Hant-HK-x', null],
            '13 characters'             => ['zh-Hant-HK-AB', null],
            '4-letter language'         => ['abcd-CA', null],
            'empty part'                => ['fr--CA', null],
        ];
    }

    /**
     * @dataProvider altLocaleProvider
     * @test
     */
    public function altLocaleIsCanonicalisedOrRefused(string $sent, ?string $expected)
    {
        $this->assertSame($expected, PlanLocalizationsController::canonicalAltLocale($sent));

        $result = $this->update(['alt_locale' => $sent]);
        if ($expected === null) {
            $this->assertSame(
                ['alt_locale must be a locale code with a region or script, such as fr-CA or zh-Hant-HK.'],
                $result['errors']
            );
        } else {
            $this->assertSame([], $result['errors']);
            $this->assertSame(['alt_locale' => $expected], $result['values']);
        }
    }

    public static function lengthProvider(): array
    {
        return [
            'name 191'                   => ['name', str_repeat('a', 191), null],
            'name 191 multibyte'         => ['name', str_repeat('é', 191), null],
            'name 192'                   => ['name', str_repeat('a', 192), 'name may not be longer than 191 characters.'],
            'thumbnail 191'              => ['thumbnail', str_repeat('a', 191), null],
            'thumbnail 192'              => ['thumbnail', str_repeat('a', 192), 'thumbnail may not be longer than 191 characters.'],
            'description 65535 bytes'    => ['description', str_repeat('a', 65535), null],
            'description 65536 bytes'    => ['description', str_repeat('a', 65536), 'description is too long.'],
            // 32,768 two-byte characters are 65,536 bytes: TEXT counts bytes.
            'description multibyte'      => ['description', str_repeat('é', 32768), 'description is too long.'],
            'bible_id 12'                => ['bible_id', 'ABCDEFGHIJKL', null],
            'bible_id 13'                => ['bible_id', 'ABCDEFGHIJKLM', 'bible_id may not be longer than 12 characters.'],
        ];
    }

    /**
     * @dataProvider lengthProvider
     * @test
     */
    public function lengthsAreCheckedBeforeMySqlCouldTruncate(string $field, string $value, ?string $error)
    {
        $result = $this->update([$field => $value]);

        if ($error === null) {
            $this->assertSame([], $result['errors']);
            $this->assertSame([$field => $value], $result['values']);
        } else {
            $this->assertSame([$error], $result['errors']);
            $this->assertSame([], $result['values']);
        }
    }

    public static function planIdProvider(): array
    {
        return [
            'the same id'                 => [self::PLAN_ID, true],
            'the same id as a string'     => ['209', true],
            'leading zero'                => ['0209', true],
            'another id'                  => [210, false],
            'another id as a string'      => ['210', false],
            'null'                        => [null, false],
            'empty string'                => ['', false],
            'float'                       => [209.0, false],
            'boolean'                     => [true, false],
            'digits and letters'          => ['209a', false],
            'trailing newline'            => ["209\n", false],
            'negative'                    => [-209, false],
            'array'                       => [[209], false],
        ];
    }

    /**
     * @dataProvider planIdProvider
     * @test
     */
    public function aSentPlanIdMustBeThePlansOwn($plan_id, bool $accepted)
    {
        $create = $this->create(['iso' => 'fra', 'name' => 'x', 'plan_id' => $plan_id]);
        $update = $this->update(['name' => 'x', 'plan_id' => $plan_id]);

        $this->assertSame($accepted ? [] : ['plan_id does not match the plan in the URL.'], $create['errors']);
        $this->assertSame($accepted ? [] : ['plan_id cannot be changed.'], $update['errors']);
        // Never a value to write, even when accepted.
        $this->assertArrayNotHasKey('plan_id', $create['values']);
        $this->assertArrayNotHasKey('plan_id', $update['values']);
    }

    /** @test */
    public function everyProblemIsReportedInOneReplyAndNothingInvalidIsKept()
    {
        $result = $this->create([
            'iso'        => 'fr',
            'alt_locale' => 'fr',
            'name'       => str_repeat('a', 192),
            'bible_id'   => 'ABCDEFGHIJKLM',
            'plan_id'    => 1,
            'thumbnail'  => 'ok.png',
        ]);

        $this->assertSame([
            'iso must be a 3-letter language code.',
            'alt_locale must be a locale code with a region or script, such as fr-CA or zh-Hant-HK.',
            'name may not be longer than 191 characters.',
            'bible_id may not be longer than 12 characters.',
            'plan_id does not match the plan in the URL.',
        ], $result['errors']);
        $this->assertSame(['thumbnail' => 'ok.png'], $result['values']);
    }

    public static function pairProvider(): array
    {
        // [iso, alt_locale, languages.iso where iso1 = the locale's 2-letter language, iso's own iso1, expected]
        return [
            'eng with fr-CA'          => ['eng', 'fr-CA', 'fra', null, false],
            'eng with ar-EG'          => ['eng', 'ar-EG', null, 'en', false],
            'eng with ko-KR'          => ['eng', 'ko-KR', null, 'en', false],
            'spa with sw-KE'          => ['spa', 'sw-KE', null, 'es', false],
            'ara with ar-EG'          => ['ara', 'ar-EG', null, null, true],
            'fra with fr-CA'          => ['fra', 'fr-CA', 'fra', null, true],
            'zho with zh-Hant-HK'     => ['zho', 'zh-Hant-HK', 'zho', null, true],
            'zho with yue-HK'         => ['zho', 'yue-HK', null, null, false],
            'yue with yue-HK'         => ['yue', 'yue-HK', null, null, true],
            'zho with cmn-Hans-CN'    => ['zho', 'cmn-Hans-CN', null, null, false],
            'iso1 table case'         => ['fra', 'fr-CA', 'FRA', null, true],
            'iso case'                => ['FRA', 'fr-CA', 'fra', null, true],
            'reverse direction match' => ['swa', 'sw-KE', null, 'sw', true],
        ];
    }

    /**
     * @dataProvider pairProvider
     * @test
     */
    public function isoAndAltLocaleMustBeTheSameLanguage(
        string $iso,
        string $alt_locale,
        ?string $iso_of_locale_language,
        ?string $iso1_of_iso,
        bool $expected
    ) {
        $this->assertSame(
            $expected,
            PlanLocalizationsController::isoMatchesAltLocale($iso, $alt_locale, $iso_of_locale_language, $iso1_of_iso)
        );
    }

    /** @test */
    public function theDuplicateMessageNamesThePairAndTheExistingRow()
    {
        $this->assertSame(
            'Plan 209 already has a localization for iso fra and alt_locale fr-CA (id 12).',
            PlanLocalizationsController::duplicateMessage(209, 'fra', 'fr-CA', 12)
        );
        $this->assertSame(
            'Plan 209 already has a localization for iso fra with no alt_locale (id 5).',
            PlanLocalizationsController::duplicateMessage(209, 'fra', null, 5)
        );
        $this->assertSame(
            'Plan 209 already has a localization for iso fra with no alt_locale.',
            PlanLocalizationsController::duplicateMessage(209, 'fra', null, null)
        );
    }

    /**
     * The 409 path catches Illuminate\Database\UniqueConstraintViolationException only. Laravel throws it when
     * MySqlConnection classifies the error as 1062 (duplicate key); a foreign-key error (1452), which shares
     * SQLSTATE 23000, must stay an ordinary QueryException so it is not reported as a duplicate.
     *
     * @test
     */
    public function onlyADuplicateKeyErrorCountsAsAUniqueConstraintViolation()
    {
        $connection = new MySqlConnection(function () {
            return null;
        }, 'test', '', []);
        $method = new ReflectionMethod($connection, 'isUniqueConstraintError');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($connection, new PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '209-fra-' for key 'plans_localization.uk_plans_localization_plan_iso_locale'"
        )));
        $this->assertFalse($method->invoke($connection, new PDOException(
            'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails'
        )));
    }

    /**
     * An object using the trait as a controller does (middleware() from the base controller, compareProjects() from
     * CheckProjectMembership, $key), with stand-ins for the two APIController methods the deny helper calls:
     * APIController itself cannot be built outside a request.
     */
    private function guardHost()
    {
        return new class extends Controller {
            use CheckProjectMembership;
            use PlanEditorGuard;

            protected $key;
            private $status_code = 200;
            /** As APIController::replyWithError() answers a v2 request: an empty array. */
            public $empty_error_reply = false;

            public function setStatusCode($status_code)
            {
                $this->status_code = $status_code;
                return $this;
            }

            public function replyWithError($message)
            {
                if ($this->empty_error_reply) {
                    return [];
                }
                return ['status_code' => $this->status_code, 'message' => $message];
            }

            public function deny(Request $request, Plan $plan)
            {
                return $this->denyUnlessCanEditPlan($request, $plan);
            }

            public function role(Request $request)
            {
                return $this->planRole($request);
            }
        };
    }

    public static function canEditPlanProvider(): array
    {
        // [role, plan owner, user, expected]
        return [
            'admin, someone else\'s plan' => ['plan_admin', 1920998, 1921467, true],
            'admin, own plan'             => ['plan_admin', 1921467, 1921467, true],
            'editor, own plan'            => ['plan_editor', 1921466, 1921466, true],
            'editor, someone else\'s'     => ['plan_editor', 1920998, 1921466, false],
            'no role'                     => [null, 1921466, 1921466, false],
            'the admin role'              => ['admin', 1921466, 1921466, false],
            'upper case'                  => ['PLAN_ADMIN', 1, 2, false],
            'empty string'                => ['', 1, 1, false],
        ];
    }

    /**
     * @dataProvider canEditPlanProvider
     * @test
     */
    public function canEditPlanFailsClosed(?string $role, int $owner_id, int $user_id, bool $expected)
    {
        $host = $this->guardHost();

        $this->assertSame($expected, $host::canEditPlan($role, $owner_id, $user_id));
    }

    /** @test */
    public function theRoleAttributeIsNamespaced()
    {
        $host = $this->guardHost();

        $this->assertSame('dbp.plan_editor_guard.role', $host::ROLE_ATTRIBUTE);
    }

    /**
     * The role is read only from request attributes, which the guard sets. A client sending plan_role in the query
     * string or the body gets nothing from it.
     *
     * @test
     */
    public function theRoleNeverComesFromClientInput()
    {
        $host = $this->guardHost();
        $request = Request::create(
            '/api/plans/localizations/1?plan_role=plan_admin&' . urlencode($host::ROLE_ATTRIBUTE) . '=plan_admin',
            'PUT',
            ['plan_role' => 'plan_admin', $host::ROLE_ATTRIBUTE => 'plan_admin']
        );

        $this->assertNull($host->role($request));

        $request->attributes->set($host::ROLE_ATTRIBUTE, 'plan_editor');
        $this->assertSame('plan_editor', $host->role($request));
    }

    private function plan(int $owner_id): Plan
    {
        // Strings, as attributes can arrive from the database: the helper must cast before comparing.
        return (new Plan())->forceFill(['id' => 8122, 'user_id' => (string) $owner_id]);
    }

    private function requestFor(?int $user_id, ?string $role): Request
    {
        $request = Request::create('/api/plans/8122/localizations?plan_role=plan_admin', 'GET');
        $request->setUserResolver(function () use ($user_id) {
            return $user_id === null ? null : (object) ['id' => (string) $user_id];
        });
        if ($role !== null) {
            $request->attributes->set(PlanLocalizationsController::ROLE_ATTRIBUTE, $role);
        }
        return $request;
    }

    /** @test */
    public function theDenyHelperLetsAnEditorOnlyAtTheirOwnPlan()
    {
        $host = $this->guardHost();
        $denied = ['status_code' => 403, 'message' => 'This user can only edit their own plans.'];

        $this->assertNull($host->deny($this->requestFor(1921466, 'plan_editor'), $this->plan(1921466)));
        $this->assertSame($denied, $host->deny($this->requestFor(1921466, 'plan_editor'), $this->plan(1920998)));
        $this->assertNull($host->deny($this->requestFor(1921467, 'plan_admin'), $this->plan(1920998)));
    }

    /** @test */
    public function theDenyHelperFailsClosedWithoutARoleOrAUser()
    {
        $host = $this->guardHost();
        $denied = ['status_code' => 403, 'message' => 'This user can only edit their own plans.'];

        // The guard did not run (no attribute), though the query string claims plan_admin.
        $this->assertSame($denied, $host->deny($this->requestFor(1921466, null), $this->plan(1921466)));
        // No token user, even with a stored admin role.
        $this->assertSame($denied, $host->deny($this->requestFor(null, 'plan_admin'), $this->plan(1921466)));
    }

    /**
     * Callers write if ($deny = $this->denyUnlessCanEditPlan(…)) { return $deny; }, so the refusal must never be falsy,
     * even where replyWithError() returns [] (a v2 request, which these v4-only routes never get).
     *
     * @test
     */
    public function theDenyHelperNeverFailsOpenOnAnEmptyErrorReply()
    {
        $host = $this->guardHost();
        $host->empty_error_reply = true;

        $deny = $host->deny($this->requestFor(1921466, 'plan_editor'), $this->plan(1920998));

        // An object, so the caller's if ($deny = …) sees a refusal.
        $this->assertInstanceOf(JsonResponse::class, $deny);
        $this->assertSame(403, $deny->getStatusCode());
    }
}
