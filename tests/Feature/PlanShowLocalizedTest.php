<?php

namespace Tests\Feature;

use App\Models\Plan\PlanLocalization;
use App\Transformers\PlanDayPlaylistItemsTransformer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit coverage for GET /plans/{plan_id}?localized=true (v4_internal_plans.show), API task 104292: how
 * PlanDayPlaylistItemsTransformer shows the plans_localization row that show() looked up for the app locale.
 *
 *  - Off (show() passes localized=false): no `description` or `localization` key, the plan's own name and thumbnail,
 *    and the key order of today's response, even if a row were passed.
 *  - On with a row: the row's name, its thumbnail unless it has none, its description or null, and `localization`
 *    {id, iso, alt_locale, bible_id} as stored, right after `thumbnail`.
 *  - On without a row: the plan's own name and thumbnail, both keys present and null.
 *  - Every other key is the same as with the flag off.
 *
 * Which row the database picks, the 422s and the responses themselves are covered by
 * tests/Integration/PlanShowLocalizedTest.php; parseAppLocale() and the scope's SQL by
 * tests/Feature/PlanLocalizedListTest.php (API task 102556).
 *
 * Runs without a database or the Laravel container. The transformer is built without its constructor, which reads
 * `v` from the request (core rule 16), and is fed a plain object in place of the Plan model. The row is a real
 * PlanLocalization filled with setRawAttributes(), so `??` and the id cast go through Eloquent as in production.
 *
 * @group plans
 */
class PlanShowLocalizedTest extends TestCase
{
    /** Today's keys, in today's order, with show_details off and no other flag. */
    private const PLAIN_KEYS = [
        'id', 'name', 'thumbnail', 'featured', 'suggested_start_date', 'draft', 'created_at', 'updated_at',
        'start_date', 'percentage_completed', 'days', 'total_users', 'user',
    ];

    private const LOCALIZED_KEYS = [
        'id', 'name', 'thumbnail', 'description', 'localization', 'featured', 'suggested_start_date', 'draft',
        'created_at', 'updated_at', 'start_date', 'percentage_completed', 'days', 'total_users', 'user',
    ];

    /**
     * Off: today's response, key for key.
     *
     * @test
     */
    public function flagOffLeavesTheResponseAsItWas()
    {
        $result = $this->transform(['localized' => false, 'localization' => null]);

        $this->assertSame(self::PLAIN_KEYS, array_keys($result));
        $this->assertSame('Bible in a Year', $result['name']);
        $this->assertSame('https://example.org/bibleinayear2.png', $result['thumbnail']);
    }

    /**
     * Off is off whatever else is passed: a row never changes the name or adds a key without the flag, and a call
     * that passes neither param (as before this change) is the same.
     *
     * @test
     */
    public function flagOffIgnoresARow()
    {
        $plain = $this->transform([]);

        $this->assertSame(self::PLAIN_KEYS, array_keys($plain));
        $this->assertSame($plain, $this->transform(['localized' => false, 'localization' => $this->row(['description' => 'Texte'])]));
    }

    /**
     * AC1: a row with a thumbnail and no description (dev row 4, 209 eng/NULL). The id comes back as an int even when
     * the driver gives a string.
     *
     * @test
     */
    public function aRowGivesItsNameThumbnailAndLocalization()
    {
        $result = $this->transform([
            'localized' => true,
            'localization' => $this->row([
                'id' => '4',
                'iso' => 'eng',
                'alt_locale' => null,
                'name' => 'Bible in a Year (en)',
                'thumbnail' => 'https://example.org/en.png',
                'description' => null,
                'bible_id' => 'ENGESV',
            ]),
        ]);

        $this->assertSame(self::LOCALIZED_KEYS, array_keys($result));
        $this->assertSame('Bible in a Year (en)', $result['name']);
        $this->assertSame('https://example.org/en.png', $result['thumbnail']);
        $this->assertArrayHasKey('description', $result);
        $this->assertNull($result['description']);
        $this->assertSame(['id' => 4, 'iso' => 'eng', 'alt_locale' => null, 'bible_id' => 'ENGESV'], $result['localization']);
    }

    /**
     * AC1, AC2: a row with a description and no thumbnail (r1, 209 fra/NULL) keeps the plan's thumbnail; a row with
     * an alt_locale shows it as stored.
     *
     * @test
     */
    public function aRowWithoutThumbnailKeepsThePlansThumbnail()
    {
        $result = $this->transform([
            'localized' => true,
            'localization' => $this->row([
                'id' => 5,
                'iso' => 'fra',
                'alt_locale' => null,
                'name' => 'La Bible en un an',
                'thumbnail' => null,
                'description' => 'Toute la Bible en un an.',
                'bible_id' => 'FRALSG',
            ]),
        ]);

        $this->assertSame('La Bible en un an', $result['name']);
        $this->assertSame('https://example.org/bibleinayear2.png', $result['thumbnail']);
        $this->assertSame('Toute la Bible en un an.', $result['description']);
        $this->assertSame(['id' => 5, 'iso' => 'fra', 'alt_locale' => null, 'bible_id' => 'FRALSG'], $result['localization']);

        $result = $this->transform([
            'localized' => true,
            'localization' => $this->row(['id' => 6, 'iso' => 'fra', 'alt_locale' => 'fr-CA', 'bible_id' => null]),
        ]);
        $this->assertSame(['id' => 6, 'iso' => 'fra', 'alt_locale' => 'fr-CA', 'bible_id' => null], $result['localization']);
    }

