<?php

namespace App\Services\Plans;

/**
 * How well a Bible can play a plan: the engine behind POST /plans/compatibility (API task 102553).
 *
 * Pure PHP, with no database and no Laravel, so DB-free unit tests cover every rule
 * (tests/Feature/PlanCompatibilityCalculatorTest.php). PlanCompatibilityService loads and caches the data it needs:
 * the plan's items and, per visible fileset, its pieces.
 *
 * A piece is what the player gets in one go, as the fileset chapter endpoint with expand_sections=true serves it: a
 * bible_files row, except that an audio chapter stored as several files in a non-section fileset is one piece (the
 * bulk endpoint serves the same rows, without that joining for books of several chapters); for text_plain one range
 * per chapter from bible_verses; for text_usx one row per book and for text_json one per chapter, each from verse 1
 * to the end. A piece is
 * [chapter_start, verse_start, chapter_end, verse_end], with verse_end null meaning "to the end of the chapter".
 * Pieces may span chapters.
 *
 * Per item and fileset the answer is exact, approximate, mismatch or missing (the item's end is first clamped to the
 * chapter's last verse in this Bible; a chapter's part is covered when its first and last verse are, gaps inside a
 * chapter not counting):
 *  - missing: none of the item's verses is covered (revision 4; an item covered only in part is inexact, below);
 *  - exact: every part of the item is covered, and on chapter and section pieces, in each chapter of the item a single
 *    piece starts and ends exactly on the item's part of that chapter, with no piece running outside the item.
 *    Cut-anywhere filesets (text, SA streams): covered is exact. A whole chapter on chapter files is exact, and so is a
 *    multi-chapter item of whole chapters;
 *  - otherwise inexact, which inexactAnswer() splits: on audio with chapter or section pieces, mismatch when the item
 *    starts or ends inside a chapter (it can't be cut there), whether it is covered fully or in part, else approximate
 *    (the app plays every piece that overlaps, or the part that is there); on text, streams and video, approximate.
 *    So text and video are never mismatch. With allow_chapter_audio, mismatch is approximate too (revision 5, R23).
 *
 * Then, in this order (design section 11, revision 5):
 *  1. video filesets are dropped unless include_video, and Opus audio filesets unless include_opus, as if absent;
 *  2. each item is judged against each remaining fileset;
 *  3. each format (text, non-drama, drama, video) takes, per item, the best answer of its filesets (exact,
 *     approximate, mismatch, missing);
 *  4. audio as a whole takes, per item, the better of non-drama and drama;
 *  5. the Bible and each requested playlist item: audio outranks text, per item: the audio's answer where it isn't
 *     missing, else the text's. For the Bible only, when the plan's audio as a whole is close enough
 *     (exact_threshold, audioIsCloseEnough()), the better of the text's and the audio's instead. Then, with
 *     allow_video_only, the better of that and the video's (the apps can't play an item from video alone yet; video
 *     isn't outranked). Video's own answers never change;
 *  6. the items are counted, and each status read from its counts (status()). For the Bible and the items,
 *     "playable" comes from any counted format before audio outranks text, so playable text keeps a Bible from
 *     incompatible_... (revision 4).
 * exact_threshold changes nothing else: no fileset's answer, no format, no requested item (its old lower side, which
 * turned a fileset's exact items into mismatch, was dropped in revision 5, R25).
 */
class PlanCompatibilityCalculator
{
    public const EXACT = 'exact';
    public const APPROXIMATE = 'approximate';
    public const MISMATCH = 'mismatch';
    public const MISSING = 'missing';

    public const STATUS_FULL = 'full';
    public const STATUS_APPROXIMATE = 'approximate';
    public const STATUS_MISMATCH = 'mismatch';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_INCOMPATIBLE_MISMATCH = 'incompatible_mismatch';
    public const STATUS_INCOMPATIBLE_MISSING = 'incompatible_missing';

    public const FORMAT_TEXT = 'text';
    public const FORMAT_NON_DRAMA = 'non_drama';
    public const FORMAT_DRAMA = 'drama';
    public const FORMAT_VIDEO = 'video';

