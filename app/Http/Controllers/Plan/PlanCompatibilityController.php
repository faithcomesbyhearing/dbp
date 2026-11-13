<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\APIController;
use App\Services\Plans\PlanCompatibilityCalculator;
use App\Services\Plans\PlanCompatibilityService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * POST /plans/compatibility (API task 102553): how well each Bible can play each plan, per format, for the Hosanna
 * apps' Start Plan, Change Bible, Select a Bible and Passage Not Available screens. Bible.is-private.
 *
 * It changes nothing; it is a POST because it takes two lists, which a JSON body carries as real lists (like
 * POST bibles/filesets/check/types). Plans are user data, so the route has the middleware of Plan Details (APIToken,
 * UserDataAccess, UserDataAuditLog), plus AccessControl because the answer is about content: only filesets the key
 * may see count. The answer holds counts and status words only, never fileset ids, URLs or content.
 *
 * The rules are in PlanCompatibilityCalculator; the data and its caches in PlanCompatibilityService. The request and
 * response contract, with complete samples, is attached to API task 102553.
 */
class PlanCompatibilityController extends APIController
{
    public const MAX_PLANS = 20;
    public const MAX_BIBLES = 100;
    public const MAX_PAIRS = 500;
    public const MAX_ITEM_CHECKS = 200000;
    public const MAX_PLAYLIST_ITEMS = 20;
    private const MAX_BIBLE_ID_LENGTH = 12;

    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new PlanCompatibilityService();
    }

    /**
     * @OA\Post(
     *     path="/plans/compatibility",
     *     tags={"Plans"},
     *     summary="How well each Bible can play each plan",
     *     description="For each plan and Bible (entries in request order: plans, then Bibles), a `status` and item counts for the Bible as a whole, and per format: `text`; `audio` as a whole, then `non_drama` and `drama`; and `video` only with `include_video`. Per item and fileset an item is `exact` (it plays exactly), `approximate` (not exact, but the app can play it, by playing every piece that overlaps or the part of the item that is there: audio for an item of whole chapters, in practice sections; text or a stream with part of the item; and video), `mismatch` (it starts or ends inside a chapter, where the audio can't be cut, whether all of it or only part of it is there) or `missing` (none of it is there); text and video are never `mismatch`, and with `allow_chapter_audio` nothing is. Text is `text_usx`, `text_json`, `text_format` and `text_plain` alike, and on text and streams an item that starts or ends inside a combined verse is exact. Each format takes the best fileset per item, in that order. Audio as a whole is the union of non-drama and drama per item. The Bible, and each playlist item's `status`, takes per item the audio's answer where it isn't missing, else the text's (for the Bible only, with `exact_threshold` met, the better of the two), then with `allow_video_only` the better of that and the video's; video always keeps its own answers. Then the items are counted. The status, the first that applies: `full` (all exact, an empty plan too), `approximate` (nothing mismatch or missing), `mismatch` (nothing missing, something playable), `partial` (something missing, something playable), `incompatible_mismatch` (nothing playable, something mismatch), `incompatible_missing` (all missing). Playable means exact or approximate: for a format, in that format; for the Bible and a playlist item, in any counted format (text, audio, and video with `allow_video_only`), before audio outranks text. Only filesets the key may see count. Unknown or deleted plans, and Bibles unknown or with nothing visible to the key, get no entry and are listed in `meta.not_found`; an asked playlist item of a deleted plan is ignored. The four flags are on only for JSON `true` or the word true (any case).",
     *     operationId="v4_internal_plans.compatibility",
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              required={"plan_ids","bible_ids"},
     *              @OA\Property(property="plan_ids", type="array", minItems=1, maxItems=20, @OA\Items(type="integer", minimum=1), description="1 to 20 plan ids"),
     *              @OA\Property(property="bible_ids", type="array", minItems=1, maxItems=100, @OA\Items(type="string", maxLength=12), description="1 to 100 Bible ids, letters and digits; repeated ids (any case) count once. At most 500 plan and Bible pairs, and 200,000 plan items times Bibles, per call (an item that spans several chapters counts once per chapter)"),
     *              @OA\Property(property="playlist_item_ids", type="array", maxItems=20, @OA\Items(type="integer", minimum=1), description="Up to 20 items of the plans; repeated ids count once, and an empty list is the same as none. Every entry then gets `playlist_items`: the answers for the asked items in its plan, in the order asked (an empty list when none is in its plan). An id that is an item of none of the plans is a 422; one of a requested plan that is deleted is ignored"),
     *              @OA\Property(property="include_video", type="boolean", default=false, description="Adds `video` to every entry's `formats` (and to each playlist item's)"),
     *              @OA\Property(property="include_opus", type="boolean", default=false, description="Counts Opus audio filesets (an id containing `-opus`, or the codec tag `opus`); without it they are left out as if absent. Android sends true; iOS, which can't play Opus yet, leaves it out"),
     *              @OA\Property(property="allow_video_only", type="boolean", default=false, description="Video counts toward the Bible's status and counts and the playlist items' status: each item takes the better of its video's answer and the answer from audio and text. Without it they come from audio and text only: the apps can't play an item from video alone yet. Video's own answers are the same either way. Without include_video it is ignored, with no error"),
     *              @OA\Property(property="allow_chapter_audio", type="boolean", default=false, description="The app can play a verse range from audio that can't be cut there, by playing the whole chapter or every overlapping section: every answer that would be `mismatch` is `approximate`, so `mismatch` and `incompatible_mismatch` never appear"),
     *              @OA\Property(property="exact_threshold", type="integer", minimum=0, maximum=100, default=0, description="A percentage; 0 is off. When the plan's audio as a whole has at least this share of exact items among its exact, approximate and mismatch items (missing ones aren't counted), the Bible's status and items take, per item, the better of text and audio instead of audio outranking text. Where an item has no text, its audio's answer stays. Formats and playlist_items never change")
     *          )
     *     )),
     *     @OA\Response(response=200, description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_internal_plans.compatibility"))
     *     ),
     *     @OA\Response(response=403, description="The API key is not authorized to access user data"),
     *     @OA\Response(response=422, description="A list is missing, empty, too long or malformed; a limit is exceeded; exact_threshold is not 0 to 100; or a playlist item id is malformed or an item of none of the plans (one of a requested plan that is deleted is ignored)")
     * )
     *
     * One schema with nothing referenced in it: openApi:generate drops this unreferenced internal schema, but would keep
     * (and publish) any schema it referenced.
     *
     * @OA\Schema(
     *     schema="v4_internal_plans.compatibility",
     *     type="object",
     *     @OA\Property(property="data", type="array", @OA\Items(
     *         @OA\Property(property="plan_id", type="integer"),
     *         @OA\Property(property="bible_id", type="string", description="As sent"),
     *         @OA\Property(property="status", type="string", enum={"full","approximate","mismatch","partial","incompatible_mismatch","incompatible_missing"}),
     *         @OA\Property(property="items", type="object",
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="exact", type="integer"),
     *             @OA\Property(property="approximate", type="integer"),
     *             @OA\Property(property="mismatch", type="integer"),
     *             @OA\Property(property="missing", type="integer")
     *         ),
     *         @OA\Property(property="formats", type="object", description="Each format, and each audio kind, has a status and items like the entry's",
     *             @OA\Property(property="text", type="object"),
     *             @OA\Property(property="audio", type="object", description="status and items are the union of non_drama and drama per item; then non_drama and drama"),
     *             @OA\Property(property="video", type="object", description="Only with include_video")
     *         ),
     *         @OA\Property(property="playlist_items", type="array", description="Only with playlist_item_ids: the asked items in this entry's plan, in the order asked", @OA\Items(
     *             @OA\Property(property="playlist_item_id", type="integer"),
     *             @OA\Property(property="status", type="string", enum={"full","approximate","mismatch","incompatible_mismatch","incompatible_missing"}),
     *             @OA\Property(property="formats", type="object",
     *                 @OA\Property(property="text", type="string", enum={"exact","approximate","missing"}),
     *                 @OA\Property(property="audio", type="object",
     *                     @OA\Property(property="status", type="string", enum={"full","approximate","incompatible_mismatch","incompatible_missing"}),
     *                     @OA\Property(property="non_drama", type="string", enum={"exact","approximate","mismatch","missing"}),
     *                     @OA\Property(property="drama", type="string", enum={"exact","approximate","mismatch","missing"})
     *                 ),
     *                 @OA\Property(property="video", type="string", enum={"exact","approximate","missing"}, description="Only with include_video")
     *             )
     *         ))
     *     )),
     *     @OA\Property(property="meta", type="object",
     *         @OA\Property(property="not_found", type="object",
     *             @OA\Property(property="plan_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="bible_ids", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function check(Request $request)
    {
        $normalized = self::normalizeRequest([
            'plan_ids'              => checkParam('plan_ids'),
            'bible_ids'             => checkParam('bible_ids'),
            'playlist_item_ids'     => self::playlistItemIdsAsSent(),
            'exact_threshold'       => checkParam('exact_threshold'),
            'include_video'         => checkParam('include_video'),
            'include_opus'          => checkParam('include_opus'),
            'allow_video_only'      => checkParam('allow_video_only'),
            'allow_chapter_audio'   => checkParam('allow_chapter_audio'),
        ]);
        if (!empty($normalized['errors'])) {
            return $this->unprocessable(implode(' ', $normalized['errors']));
        }
        $values = $normalized['values'];

        $access_group_ids = getAccessGroups();

        // The item-check limit is counted as the plans load, so a request over it stops at the plan that crosses it
        // instead of loading every plan first.
        $plans = [];
        $not_found_plan_ids = [];
        $item_checks = 0;
        foreach ($values['plan_ids'] as $plan_id) {
            $items = $this->service->getPlanItems($plan_id);
            if ($items === null) {
                $not_found_plan_ids[] = $plan_id;
                continue;
            }
            $plans[$plan_id] = $items;
            $item_checks += self::itemChecks($items);
            if ($item_checks * count($values['bible_ids']) > self::MAX_ITEM_CHECKS) {
                return $this->unprocessable(sprintf(
                    'The plans have too many items for this many Bibles: more than %d plan items times Bibles. Send fewer Bibles at a time.',
                    self::MAX_ITEM_CHECKS
                ));
            }
        }

        // Every asked item must be in one of the plans; each entry then answers for those in its own plan. An item of a
        // requested plan that is deleted is ignored, like the plan (R24); its plan is looked up only when some asked
        // id is in none of the loaded plans.
        $playlist_item_ids = $values['playlist_item_ids'];
        if ($playlist_item_ids !== null) {
            $unknown = self::unknownPlaylistItemIds($plans, $playlist_item_ids);
            if (!empty($unknown)) {
                $unknown = self::itemsOfNoRequestedPlan(
                    $unknown,
                    $this->service->getPlanIdsOfItems($unknown),
                    $not_found_plan_ids
                );
            }
            if (!empty($unknown)) {
                return $this->unprocessable(sprintf(
                    'These playlist_item_ids are not items of any of the plans: %s.',
                    implode(', ', $unknown)
                ));
            }
        }

        $calculator = new PlanCompatibilityCalculator(
            $values['include_video'],
            $values['include_opus'],
            $values['allow_video_only'],
            $values['exact_threshold'],
            $values['allow_chapter_audio']
        );

        // One Bible at a time, so only one Bible's pieces are held in memory, and only for the books the plans touch.
        $books = self::planBooks($plans);
        $bible_results = [];
        $not_found_bible_ids = [];
        foreach ($values['bible_ids'] as $bible_id) {
            $visible = $this->service->getVisibleFilesets($bible_id, $access_group_ids);
            if (empty($visible)) {
                $not_found_bible_ids[] = $bible_id;
                continue;
            }
            if (empty($plans)) {
                // Every plan is unknown: there is nothing to answer, only meta.not_found to fill.
                continue;
            }

            $filesets = [];
            foreach ($visible as $fileset) {
                if ($calculator->keepsFileset($fileset)) {
                    $fileset['pieces'] = $this->service->getPieces($fileset);
                    $filesets[] = $fileset;
                }
            }

            $bible_results[] = [
                $bible_id,
                self::bibleResults(
                    $calculator,
                    $plans,
                    $calculator->prepareBible($filesets, $books),
                    $playlist_item_ids
                ),
            ];
        }

        return $this->reply([
            'data' => self::entries($plans, $bible_results),
            'meta' => [
                'not_found' => [
                    'plan_ids'  => $not_found_plan_ids,
                    'bible_ids' => $not_found_bible_ids,
                ],
            ],
        ]);
    }

    /**
     * One Bible's answer for every plan. With playlist item ids, each plan's answer carries playlist_items: the asked
     * items in that plan, in the order asked (an item is answered in its own plan, where it was found).
     *
     * @param array $plans plan_id => items
     * @param array $bible from PlanCompatibilityCalculator::prepareBible()
     * @param array|null $playlist_item_ids the asked ids, de-duplicated; null when none was asked for
     *
     * @return array plan_id => the calculator's result
     */
    public static function bibleResults(
        PlanCompatibilityCalculator $calculator,
        array $plans,
        array $bible,
        ?array $playlist_item_ids
    ) : array {
        $results = [];
        foreach ($plans as $plan_id => $items) {
            $results[$plan_id] = $calculator->evaluate($items, $bible, $playlist_item_ids);
        }

        return $results;
    }

    /**
     * The entries in request order, plans then Bibles, each starting with plan_id and bible_id. Bibles come as a list
     * of [bible_id, results], never keyed by id, so an all-digit Bible id stays a string.
     *
     * @param array $plans plan_id => items, in request order
     * @param array $bible_results list of [bible_id, bibleResults()], in request order
     */
    public static function entries(array $plans, array $bible_results) : array
    {
        $entries = [];
        foreach (array_keys($plans) as $plan_id) {
            foreach ($bible_results as [$bible_id, $results]) {
                $entries[] = ['plan_id' => $plan_id, 'bible_id' => $bible_id] + $results[$plan_id];
            }
        }

        return $entries;
    }

    /**
     * Check and normalize the request, every problem at once (the 422 message lists them all).
     *
     * Plan ids are whole numbers of 1 or more (a JSON number or a string of digits), repeated ids counting once. Bible
     * ids are strings of letters and digits, at most 12 characters, repeated ids counting once whatever their case
     * (the first spelling is kept, and echoed). The limits apply to the distinct ids. The four flags are on only for
     * JSON true or the word true in any case, so a release whose checkBoolean() is older behaves the same; anything
     * else, including a list, is off. allow_video_only without include_video is not an error: it adds nothing, as there
     * is no video to count (R26). exact_threshold is 0 to 100, 0 (or absent) meaning off. Absent values arrive as null:
     * checkParam() also returns null for 0, "0", false and an empty list. playlist_item_ids arrives as sent
     * (playlistItemIdsAsSent()), so a 0 or false is rejected as not a list; an empty list, or none, is null in the
     * values. Its ids are whole numbers of 1 or more, at most 20 distinct, repeated ids counting once.
     *
     * @param array $raw plan_ids, bible_ids, playlist_item_ids, exact_threshold, include_video, include_opus,
     *                   allow_video_only, allow_chapter_audio, as checkParam() returns them
     *
     * @return array ['errors' => list of messages, 'values' => the normalized request]
     */
    public static function normalizeRequest(array $raw) : array
    {
        $errors = [];

        $plan_ids = [];
        $raw_plan_ids = $raw['plan_ids'] ?? null;
        if ($raw_plan_ids === null) {
            $errors[] = 'plan_ids is required: a list of 1 to ' . self::MAX_PLANS . ' plan ids.';
        } elseif (!is_array($raw_plan_ids) || !array_is_list($raw_plan_ids)) {
            $errors[] = 'plan_ids must be a list of plan ids.';
        } else {
            $invalid = false;
            // A keyed set, so a long list costs one pass, not one search of the list per id.
            $seen = [];
            foreach ($raw_plan_ids as $value) {
                $plan_id = self::positiveInteger($value);
                if ($plan_id === null) {
                    $invalid = true;
                } elseif (!isset($seen[$plan_id])) {
                    $seen[$plan_id] = true;
                    $plan_ids[] = $plan_id;
                }
            }
            if ($invalid) {
                $errors[] = 'Each plan id must be a whole number of 1 or more.';
            } elseif (count($plan_ids) > self::MAX_PLANS) {
                $errors[] = 'plan_ids takes at most ' . self::MAX_PLANS . ' plan ids.';
            }
        }

        $bible_ids = [];
        $raw_bible_ids = $raw['bible_ids'] ?? null;
        if ($raw_bible_ids === null) {
            $errors[] = 'bible_ids is required: a list of 1 to ' . self::MAX_BIBLES . ' Bible ids.';
        } elseif (!is_array($raw_bible_ids) || !array_is_list($raw_bible_ids)) {
            $errors[] = 'bible_ids must be a list of Bible ids.';
        } else {
            $invalid = false;
            $seen = [];
            foreach ($raw_bible_ids as $value) {
                // ASCII letters and digits only (Laravel's alpha_num:ascii), as every Bible id is: the database's
                // accent-insensitive collation would match ÉNGESV to ENGESV, past this de-duplication and the cache key.
                if (!is_string($value)
                    || preg_match('/\A[A-Za-z0-9]+\z/', $value) !== 1
                    || strlen($value) > self::MAX_BIBLE_ID_LENGTH
                ) {
                    $invalid = true;
                    continue;
                }
                $upper = strtoupper($value);
                if (!isset($seen[$upper])) {
                    $seen[$upper] = true;
                    $bible_ids[] = $value;
                }
            }
            if ($invalid) {
                $errors[] = 'Each Bible id must be letters and digits, at most ' . self::MAX_BIBLE_ID_LENGTH
                    . ' characters.';
            } elseif (count($bible_ids) > self::MAX_BIBLES) {
                $errors[] = 'bible_ids takes at most ' . self::MAX_BIBLES . ' Bible ids.';
            }
        }

        if (empty($errors) && count($plan_ids) * count($bible_ids) > self::MAX_PAIRS) {
            $errors[] = sprintf(
                'At most %d plan and Bible pairs per call: %d plans times %d Bibles were sent.',
                self::MAX_PAIRS,
                count($plan_ids),
                count($bible_ids)
            );
        }

        $playlist_item_ids = null;
        $raw_item_ids = $raw['playlist_item_ids'] ?? null;
        if ($raw_item_ids !== null && $raw_item_ids !== []) {
            if (!is_array($raw_item_ids) || !array_is_list($raw_item_ids)) {
                $errors[] = 'playlist_item_ids must be a list of playlist item ids.';
            } else {
                $invalid = false;
                $seen = [];
                $playlist_item_ids = [];
                foreach ($raw_item_ids as $value) {
                    $item_id = self::positiveInteger($value);
                    if ($item_id === null) {
                        $invalid = true;
                    } elseif (!isset($seen[$item_id])) {
                        $seen[$item_id] = true;
                        $playlist_item_ids[] = $item_id;
                    }
                }
                if ($invalid) {
                    $errors[] = 'Each playlist item id must be a whole number of 1 or more.';
                } elseif (count($playlist_item_ids) > self::MAX_PLAYLIST_ITEMS) {
                    $errors[] = 'playlist_item_ids takes at most ' . self::MAX_PLAYLIST_ITEMS . ' playlist item ids.';
                }
            }
        }

        $exact_threshold = 0;
        if (($raw['exact_threshold'] ?? null) !== null) {
            $threshold = self::positiveInteger($raw['exact_threshold']);
            if ($threshold === null || $threshold > 100) {
                $errors[] = 'exact_threshold must be a whole number from 0 to 100.';
            } else {
                $exact_threshold = $threshold;
            }
        }

        return [
            'errors' => $errors,
            'values' => [
                'plan_ids'              => $plan_ids,
                'bible_ids'             => $bible_ids,
                'playlist_item_ids'     => $playlist_item_ids,
                'exact_threshold'       => $exact_threshold,
                'include_video'         => self::isTrue($raw['include_video'] ?? null),
                'include_opus'          => self::isTrue($raw['include_opus'] ?? null),
                'allow_video_only'      => self::isTrue($raw['allow_video_only'] ?? null),
                'allow_chapter_audio'   => self::isTrue($raw['allow_chapter_audio'] ?? null),
            ],
        ];
    }

    /**
     * A JSON integer of 1 or more, or a string of digits for one without leading zeros; null for anything else
     * (booleans, decimals, signs, leading zeros, numbers too large for an integer).
     */
    private static function positiveInteger($value) : ?int
    {
        if (is_int($value)) {
            return $value >= 1 ? $value : null;
        }
        if (is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1) {
            $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            return $number === false ? null : $number;
        }

        return null;
    }

    /**
     * On only for JSON true or the word true in any case (rule 3: strict on every branch, never a 500 for a list).
     */
    private static function isTrue($value) : bool
    {
        return $value === true || (is_string($value) && strtolower($value) === 'true');
    }

    /**
     * playlist_item_ids as the client sent it. checkParam() reads 0, "0" and false as absent, but they are not a list
     * (a 422), so when checkParam() finds nothing, getAndCheckParam() is asked too. Only those falsy values, an empty
     * list or null reach it, and its return type changes nothing that matters for them (false comes back as 0, still
     * not a list).
     */
    private static function playlistItemIdsAsSent()
    {
        return checkParam('playlist_item_ids') ?? getAndCheckParam('playlist_item_ids');
    }

    /**
     * What a plan's items count toward MAX_ITEM_CHECKS (per Bible): one per chapter each item spans, since the work is
     * done chapter by chapter. A one-chapter item, nearly every item, counts 1; nothing checks the chapter range a
     * client stores, so an item spanning a whole book counts every chapter.
     */
    public static function itemChecks(array $items) : int
    {
        $checks = 0;
        foreach ($items as $item) {
            $parsed = PlanCompatibilityCalculator::parseItem($item);
            $checks += $parsed['chapter_end'] - $parsed['chapter_start'] + 1;
        }

        return $checks;
    }

    /**
     * The books the plans' items are in, as book_id => true (the asked playlist items are among them).
     */
    public static function planBooks(array $plans) : array
    {
        $books = [];
        foreach ($plans as $items) {
            foreach ($items as $item) {
                $books[(string) $item['book_id']] = true;
            }
        }

        return $books;
    }

    /**
     * The asked playlist item ids that are in none of the plans, in the order asked.
     */
    public static function unknownPlaylistItemIds(array $plans, array $playlist_item_ids) : array
    {
        $known = [];
        foreach ($plans as $items) {
            foreach ($items as $item) {
                $known[(int) $item['id']] = true;
            }
        }

        return array_values(array_filter($playlist_item_ids, function ($item_id) use ($known) {
            return !isset($known[$item_id]);
        }));
    }

    /**
     * R24: of the asked ids in none of the loaded plans, those that are not items of a requested plan in
     * meta.not_found (in practice a deleted plan, whose items are ignored like the plan). These are the 422.
     *
     * @param array $unknown_ids from unknownPlaylistItemIds(), in the order asked
     * @param array $plan_id_by_item from PlanCompatibilityService::getPlanIdsOfItems()
     * @param array $not_found_plan_ids the requested plan ids that got no entry
     */
    public static function itemsOfNoRequestedPlan(
        array $unknown_ids,
        array $plan_id_by_item,
        array $not_found_plan_ids
    ) : array {
        $not_found = array_fill_keys($not_found_plan_ids, true);

        return array_values(array_filter($unknown_ids, function ($item_id) use ($plan_id_by_item, $not_found) {
            return !isset($plan_id_by_item[$item_id], $not_found[$plan_id_by_item[$item_id]]);
        }));
    }

    private function unprocessable(string $message)
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY)->replyWithError($message);
    }
}
