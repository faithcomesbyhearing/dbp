<?php

namespace App\Services\Plans;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleVerse;
use App\Models\Plan\Plan;
use App\Models\Playlist\PlaylistItems;
use App\Support\AccessGroupsCollection;
use Illuminate\Support\Facades\DB;

/**
 * Loads and caches what PlanCompatibilityCalculator needs for POST /plans/compatibility (API task 102553): a plan's
 * items, a Bible's visible filesets and each fileset's pieces. All content reads are on connection dbp and the plan
 * on dbp_users; nothing joins across the two.
 *
 * Caches (both a day, design section 8): plan_compat_bible_filesets per Bible and access groups, holding the visible
 * filesets of every counted type whatever the request's options; plan_compat_fileset per hash_id and updated_at,
 * holding the pieces. Plan items and results are not cached.
 */
class PlanCompatibilityService
{
    private $plan_service;

    public function __construct()
    {
        $this->plan_service = new PlanService();
    }

    /**
     * A plan's items, loaded exactly as Plan Details loads them with no user: soft-deleted plans and playlists are left
     * out, and so are the items Playlist::findWithFollowersByUserAndIds filters out by fileset tag (in practice the
     * opus ones), so the items always match Plan Details.
     *
     * @return array|null null when there is no such plan; else a list of items
     *                    (id, book_id, chapter_start, chapter_end, verse_start, verse_end)
     */
    public function getPlanItems(int $plan_id) : ?array
    {
        $plan = Plan::getWithDaysAndUserById($plan_id);
        if (!$plan) {
            return null;
        }

        $this->plan_service->setPlaylistItemsForEachPlaylist($plan, 0);

        $items = [];
        foreach ($plan->days as $day) {
            // Set as an attribute by setPlaylistItemsForEachPlaylist(); reading $day->playlist when it was not set
            // would lazy-load the relation, without the item filter.
            $playlist = $day->getAttributes()['playlist'] ?? null;
            if (!$playlist) {
                continue;
            }
            foreach ($playlist->items as $item) {
                $items[] = [
                    'id'            => (int) $item->id,
                    'book_id'       => $item->book_id,
                    'chapter_start' => $item->chapter_start,
                    'chapter_end'   => $item->chapter_end,
                    'verse_start'   => $item->verse_start,
                    'verse_end'     => $item->verse_end,
                ];
            }
        }

        return $items;
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
                return $bible->filesetsWithoutMeta()
                    ->isContentAvailable($access_group_ids)
                    ->conditionToExcludeOldTextFormat()
                    ->conditionToExcludeOldDA16Format()
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
                    ->values()
                    ->all();
            }
        );
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
     * null for "to the end of the chapter". Cached per hash_id and updated_at; never null, so an empty fileset caches [].
     */
    public function getPieces(array $fileset) : array
    {
        return cacheRemember(
            'plan_compat_fileset',
            [$fileset['hash_id'], $fileset['updated_at']],
            now()->addDay(),
            function () use ($fileset) {
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
     * text_plain: per chapter, its lowest and highest verse (verse_end counts for combined verses such as "1-2").
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
                DB::raw('MIN(bible_verses.verse_sequence) AS verse_min'),
                DB::raw(
                    'GREATEST(MAX(bible_verses.verse_sequence),'
                    . ' COALESCE(MAX(CAST(bible_verses.verse_end AS UNSIGNED)), 0)) AS verse_max'
                ),
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
     * chapter stream kept over the legacy clips of the same chapter.
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
            ->get()
            ->map(function ($row) {
                return $row->getAttributes();
            })
            ->all();
    }

    /**
     * text_plain rows (book_id, chapter, verse_min, verse_max) as pieces: one per chapter, never open-ended. A combined
     * verse is inside its chapter's piece, so it never makes an item inexact (R21, verseRows()).
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