    /**
     * The fileset types that count, and the format each one answers for. The four text types count alike (revision 5,
     * R19): the best text fileset wins, though the apps show one text fileset per book (USX, then JSON, then formatted,
     * then plain) with no fallback.
     */
    public const FORMAT_BY_SET_TYPE = [
        'text_usx'           => self::FORMAT_TEXT,
        'text_json'          => self::FORMAT_TEXT,
        'text_plain'         => self::FORMAT_TEXT,
        'text_format'        => self::FORMAT_TEXT,
        'audio'              => self::FORMAT_NON_DRAMA,
        'audio_stream'       => self::FORMAT_NON_DRAMA,
        'audio_drama'        => self::FORMAT_DRAMA,
        'audio_drama_stream' => self::FORMAT_DRAMA,
        'video_stream'       => self::FORMAT_VIDEO,
    ];

    /**
     * Text and SA streams can be cut at any verse, whatever their segmentation, so on them covered is exact. On purpose
     * (Sam, 2026-11-12, design R21), that includes an item that starts or ends inside a combined verse (MAT 1:1-6 in a
     * text that joins MAT 1:2-11 into one verse): the app shows the whole combined verse, which it can't avoid, and
     * calling such items approximate would change the status of many Bibles. A text piece runs from a chapter's lowest
     * verse to its highest verse end (PlanCompatibilityService::verseRows), so the engine never sees inside one.
     */
    private const CUT_ANYWHERE_SET_TYPES = [
        'text_usx', 'text_json', 'text_plain', 'text_format', 'audio_stream', 'audio_drama_stream',
    ];

    /** Answers ranked so that the best is the highest. */
    private const RANK_MISSING = 0;
    private const RANK_MISMATCH = 1;
    private const RANK_APPROXIMATE = 2;
    private const RANK_EXACT = 3;

    private const ANSWER_BY_RANK = [
        self::RANK_MISSING     => self::MISSING,
        self::RANK_MISMATCH    => self::MISMATCH,
        self::RANK_APPROXIMATE => self::APPROXIMATE,
        self::RANK_EXACT       => self::EXACT,
    ];

    /** "To the end of the chapter", for verse positions. */
    private const END = PHP_INT_MAX;

    private bool $include_video;
    private bool $include_opus;
    private bool $allow_video_only;
    private int $exact_threshold;
    private bool $allow_chapter_audio;

    public function __construct(
        bool $include_video = false,
        bool $include_opus = false,
        bool $allow_video_only = false,
        int $exact_threshold = 0,
        bool $allow_chapter_audio = false
    ) {
        $this->include_video = $include_video;
        $this->include_opus = $include_opus;
        $this->allow_video_only = $allow_video_only;
        $this->exact_threshold = $exact_threshold;
        $this->allow_chapter_audio = $allow_chapter_audio;
    }

    /**
     * Step 1: whether a fileset counts at all with these options. Unknown types never count.
     *
     * @param array $fileset at least set_type_code and is_opus
     */
    public function keepsFileset(array $fileset) : bool
    {
        $format = self::FORMAT_BY_SET_TYPE[$fileset['set_type_code'] ?? ''] ?? null;

        if ($format === null) {
            return false;
        }
        if ($format === self::FORMAT_VIDEO) {
            return $this->include_video;
        }
        if (!empty($fileset['is_opus']) && ($format === self::FORMAT_NON_DRAMA || $format === self::FORMAT_DRAMA)) {
            return $this->include_opus;
        }

        return true;
    }