    /**
     * AC4: no row in the language. The plan keeps its own name and thumbnail; both keys are present and null.
     *
     * @test
     */
    public function noRowKeepsThePlansOwnValuesWithNullKeys()
    {
        $result = $this->transform(['localized' => true, 'localization' => null]);

        $this->assertSame(self::LOCALIZED_KEYS, array_keys($result));
        $this->assertSame('Bible in a Year', $result['name']);
        $this->assertSame('https://example.org/bibleinayear2.png', $result['thumbnail']);
        $this->assertNull($result['description']);
        $this->assertNull($result['localization']);
    }

    /**
     * AC8: localized mode changes name, thumbnail and the two new keys only; days, user and the per-user keys are
     * what they are with the flag off.
     *
     * @test
     */
    public function everyOtherKeyIsUnchanged()
    {
        $plain = $this->transform(['localized' => false, 'localization' => null]);
        $on = $this->transform(['localized' => true, 'localization' => $this->row([])]);
        $none = $this->transform(['localized' => true, 'localization' => null]);

        $changed = ['name' => 0, 'thumbnail' => 0, 'description' => 0, 'localization' => 0];
        $this->assertSame(array_diff_key($plain, $changed), array_diff_key($on, $changed));
        $this->assertSame(array_diff_key($plain, $changed), array_diff_key($none, $changed));
    }

    /**
     * AC9: with include_user_bible and include_completed_at on, every key is there, the new keys stay right after
     * `thumbnail`, and `user_bible` and `completed_at` keep their places.
     *
     * @test
     */
    public function worksWithTheOtherFlags()
    {
        $result = $this->transform([
            'localized' => true,
            'localization' => $this->row([]),
            'include_user_bible' => true,
            'user_bible' => 'ENGNIV',
            'include_completed_at' => true,
            'completed_at_by_day' => [3164 => '2026-10-13T19:37:53.000000Z'],
        ]);

        $this->assertSame(
            [
                'id', 'name', 'thumbnail', 'description', 'localization', 'featured', 'suggested_start_date', 'draft',
                'created_at', 'updated_at', 'start_date', 'percentage_completed', 'user_bible', 'days', 'total_users',
                'user',
            ],
            array_keys($result)
        );
        $this->assertSame('ENGNIV', $result['user_bible']);
        $this->assertSame('2026-10-13T19:37:53.000000Z', $result['days'][0]['completed_at']);
        $this->assertSame(
            ['id', 'playlist_id', 'completed', 'completed_at', 'playlist_is_empty'],
            array_keys($result['days'][0])
        );
    }

    /**
     * A row as show() reads it: the seven columns it selects. Defaults: 209 fra/fr-CA with its own thumbnail.
     */
    private function row(array $attributes): PlanLocalization
    {
        $row = new PlanLocalization();
        $row->setRawAttributes(array_merge([
            'id' => 6,
            'iso' => 'fra',
            'alt_locale' => 'fr-CA',
            'name' => 'La Bible en une année',
            'thumbnail' => 'https://example.org/fr-ca.png',
            'description' => null,
            'bible_id' => 'FRALSG',
        ], $attributes), true);
        $row->exists = true;

        return $row;
    }

    /**
     * Plan 209 as show() passes it, with show_details off: two days, one completed.
     */
    private function transform(array $params): array
    {
        $days = new Collection([
            (object) ['id' => 3164, 'playlist_id' => 14106, 'completed' => true, 'playlist_is_empty' => false],
            (object) ['id' => 3165, 'playlist_id' => 14107, 'completed' => false, 'playlist_is_empty' => false],
        ]);

        $plan = (object) [
            'id' => 209,
            'name' => 'Bible in a Year',
            'thumbnail' => 'https://example.org/bibleinayear2.png',
            'featured' => true,
            'suggested_start_date' => '2030-01-01',
            'draft' => false,
            'created_at' => null,
            'updated_at' => null,
            'start_date' => null,
            'percentage_completed' => null,
            'days' => $days,
            'total_users' => 67,
            'user' => (object) ['id' => 1920998, 'name' => 'FCBH Global Apps'],
        ];

        $transformer = (new ReflectionClass(PlanDayPlaylistItemsTransformer::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(PlanDayPlaylistItemsTransformer::class, 'params');
        $property->setAccessible(true);
        $property->setValue($transformer, $params + ['v' => 4, 'key' => '<KEY>', 'show_details' => false]);

        $result = $transformer->transform($plan);
        $result['days'] = $result['days']->all();

        return $result;
    }
}
