<?php

namespace App\Services\Plans;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleVerse;
use App\Models\Plan\Plan;
use App\Models\Playlist\PlaylistItems;
use App\Support\AccessGroupsCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Loads and caches what PlanCompatibilityCalculator needs for POST /plans/compatibility (API task 102553): a plan's
 * items, a Bible's visible filesets and each fileset's pieces. All content reads are on connection dbp and the plan
 * on dbp_users; nothing joins across the two.
 *
 * Caches (design section 8; revision 6, the speed design's section 5):
 *  - plan_compat_bible_filesets, a day, per Bible and access groups: the visible filesets of every counted type
 *    whatever the request's options;
 *  - plan_compat_superseded, until Monday (cacheUntilMonday()), per hash_id and updated_at: whether the old-format
 *    rules drop a candidate fileset (getVisibleFilesets()). It doesn't depend on the key;
 *  - plan_compat_fileset_v2, until Monday, per hash_id and updated_at: the pieces (renamed from plan_compat_fileset
 *    when the plain text's last verse changed, so old and new values never share a key);
 *  - plan_compat_plan_bible_text, a day, per plan item fileset_id: the fileset row of the plan's own Bible's plain
 *    text, whose pieces then come from plan_compat_fileset_v2 (getPlanBibleLastVerses(); design R28).
 * Plan items and results are not cached. A change that bumps a fileset's updated_at shows within a day, through the
 * visible list; any other content change after the next Monday 17:00 UTC. A newer copy loaded or archived (another
 * fileset's row, not the candidate's) can take up to a day more: a visible list built just before a verdict expires
 * keeps that verdict for its day (review L2-1), so Monday's changes show by Tuesday 18:00 UTC at the latest.
 */
class PlanCompatibilityService
{
    /**
     * Content reaches prod's database in a migration on Monday mornings, so content cached until Monday 17:00 UTC
     * (10:00 or 11:00 Mountain) is as fresh as a day's cache (Sam, 2026-11-17).
     */
    private const WEEKLY_EXPIRY_HOUR_UTC = 17;
    /** Entries expire up to an hour after it, each at its own second, so they don't all expire at once. */
    private const WEEKLY_EXPIRY_SPREAD_SECONDS = 3600;

    private $plan_service;

    /**
     * What this call loaded, for the log line (PlanCompatibilityController::logContext()). A cache closure runs only
     * on a miss, so built against asked tells cold from warm.
     */
    private $counts = ['pieces' => 0, 'pieces_built' => 0, 'old_format_checks' => 0];

    public function __construct()
    {
        $this->plan_service = new PlanService();
    }

    /**
     * @return array pieces (filesets whose pieces were asked for), pieces_built (of those, built rather than read from
     *               the cache) and old_format_checks (old-format verdicts worked out rather than read from the cache)
     */
    public function counts() : array
    {
        return $this->counts;
    }

    /**
     * The expiry of a weekly cache entry: the next Monday 17:00 UTC, rolled on a week when less than an hour is left
     * (so an entry stored just before it lasts the week), plus up to an hour that depends on the key. Never less than
     * an hour away: Cache::put() deletes an entry whose time is up. An Illuminate\Support\Carbon, the type
     * cacheRemember() takes; UTC, so the time of year changes nothing.
     */
    public static function cacheUntilMonday(string $key) : Carbon
    {
        $now = Carbon::now('UTC');
        // This ISO week's Monday (dayOfWeekIso: Monday 1 to Sunday 7).
        $expiry = $now->copy()->subDays($now->dayOfWeekIso - 1)->setTime(self::WEEKLY_EXPIRY_HOUR_UTC, 0);
        if ($expiry->lt($now->copy()->addHour())) {
            $expiry->addWeek();
        }

        return $expiry->addSeconds(crc32($key) % self::WEEKLY_EXPIRY_SPREAD_SECONDS);
    }

    /**
     * A plan's items, loaded exactly as Plan Details loads them with no user: soft-deleted plans and playlists are left
     * out, and so are the items Playlist::findWithFollowersByUserAndIds filters out by fileset tag (in practice the
     * opus ones), so the items always match Plan Details.
     *
     * An item that names its last verse also gets plan_bible_last_verse: the last verse of its last chapter in the
     * plan's own Bible, which a Bible that doesn't number that chapter uses to tell PSA 23:1-6 (the whole chapter)
     * from MAT 13:1-23 (part of one) (PlanCompatibilityCalculator::itemPart(); Sam, 2026-11-16). Looked up only for
     * such items, so a plan of whole chapters (every featured plan on dev, 2026-11-16) costs nothing more.
     *
     * @return array|null null when there is no such plan; else a list of items
     *                    (id, book_id, chapter_start, chapter_end, verse_start, verse_end, and plan_bible_last_verse
     *                    for an item with a verse_end: an int, or null when the plan's own Bible doesn't give one)
     */
    public function getPlanItems(int $plan_id) : ?array
    {
        $plan = Plan::getWithDaysAndUserById($plan_id);
        if (!$plan) {
            return null;
        }

        $this->plan_service->setPlaylistItemsForEachPlaylist($plan, 0);

        $items = [];
        $last_verses_by_fileset = [];
        foreach ($plan->days as $day) {
            // Set as an attribute by setPlaylistItemsForEachPlaylist(); reading $day->playlist when it was not set
            // would lazy-load the relation, without the item filter.
            $playlist = $day->getAttributes()['playlist'] ?? null;
            if (!$playlist) {
                continue;
            }
            foreach ($playlist->items as $item) {
                $entry = [
                    'id'            => (int) $item->id,
                    'book_id'       => $item->book_id,
                    'chapter_start' => $item->chapter_start,
                    'chapter_end'   => $item->chapter_end,
                    'verse_start'   => $item->verse_start,
                    'verse_end'     => $item->verse_end,
                ];
                if (PlanCompatibilityCalculator::parseItem($entry)['verse_end'] !== null) {
                    $fileset_id = (string) $item->fileset_id;
                    $last_verses_by_fileset[$fileset_id] ??= $this->getPlanBibleLastVerses($fileset_id);
                    $entry['plan_bible_last_verse'] = self::planBibleLastVerse(
                        $entry,
                        $last_verses_by_fileset[$fileset_id]
                    );
                }
                $items[] = $entry;
            }
        }

        return $items;
    }

    /**
     * The last verse of an item's last chapter in the plan's own Bible.
     *
     * @param array $item as getPlanItems() builds it
     * @param array $last_verses from getPlanBibleLastVerses()
     */
    public static function planBibleLastVerse(array $item, array $last_verses) : ?int
    {
        $parsed = PlanCompatibilityCalculator::parseItem($item);

        return $last_verses[$parsed['book_id']][$parsed['chapter_end']] ?? null;
    }

    /**
     * Each chapter's last verse in the plan's own Bible: the plain text of the Bible of the fileset the item was
     * written against (playlist_items.fileset_id), found as Plan Details finds an item's verse text and verse count
     * (BibleFileset::filesetTypeTextPlainAssociated(), PlaylistItems::getVerseText() and calculateVerses()). Like
     * them, it reads that text whatever the key may see: only where its chapters end is used, and nothing of it is
     * returned. The text's pieces come from getPieces(), the same cache entry as when that Bible is itself checked.
     *
     * @return array book_id => chapter => verse; [] when the fileset, its Bible or a plain text is missing
     */
    public function getPlanBibleLastVerses(string $fileset_id) : array
    {
        if ($fileset_id === '') {
            return [];
        }
        $text = cacheRemember(
            'plan_compat_plan_bible_text',
            [$fileset_id],
            now()->addDay(),
            function () use ($fileset_id) {
                // filesetTypeTextPlainAssociated() reads the Bible of whichever row with this id comes first, without
                // checking that it has one; an id can be shared by several filesets, so every one of them must.
                $filesets = BibleFileset::select(['hash_id', 'id'])->with('bible')->where('id', $fileset_id)->get();
                if ($filesets->isEmpty() || $filesets->contains(function ($fileset) {
                    return !$fileset->bible->first();
                })) {
                    return [];
                }
                $text = BibleFileset::filesetTypeTextPlainAssociated($fileset_id);

                return $text ? self::filesetFromRow($text->getAttributes()) : [];
            }
        );

        return empty($text) ? [] : self::lastVersesOfPieces($this->getPieces($text));
    }

    /**
     * The highest numbered end in each chapter of these pieces.
     *
     * @param array $pieces_by_book from getPieces()
     *
     * @return array book_id => chapter => verse
     */
    public static function lastVersesOfPieces(array $pieces_by_book) : array
    {
        $last_verses = [];
        foreach ($pieces_by_book as $book => $pieces) {
            foreach ($pieces as [, , $chapter_end, $verse_end]) {
                if ($verse_end !== null) {
                    $last_verses[$book][$chapter_end] = max($last_verses[$book][$chapter_end] ?? 0, (int) $verse_end);
                }
            }
        }

        return $last_verses;
    }

    /**
     * The plan of each of these playlist items, deleted plans and playlists included. Deleting a plan soft-deletes its
     * playlists but deletes its plan_days rows, so the playlist's own plan_id is the link left. That column was
     * backfilled from plan_days in 2020-05 (migration add_plan_to_playlist_table), after which a plan deleted earlier
     * had no days left: its playlists have plan_id 0, and its items still get the 422 (243 such plans on dev,
     * 2026-11-13; review L4-1). Read only for asked ids
     * that are in none of the loaded plans, to tell an item of a requested plan that is deleted (ignored, design R24)
     * from an item of no requested plan (a 422). Deleted playlists count: the joined user_playlists gets no model scope
     * (Playlist's SoftDeletes applies only to queries on Playlist itself), and PlaylistItems has no soft deletes.
     *
     * @param int[] $playlist_item_ids at most PlanCompatibilityController::MAX_PLAYLIST_ITEMS
     *
     * @return array playlist_item_id => plan_id; an id with no item, or whose playlist row is gone, is absent, and a
     *               playlist of no plan gives 0, which is never a requested plan id
     */
    public function getPlanIdsOfItems(array $playlist_item_ids) : array
    {
        return PlaylistItems::query()
            ->toBase()
            ->join('user_playlists', 'user_playlists.id', 'playlist_items.playlist_id')
            ->whereIn('playlist_items.id', $playlist_item_ids)
            ->pluck('user_playlists.plan_id', 'playlist_items.id')
            ->map(function ($plan_id) {
                return (int) $plan_id;
            })
            ->all();
    }

    /**
     * The Bible's filesets the key may see, of every type, Opus and video included: the options and the types the call
     * counts are applied later (PlanCompatibilityCalculator::keepsFileset()), so the cached list depends on neither,
     * and a Bible visible only through types the call does not count still gets an entry, with everything missing,
     * rather than going to meta.not_found with the Bibles the key can't see. The same filters as Bible details
     * (BiblesController::loadBibleForShow): content loaded, not archived, permitted to the access groups, and no old
     * six-character text or old 16 kbps audio where the newer copy exists.
     *
     * The old-format rules run only on the few rows they can drop (isOldFormatCandidate()), one at a time, through the
     * model's own scopes (isSupersededOldFormat()): run on the whole list, they compared ids across every fileset once
     * per visible fileset, most of a cold call's time (revision 6, speed design T1). The cached list is the same as
     * before, so entries written before it stay valid.
     *
     * @return array list of filesets (see filesetFromRow()); [] for an unknown Bible or one with nothing visible
     */
    public function getVisibleFilesets(string $bible_id, AccessGroupsCollection $access_group_ids) : array
    {
        // Looked up outside the cache, so an unknown id (the caller's own text) is never cached.
        $bible = Bible::select('id')->find($bible_id);
        if (!$bible) {
            return [];
        }

        return cacheRemember(
            'plan_compat_bible_filesets',
            [$bible_id, $access_group_ids->toString()],
            now()->addDay(),
            function () use ($bible, $access_group_ids) {
                $filesets = $bible->filesetsWithoutMeta()
                    ->isContentAvailable($access_group_ids)
                    ->select([
                        'bible_filesets.hash_id',
                        'bible_filesets.id',
                        'bible_filesets.set_type_code',
                        'bible_filesets.set_size_code',
                        'bible_filesets.segmentation_type',
                        'bible_filesets.updated_at',
                    ])
                    ->selectRaw(
                        'EXISTS (SELECT 1 FROM bible_fileset_tags AS opus_tag'
                        . ' WHERE opus_tag.hash_id = bible_filesets.hash_id'
                        . " AND opus_tag.name = 'codec' AND opus_tag.description = 'opus') AS has_opus_codec_tag"
                    )
                    ->orderBy('bible_filesets.hash_id')
                    ->get()
                    ->map(function ($row) {
                        // getAttributes(): hash_id and updated_at are hidden, and toArray() would drop them.
                        return self::filesetFromRow($row->getAttributes());
                    })
                    ->all();

                return array_values(array_filter($filesets, function (array $fileset) {
                    return !self::isOldFormatCandidate($fileset) || !$this->isSupersededOldFormat($fileset);
                }));
            }
        );
    }

    /**
     * Whether the old-format rules could drop this fileset: the first condition each of their subqueries puts on the
     * fileset itself, stated a second time (speed design T1; reference/findings.md: a change to those rules must change
     * this too). BibleFileset::scopeConditionToExcludeOldTextFormat() drops only text_plain with an id of
     * OLD_TEXT_PLAIN_FILESET_LENGTH characters, and scopeConditionToExcludeOldDA16Format() only audio or audio_drama
     * with an id of V1_AUDIO_16_KBPS_FILESET_LENGTH. MySQL counts characters (CHAR_LENGTH) and compares the types
     * without regard to case or trailing spaces, and so does this. A row it calls a candidate needlessly only costs a
     * query; one it missed would be kept wrongly, so it errs on the side of more.
     */
    public static function isOldFormatCandidate(array $fileset) : bool
    {
        $type = strtolower(rtrim((string) ($fileset['set_type_code'] ?? '')));
        $length = mb_strlen((string) ($fileset['id'] ?? ''), 'UTF-8');

        return ($type === BibleFileset::TYPE_TEXT_PLAIN && $length === BibleFileset::OLD_TEXT_PLAIN_FILESET_LENGTH)
            || (in_array($type, [BibleFileset::TYPE_AUDIO, BibleFileset::TYPE_AUDIO_DRAMA], true)
                && $length === BibleFileset::V1_AUDIO_16_KBPS_FILESET_LENGTH);
    }

    /**
     * Whether the old-format rules drop this candidate: an old six-character text where the ten-character text exists,
     * or old 16 kbps audio where the -opus16 copy does. Worked out by the model's own scopes on this one row, so the
     * verdict is theirs by construction. It is about the fileset's own row and other filesets, not the key, so it is
     * cached per hash_id and updated_at (plan_compat_superseded, until Monday; a change to the row itself, its size
     * say, shows within a day, as the pieces do) and paid once a week, whichever key asks. 1 when dropped, 0 when
     * kept.
     *
     * @param array $fileset from filesetFromRow(): hash_id and updated_at
     */
    private function isSupersededOldFormat(array $fileset) : bool
    {
        $hash_id = $fileset['hash_id'];
        $superseded = cacheRemember(
            'plan_compat_superseded',
            [$hash_id, $fileset['updated_at']],
            self::cacheUntilMonday('plan_compat_superseded:' . $hash_id),
            function () use ($hash_id) {
                $this->counts['old_format_checks']++;

                return BibleFileset::where('bible_filesets.hash_id', $hash_id)
                    ->conditionToExcludeOldTextFormat()
                    ->conditionToExcludeOldDA16Format()
                    ->exists() ? 0 : 1;
            }
        );

        return (int) $superseded === 1;
    }

    /**
     * One visible-fileset row as the call keeps it. Opus is found by either test, since neither finds every Opus
     * fileset on its own: the id contains "-opus", or the fileset has the codec tag "opus".
     */
    public static function filesetFromRow(array $row) : array
    {
        $id = (string) $row['id'];

        return [
            'hash_id'           => (string) $row['hash_id'],
            'id'                => $id,
            'set_type_code'     => (string) $row['set_type_code'],
            'set_size_code'     => $row['set_size_code'] ?? null,
            'segmentation_type' => $row['segmentation_type'] ?? null,
            'updated_at'        => (string) ($row['updated_at'] ?? ''),
            'is_opus'           => stripos($id, '-opus') !== false || !empty($row['has_opus_codec_tag']),
        ];
    }

    /**
     * A fileset's pieces by book: book_id => list of [chapter_start, verse_start, chapter_end, verse_end], verse_end
     * null for "to the end of the chapter". Cached per hash_id and updated_at until Monday (cacheUntilMonday()); never
     * null, so an empty fileset caches []. The only place a pieces value is built, for the Bibles checked and for the
     * plan's own Bible alike, so one key always holds one kind of value (speed design T2).
     */
    public function getPieces(array $fileset) : array
    {
        $this->counts['pieces']++;

        return cacheRemember(
            'plan_compat_fileset_v2',
            [$fileset['hash_id'], $fileset['updated_at']],
            self::cacheUntilMonday('plan_compat_fileset_v2:' . $fileset['hash_id']),
            function () use ($fileset) {
                $this->counts['pieces_built']++;
                // The fileset's own first Bible, through the same relation the fileset endpoints use
                // (optional($fileset->bible)->first(), BibleFileSetsTrait), so the pieces depend on the hash_id alone,
                // whichever Bible asked. The relation needs only the hash_id, so the fileset row is not read again.
                $bible = (new BibleFileset())->forceFill(['hash_id' => $fileset['hash_id']])->bible->first();
                if (!$bible) {
                    return [];
                }

                return $fileset['set_type_code'] === BibleFileset::TYPE_TEXT_PLAIN
                    ? self::piecesFromVerseRows($this->verseRows($fileset['hash_id'], $bible->id))
                    : self::piecesFromFileRows($this->fileRows($fileset['hash_id'], $fileset['set_type_code'], $bible->id));
            }
        );
    }

    /**
     * text_plain: per chapter, its lowest and highest verse start, read from the index alone (unique_text_reference:
     * hash_id, book_id, chapter, verse_start), never the rows. So a chapter that ends on a combined verse ("25-26")
     * ends at 25: Sam chose speed over exactness (2026-11-17, revision 6, P1). As the text sets a chapter's last verse
     * for the whole Bible (PlanCompatibilityCalculator::lastVerses()), an item starting on 26 there has no verse in
     * that Bible, in any format, while one ending there still reads to the end of the chapter.
     *
     * One range per chapter, so the engine never sees inside a combined verse: an item that starts or ends inside one
     * (MAT 1:1-6 where MAT 1:2-11 is one verse) is exact on purpose (Sam, 2026-11-12, design R21; see
     * PlanCompatibilityCalculator::CUT_ANYWHERE_SET_TYPES). text_usx, text_json and text_format come from bible_files
     * rows (fileRows()), which run from verse 1 to the end of their chapters.
     */
    private function verseRows(string $hash_id, string $bible_id) : array
    {
        return BibleVerse::query()
            ->toBase()
            ->join('bible_books', function ($join) use ($bible_id) {
                $join->on('bible_books.book_id', 'bible_verses.book_id')->where('bible_books.bible_id', $bible_id);
            })
            ->where('bible_verses.hash_id', $hash_id)
            ->groupBy('bible_verses.book_id', 'bible_verses.chapter')
            ->select([
                'bible_verses.book_id',
                'bible_verses.chapter',
                DB::raw('MIN(CAST(bible_verses.verse_start AS UNSIGNED)) AS verse_min'),
                DB::raw('MAX(CAST(bible_verses.verse_start AS UNSIGNED)) AS verse_max'),
            ])
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();
    }

    /**
     * Every other type: the rows the chapter and bulk endpoints select (BibleFile::scopeByHashIdJoinBooks: the book
     * must be in the Bible), with the same type filter (video: the .m3u8 streams only) and, for video, the newer
     * chapter stream kept over the legacy clips of the same chapter. Read as plain rows (toBase()), not models: the
     * same columns and values as a model's raw attributes, without building one per file.
     */
    private function fileRows(string $hash_id, string $set_type_code, string $bible_id) : array
    {
        return BibleFile::query()
            ->filterBySetTypeCode($set_type_code)
            ->when($set_type_code === BibleFileset::TYPE_VIDEO_STREAM, function ($query) {
                $query->prioritizeNewVideoFormat();
            })
            ->where('bible_files.hash_id', $hash_id)
            ->join('bible_books', function ($join) use ($bible_id) {
                $join->on('bible_books.book_id', 'bible_files.book_id')->where('bible_books.bible_id', $bible_id);
            })
            ->join('books', 'books.id', 'bible_files.book_id')
            ->select([
                'bible_files.book_id',
                'bible_files.chapter_start',
                'bible_files.chapter_end',
                'bible_files.verse_start',
                'bible_files.verse_end',
                'bible_files.verse_sequence',
            ])
            ->toBase()
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();
    }

    /**
     * text_plain rows (book_id, chapter, verse_min, verse_max) as pieces: one per chapter, never open-ended. A combined
     * verse is inside its chapter's piece, so it never makes an item inexact (R21, verseRows()); one that ends the
     * chapter ends it at its first number (P1).
     */
    public static function piecesFromVerseRows(array $rows) : array
    {
        $pieces = [];
        foreach ($rows as $row) {
            $chapter = (int) $row['chapter'];
            $pieces[$row['book_id']][] = [$chapter, (int) $row['verse_min'], $chapter, (int) $row['verse_max']];
        }

        return $pieces;
    }

    /**
     * bible_files rows as pieces. The start is verse_sequence, else the leading number of verse_start (it can be
     * "2b"), as BibleFileSetsTrait builds stream URLs; a missing or backwards chapter_end is the chapter_start; an
     * empty or 0 verse_end means to the end of the chapter. Rows without a chapter can't be placed and are skipped.
     */
    public static function piecesFromFileRows(array $rows) : array
    {
        $pieces = [];
        foreach ($rows as $row) {
            if (!isset($row['chapter_start'])) {
                continue;
            }
            $chapter_start = (int) $row['chapter_start'];
            $chapter_end = isset($row['chapter_end']) ? max($chapter_start, (int) $row['chapter_end']) : $chapter_start;
            $verse_start = isset($row['verse_sequence'])
                ? (int) $row['verse_sequence']
                : (PlanCompatibilityCalculator::verseNumber($row['verse_start'] ?? null) ?? 1);

            $pieces[$row['book_id']][] = [
                $chapter_start,
                $verse_start,
                $chapter_end,
                PlanCompatibilityCalculator::verseNumber($row['verse_end'] ?? null),
            ];
        }

        return $pieces;
    }
}