    /**
     * Prepare one Bible: keep the filesets that count (step 1), join an audio chapter stored as several files into one
     * piece as the chapter endpoint plays it, find each chapter's last verse in this Bible from what is left, and
     * index every fileset's pieces by book and chapter with their verse positions resolved.
     *
     * The last verse of a chapter is the highest verse the Bible's text ends on there (in practice its plain text: USX
     * and JSON rows run to the end with no number, so they give none); without a numbered text end for that chapter,
     * the highest verse any fileset's piece ends on there. Unknown when no piece gives a number, or when a piece with
     * no numbered end there (it reaches the end, whatever the number) starts after that verse. Video's pieces count
     * only toward video's own last verses, so include_video changes nothing but video.
     *
     * @param array $filesets each: set_type_code, segmentation_type, is_opus, and pieces
     *                        (book_id => list of [chapter_start, verse_start, chapter_end, verse_end])
     * @param array|null $books book_id => true for the books the plans touch, or null for all. Every step works book by
     *                          book, so leaving out the other books changes no answer, only the work.
     *
     * @return array the prepared Bible, for evaluate()
     */
    public function prepareBible(array $filesets, ?array $books = null) : array
    {
        $kept = [];
        foreach ($filesets as $fileset) {
            if ($this->keepsFileset($fileset)) {
                if ($books !== null) {
                    $fileset['pieces'] = array_intersect_key($fileset['pieces'] ?? [], $books);
                }
                $format = self::FORMAT_BY_SET_TYPE[$fileset['set_type_code']];
                if (($format === self::FORMAT_NON_DRAMA || $format === self::FORMAT_DRAMA)
                    && !in_array($fileset['set_type_code'], self::CUT_ANYWHERE_SET_TYPES, true)
                ) {
                    $fileset['pieces'] = self::joinSplitChapters(
                        $fileset['pieces'] ?? [],
                        $fileset['segmentation_type'] ?? null
                    );
                }
                $kept[] = $fileset;
            }
        }

        // Video's numbers set the last verse only for video itself, so include_video never changes another answer.
        $not_video = array_filter($kept, function ($fileset) {
            return self::FORMAT_BY_SET_TYPE[$fileset['set_type_code']] !== self::FORMAT_VIDEO;
        });
        $last_verses = self::lastVerses($not_video);
        $video_last_verses = $this->include_video ? self::lastVerses($kept) : $last_verses;

        $prepared = [];
        foreach ($kept as $fileset) {
            $set_type = $fileset['set_type_code'];
            $last = self::FORMAT_BY_SET_TYPE[$set_type] === self::FORMAT_VIDEO ? $video_last_verses : $last_verses;
            $prepared[] = [
                'format'      => self::FORMAT_BY_SET_TYPE[$set_type],
                'cut'         => in_array($set_type, self::CUT_ANYWHERE_SET_TYPES, true),
                'chapters'    => $this->indexPieces($fileset['pieces'] ?? [], $last),
                'last_verses' => $last,
            ];
        }

        return ['filesets' => $prepared];
    }

    /**
     * Each chapter's last verse in this Bible, from these filesets (see prepareBible()).
     *
     * @return array book_id => chapter => verse; a chapter is absent when its last verse is unknown
     */
    private static function lastVerses(array $filesets) : array
    {
        $text_last = [];
        $any_last = [];
        $open_start = [];
        foreach ($filesets as $fileset) {
            $is_text = self::FORMAT_BY_SET_TYPE[$fileset['set_type_code']] === self::FORMAT_TEXT;
            foreach ($fileset['pieces'] ?? [] as $book => $pieces) {
                foreach ($pieces as [$chapter_start, $verse_start, $chapter_end, $verse_end]) {
                    // Where the piece runs to the end of a chapter with no number (an open end, or on into the next
                    // chapter), the highest verse it starts on there.
                    $last_open = $verse_end === null ? $chapter_end : $chapter_end - 1;
                    for ($chapter = $chapter_start; $chapter <= $last_open; $chapter++) {
                        $start = $chapter === $chapter_start ? (int) $verse_start : 1;
                        $open_start[$book][$chapter] = max($open_start[$book][$chapter] ?? 0, $start);
                    }
                    if ($verse_end === null) {
                        continue;
                    }
                    $any_last[$book][$chapter_end] = max($any_last[$book][$chapter_end] ?? 0, $verse_end);
                    if ($is_text) {
                        $text_last[$book][$chapter_end] = max($text_last[$book][$chapter_end] ?? 0, $verse_end);
                    }
                }
            }
        }
        // Without text, a numbered end is the chapter's last verse only if no open-ended piece starts after it (a
        // section from 7:36 on into chapter 8 shows that LUK 7 goes past an end of 35 elsewhere).
        $last_verses = [];
        foreach ($any_last as $book => $chapters) {
            foreach ($chapters as $chapter => $verse) {
                if (($open_start[$book][$chapter] ?? 0) <= $verse) {
                    $last_verses[$book][$chapter] = $verse;
                }
            }
        }
        foreach ($text_last as $book => $chapters) {
            foreach ($chapters as $chapter => $verse) {
                $last_verses[$book][$chapter] = $verse;
            }
        }

        return $last_verses;
    }

    /**
     * The answer for one plan and one prepared Bible: status, items and formats, plus playlist_items when ids were
     * asked for: the asked items that are in this plan, in the order asked ([] when none is).
     *
     * @param array $items each: id, book_id, chapter_start, chapter_end, verse_start, verse_end (as stored)
     * @param array $bible from prepareBible()
     * @param array|null $playlist_item_ids the ids asked for, de-duplicated; null when none was asked for
     */
    public function evaluate(array $items, array $bible, ?array $playlist_item_ids = null) : array
    {
        $ranks = $this->formatRanks($items, $bible);

        $formats = [
            self::FORMAT_TEXT => $this->summary($ranks[self::FORMAT_TEXT]),
            'audio' => $this->summary($ranks['audio']) + [
                self::FORMAT_NON_DRAMA => $this->summary($ranks[self::FORMAT_NON_DRAMA]),
                self::FORMAT_DRAMA     => $this->summary($ranks[self::FORMAT_DRAMA]),
            ],
        ];
        if ($this->include_video) {
            $formats[self::FORMAT_VIDEO] = $this->summary($ranks[self::FORMAT_VIDEO]);
        }

        // The Bible is playable when some item is exact or approximate in a counted format, before audio outranks text.
        $result = $this->summary($ranks['bible'], in_array(true, $ranks['playable'], true)) + ['formats' => $formats];

        if ($playlist_item_ids !== null) {
            $index_by_id = [];
            foreach ($items as $index => $item) {
                $index_by_id[(int) $item['id']] ??= $index;
            }
            $result['playlist_items'] = [];
            foreach ($playlist_item_ids as $playlist_item_id) {
                if (isset($index_by_id[$playlist_item_id])) {
                    $result['playlist_items'][] = $this->itemBlock(
                        $playlist_item_id,
                        $ranks,
                        $index_by_id[$playlist_item_id]
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Steps 2 to 5 for every item: the rank of each item per format, for audio as a whole, for the item (its status
     * as a requested playlist item) and for the Bible, and whether the item is playable (exact or approximate) in a
     * counted format.
     *
     * @return array format, 'audio', 'item' and 'bible' => list of ranks, and 'playable' => list of booleans, in item
     *               order
     */
    private function formatRanks(array $items, array $bible) : array
    {
        $parsed = [];
        foreach ($items as $item) {
            $parsed[] = self::parseItem($item);
        }

        $ranks = [];
        foreach ([self::FORMAT_TEXT, self::FORMAT_NON_DRAMA, self::FORMAT_DRAMA, self::FORMAT_VIDEO] as $format) {
            $ranks[$format] = array_fill(0, count($parsed), self::RANK_MISSING);
        }

        // Step 2 for each fileset, and step 3: each format keeps the best answer of its filesets.
        foreach ($bible['filesets'] as $fileset) {
            $format = $fileset['format'];
            foreach ($parsed as $index => $item) {
                $rank = $this->rankItem($item, $fileset, $fileset['last_verses']);
                if ($rank > $ranks[$format][$index]) {
                    $ranks[$format][$index] = $rank;
                }
            }
        }

        // Step 4, audio as a whole: the union of the two kinds.
        $ranks['audio'] = [];
        foreach (array_keys($parsed) as $index) {
            $ranks['audio'][$index] = max($ranks[self::FORMAT_NON_DRAMA][$index], $ranks[self::FORMAT_DRAMA][$index]);
        }

        // Step 5. The item: audio outranks text, so exact text doesn't hide audio that can't play it (Sam, 2026-11-11).
        // The Bible: the same, unless the plan's audio as a whole is close enough (exact_threshold), when the text
        // covers the few audio items that aren't exact; where an item has no text, its audio's answer stays (Sam,
        // 2026-11-12). Video joins both only with allow_video_only (the apps can't play an item from video alone
        // yet), and it isn't outranked.
        $close_enough = $this->audioIsCloseEnough($ranks['audio']);
        $ranks['item'] = [];
        $ranks['bible'] = [];
        $ranks['playable'] = [];
        foreach (array_keys($parsed) as $index) {
            $text = $ranks[self::FORMAT_TEXT][$index];
            $audio = $ranks['audio'][$index];
            $video = $this->allow_video_only ? $ranks[self::FORMAT_VIDEO][$index] : self::RANK_MISSING;
            $audio_outranking_text = $audio !== self::RANK_MISSING ? $audio : $text;

            $ranks['item'][$index] = max($audio_outranking_text, $video);
            $ranks['bible'][$index] = max($close_enough ? max($text, $audio) : $audio_outranking_text, $video);
            $ranks['playable'][$index] = max($text, $audio, $video) >= self::RANK_APPROXIMATE;
        }

        return $ranks;
    }

    /**
     * exact_threshold (revision 5, R22 and R25): whether the plan's audio as a whole is close enough for its other
     * items not to outrank the text in the Bible's answer. Its exact items must be at least the threshold's share of
     * its exact, approximate and mismatch items. Missing audio isn't counted (where an item has no audio its text
     * counts anyway), and approximate counts like mismatch, so the share reads the same with allow_chapter_audio. Off
     * at 0, and when the plan has no audio at all.
     *
     * @param array $audio_ranks audio as a whole, per item
     */
    private function audioIsCloseEnough(array $audio_ranks) : bool
    {
        if ($this->exact_threshold <= 0) {
            return false;
        }
        $counts = array_count_values($audio_ranks);
        $exact = $counts[self::RANK_EXACT] ?? 0;
        $counted = $exact + ($counts[self::RANK_APPROXIMATE] ?? 0) + ($counts[self::RANK_MISMATCH] ?? 0);

        return $counted > 0 && $exact * 100 >= $this->exact_threshold * $counted;
    }

    /**
     * Step 2: one item against one prepared fileset.
     */
    private function rankItem(array $item, array $fileset, array $last_verses) : int
    {
        $book = $item['book_id'];
        // Exact so far: every part covered, each by one piece matching it.
        $exact = true;
        // Some verse of the item is covered: otherwise it is missing (revision 4).
        $covered_somewhere = false;
        // Whole chapters: the item starts at the beginning of its first chapter and runs to the end of its last
        // (after clamping). Otherwise it starts or ends inside a chapter.
        $whole_chapters = true;

        for ($chapter = $item['chapter_start']; $chapter <= $item['chapter_end']; $chapter++) {
            [$start, $end] = self::itemPart($item, $chapter, $last_verses[$book][$chapter] ?? null);
            if ($start > $end) {
                // The item's part of this chapter starts past its last verse in this Bible: no verse to cover here,
                // and no chapter for the item to start inside (GEN 1:32-3 with 31 verses in GEN 1 is GEN 2-3).
                continue;
            }
            if (($chapter === $item['chapter_start'] && $start > 1)
                || ($chapter === $item['chapter_end'] && $end !== self::END)
            ) {
                $whole_chapters = false;
            }

            $pieces = $fileset['chapters'][$book][$chapter] ?? [];
            [$start, $end] = self::skipNumberingGaps($pieces, $start, $end);
            if ($start > $end) {
                // Only verses the translation leaves out.
                continue;
            }

            $start_covered = false;
            $end_covered = false;
            $overlapping = [];
            foreach ($pieces as $piece) {
                [$piece_start, $piece_end] = $piece;
                if ($piece_start <= $start && $start <= $piece_end) {
                    $start_covered = true;
                }
                if ($piece_start <= $end && $end <= $piece_end) {
                    $end_covered = true;
                }
                if ($piece_start <= $end && $piece_end >= $start) {
                    $overlapping[] = $piece;
                }
            }
            if (!empty($overlapping)) {
                $covered_somewhere = true;
            }
            if (!$start_covered || !$end_covered) {
                // This chapter's part is absent, or only partly there.
                $exact = false;
                continue;
            }

            if (!$fileset['cut'] && $exact) {
                // One piece, matching the item's part of this chapter, and not running outside the item.
                $exact = count($overlapping) === 1
                    && $overlapping[0][0] === $start
                    && $overlapping[0][1] === $end
                    && $overlapping[0][2] >= $item['chapter_start']
                    && $overlapping[0][3] <= $item['chapter_end'];
            }
        }

        if (!$covered_somewhere) {
            return self::RANK_MISSING;
        }

        return $exact ? self::RANK_EXACT : $this->inexactAnswer($fileset, $whole_chapters);
    }

    /**
     * Covered, fully or in part, but not exactly: can the app still play it, by playing every piece that overlaps the
     * item, or the part of it that is there?
     *  - text and streams: approximate. They are inexact only when the item is partly there, and the part that is
     *    there plays;
     *  - video: approximate, whatever the item's edges (Liubov agreed, 2026-11-11: video is never mismatch);
     *  - audio with chapter or section pieces: approximate for an item of whole chapters: in practice sections, older
     *    filesets with no segmentation whose files of one chapter start on different verses (the endpoint plays them as
     *    sections), the rare chapter file that runs on into the next chapter, and a whole chapter only partly there. An
     *    item that starts or ends inside a chapter is mismatch, even when several sections line up with its first and
     *    last verse, and also when it is only partly there: the verse rule always wins (Sam, 2026-11-10/11). So
     *    mismatch always means "starts or ends inside a chapter";
     *  - with allow_chapter_audio (revision 5, R23), that item is approximate too: the app plays the whole chapter, or
     *    every section that overlaps it. This is the only place mismatch comes from, so with the option nothing is
     *    mismatch anywhere.
     */
    private function inexactAnswer(array $fileset, bool $whole_chapters) : int
    {
        if ($fileset['cut'] || $fileset['format'] === self::FORMAT_VIDEO) {
            return self::RANK_APPROXIMATE;
        }

        return $whole_chapters || $this->allow_chapter_audio ? self::RANK_APPROXIMATE : self::RANK_MISMATCH;
    }

    /**
     * Gaps in a translation's numbering don't count: a start or end on a verse no piece covers, but with pieces on
     * both sides of it (MRK 11:26 in a translation that leaves it out, between pieces 11:20-25 and 11:27-33), moves to
     * the next piece's start or the previous piece's end. A start before every piece (the recording starts late) or an
     * end after every piece stays where it is: the item is then only partly there.
     *
     * @param array $pieces the chapter's pieces, each [start, end, chapter_start, chapter_end]
     */
    private static function skipNumberingGaps(array $pieces, int $start, int $end) : array
    {
        $start_covered = false;
        $end_covered = false;
        $piece_before_start = false;
        $piece_after_end = false;
        $next_start = null;
        $previous_end = null;
        foreach ($pieces as [$piece_start, $piece_end]) {
            if ($piece_start <= $start && $start <= $piece_end) {
                $start_covered = true;
            }
            if ($piece_start <= $end && $end <= $piece_end) {
                $end_covered = true;
            }
            if ($piece_end < $start) {
                $piece_before_start = true;
            }
            if ($piece_start > $end) {
                $piece_after_end = true;
            }
            if ($piece_start > $start && ($next_start === null || $piece_start < $next_start)) {
                $next_start = $piece_start;
            }
            if ($piece_end < $end && ($previous_end === null || $piece_end > $previous_end)) {
                $previous_end = $piece_end;
            }
        }

        if (!$start_covered && $piece_before_start && $next_start !== null) {
            $start = $next_start;
        }
        if (!$end_covered && $piece_after_end && $previous_end !== null) {
            $end = $previous_end;
        }

        return [$start, $end];
    }

    /**
     * The item's part of one of its chapters, as [start, end] verse positions; end is END for "to the end of the
     * chapter", and an end at or past the chapter's last verse is clamped to END. A start past the last verse gives a
     * start greater than the end: the item has no verse in this chapter.
     */
    private static function itemPart(array $item, int $chapter, ?int $last_verse) : array
    {
        $start = $chapter === $item['chapter_start'] && $item['verse_start'] !== null ? $item['verse_start'] : 1;
        $end = $chapter === $item['chapter_end'] && $item['verse_end'] !== null
            ? self::resolveEnd($item['verse_end'], $last_verse)
            : self::END;

        if ($last_verse !== null && $start > $last_verse) {
            $end = $start - 1;
        }

        return [$start, $end];
    }

    /**
     * Index a fileset's pieces by book and chapter. Each entry is [start, end, chapter_start, chapter_end] for that
     * chapter: a piece spanning chapters appears under each of them, from its start (or verse 1) to its end (or END).
     * Pieces that resolve to the same positions (video renditions, duplicated rows) are one piece.
     */
    private function indexPieces(array $pieces_by_book, array $last_verses) : array
    {
        $chapters = [];
        foreach ($pieces_by_book as $book => $pieces) {
            $seen = [];
            foreach ($pieces as [$chapter_start, $verse_start, $chapter_end, $verse_end]) {
                $start = max(1, (int) $verse_start);
                $end = self::resolveEnd($verse_end, $last_verses[$book][$chapter_end] ?? null);
                $key = $chapter_start . ':' . $start . ':' . $chapter_end . ':' . $end;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                for ($chapter = $chapter_start; $chapter <= $chapter_end; $chapter++) {
                    $chapters[$book][$chapter][] = [
                        $chapter === $chapter_start ? $start : 1,
                        $chapter === $chapter_end ? $end : self::END,
                        $chapter_start,
                        $chapter_end,
                    ];
                }
            }
        }

        return $chapters;
    }

    /**
     * The fileset chapter endpoint plays a chapter that an audio fileset stores as several files as one stream of the
     * whole chapter (BibleFileSetsTrait::isSingleChapterSplitIntoMultipleFiles, with expand_sections=true). So those
     * files are one piece, from the lowest start to the furthest end. Not in a section fileset, whose sections the
     * apps play one by one, nor in an older fileset with no segmentation whose files start on different verses (the
     * endpoint treats those as sections too). This mirrors that method for files within one chapter, and a change to
     * when it joins files must be made here too. A piece that runs into another chapter is left as it is, though the
     * endpoint would join it with other files starting in the same chapter (on dev, 2026-11-10, no chapter has both:
     * the one such non-section audio file is a whole book, JON 1:1-4:11).
     */
    private static function joinSplitChapters(array $pieces_by_book, ?string $segmentation_type) : array
    {
        if ($segmentation_type === 'section') {
            return $pieces_by_book;
        }

        foreach ($pieces_by_book as $book => $pieces) {
            $joined = [];
            $by_chapter = [];
            foreach ($pieces as $piece) {
                if ($piece[0] === $piece[2]) {
                    $by_chapter[$piece[0]][] = $piece;
                } else {
                    $joined[] = $piece;
                }
            }
            foreach ($by_chapter as $chapter => $group) {
                $starts = array_column($group, 1);
                if (count($group) === 1
                    || ($segmentation_type === null && count(array_unique($starts)) > 1)
                ) {
                    array_push($joined, ...$group);
                    continue;
                }
                $ends = array_column($group, 3);
                $joined[] = [$chapter, min($starts), $chapter, in_array(null, $ends, true) ? null : max($ends)];
            }
            $pieces_by_book[$book] = $joined;
        }

        return $pieces_by_book;
    }

    /**
     * A verse end as a position: null, or at or past the chapter's last verse, reaches the end of the chapter.
     */
    private static function resolveEnd(?int $verse_end, ?int $last_verse) : int
    {
        if ($verse_end === null || ($last_verse !== null && $verse_end >= $last_verse)) {
            return self::END;
        }

        return $verse_end;
    }

    /**
     * A plan item as the engine reads it. Like PlaylistItems::getFullChapterAttribute, an empty or 0 verse is no verse:
     * the item then starts at the beginning, or runs to the end, of its chapter. A missing or backwards chapter_end is
     * the chapter_start.
     */
    public static function parseItem(array $item) : array
    {
        $chapter_start = (int) $item['chapter_start'];
        $chapter_end = isset($item['chapter_end']) ? (int) $item['chapter_end'] : $chapter_start;

        return [
            'book_id'       => (string) $item['book_id'],
            'chapter_start' => $chapter_start,
            'chapter_end'   => max($chapter_start, $chapter_end),
            'verse_start'   => self::verseNumber($item['verse_start'] ?? null),
            'verse_end'     => self::verseNumber($item['verse_end'] ?? null),
        ];
    }

    /**
     * The leading number of a stored verse ("12", "2b"), or null when there is none or it is 0.
     */
    public static function verseNumber($value) : ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && preg_match('/^\s*(\d+)/', $value, $matches)) {
            $number = (int) $matches[1];
            return $number > 0 ? $number : null;
        }

        return null;
    }

    /**
     * status and items for a list of ranks. $playable is given for the Bible (from any counted format); a format's
     * comes from its own counts.
     */
    private function summary(array $ranks, ?bool $playable = null) : array
    {
        $counts = array_count_values($ranks);
        $items = [
            'total'       => count($ranks),
            'exact'       => $counts[self::RANK_EXACT] ?? 0,
            'approximate' => $counts[self::RANK_APPROXIMATE] ?? 0,
            'mismatch'    => $counts[self::RANK_MISMATCH] ?? 0,
            'missing'     => $counts[self::RANK_MISSING] ?? 0,
        ];

        return ['status' => self::status($items, $playable), 'items' => $items];
    }

    /**
     * The status from the counts, the first that applies:
     *  1. all exact (an empty plan too): full;
     *  2. nothing mismatch or missing: approximate;
     *  3. nothing missing, something playable: mismatch;
     *  4. something playable: partial (so partial always has missing items);
     *  5. something mismatch: incompatible_mismatch (nothing playable);
     *  6. otherwise: incompatible_missing (all missing).
     * Something playable: for a format, an item exact or approximate in its counts (the default); for the Bible, an
     * item exact or approximate in any counted format, before audio outranks text ($playable).
     */
    public static function status(array $items, ?bool $playable = null) : string
    {
        $playable ??= $items['exact'] + $items['approximate'] > 0;

        if ($items['exact'] === $items['total']) {
            return self::STATUS_FULL;
        }
        if ($items['mismatch'] === 0 && $items['missing'] === 0) {
            return self::STATUS_APPROXIMATE;
        }
        if ($playable && $items['missing'] === 0) {
            return self::STATUS_MISMATCH;
        }
        if ($playable) {
            return self::STATUS_PARTIAL;
        }
        if ($items['mismatch'] > 0) {
            return self::STATUS_INCOMPATIBLE_MISMATCH;
        }

        return self::STATUS_INCOMPATIBLE_MISSING;
    }

    /**
     * The block for one requested playlist item: its answer word per format, a status for the item, from audio
     * outranking text (as the Bible's, but never changed by exact_threshold: the player warns per passage), and one for
     * its audio as a whole. See itemStatus().
     */
    private function itemBlock(int $playlist_item_id, array $ranks, int $index) : array
    {
        $formats = [
            self::FORMAT_TEXT => self::ANSWER_BY_RANK[$ranks[self::FORMAT_TEXT][$index]],
            'audio' => [
                'status'               => self::itemStatus($ranks['audio'][$index], false),
                self::FORMAT_NON_DRAMA => self::ANSWER_BY_RANK[$ranks[self::FORMAT_NON_DRAMA][$index]],
                self::FORMAT_DRAMA     => self::ANSWER_BY_RANK[$ranks[self::FORMAT_DRAMA][$index]],
            ],
        ];
        if ($this->include_video) {
            $formats[self::FORMAT_VIDEO] = self::ANSWER_BY_RANK[$ranks[self::FORMAT_VIDEO][$index]];
        }

        return [
            'playlist_item_id' => $playlist_item_id,
            'status'           => self::itemStatus($ranks['item'][$index], $ranks['playable'][$index]),
            'formats'          => $formats,
        ];
    }

    /**
     * One item's status: exact gives full, approximate approximate, missing incompatible_missing, and mismatch gives
     * mismatch when the item is playable in a counted format (its text, say), else incompatible_mismatch. An item is
     * never partial. For the item's audio as a whole, $playable is false: mismatch there is incompatible_mismatch.
     */
    private static function itemStatus(int $rank, bool $playable) : string
    {
        switch ($rank) {
            case self::RANK_EXACT:
                return self::STATUS_FULL;
            case self::RANK_APPROXIMATE:
                return self::STATUS_APPROXIMATE;
            case self::RANK_MISMATCH:
                return $playable ? self::STATUS_MISMATCH : self::STATUS_INCOMPATIBLE_MISMATCH;
            default:
                return self::STATUS_INCOMPATIBLE_MISSING;
        }
    }
}
