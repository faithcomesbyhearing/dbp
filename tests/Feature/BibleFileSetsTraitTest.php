<?php

namespace Tests\Feature;

use App\Models\Bible\BibleFileset;
use App\Traits\BibleFileSetsTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit coverage for BibleFileSetsTrait::isSingleChapterSplitIntoMultipleFiles(),
 * the guard that decides whether a set of audio files is a single chapter
 * mechanically split across multiple mp3 files (collapse into one .m3u8) or a
 * set of items that must each stay playable on their own.
 *
 * Section-awareness is opt-in via $expand_sections:
 *  - flag false => original behavior (collapse whenever every file shares
 *    chapter_start), so existing clients keep the single .m3u8 chapter playlist.
 *  - flag true  => section-segmented audio (segmentation_type='section', or the
 *    null-segmentation verse_start fallback) is kept as separate items.
 *
 * Regression for the bug where section-segmented one-chapter books (e.g. PHM,
 * JUD) were collapsed into a single playlist item because every section shares
 * chapter_start = 1.
 *
 * @group bible_filesets
 */
class BibleFileSetsTraitTest extends TestCase
{
    /**
     * Invoke the private guard via reflection on an anonymous host that uses the trait.
     */
    private function isSingleChapterSplit(
        $segmentation_type,
        array $fileset_chapters,
        bool $expand_sections = false
    ): bool {
        $host = new class {
            use BibleFileSetsTrait;
        };

        $fileset = new BibleFileset();
        if ($segmentation_type !== null) {
            $fileset->forceFill(['segmentation_type' => $segmentation_type]);
        }

        $method = new ReflectionMethod(
            $host,
            'isSingleChapterSplitIntoMultipleFiles'
        );
        $method->setAccessible(true);

        return $method->invoke(
            $host,
            $fileset,
            $fileset_chapters,
            $expand_sections
        );
    }

    private function chapters(array ...$rows): array
    {
        return array_map(function ($row) {
            return ['chapter_start' => $row[0], 'verse_start' => $row[1]];
        }, $rows);
    }

    /**
     * Without the opt-in flag the guard must behave exactly like the original:
     * a one-chapter section book collapses into a single .m3u8 playlist, so
     * existing clients (e.g. bibleis mobile) are unaffected.
     *
     * @test
     */
    public function legacy_collapses_section_book_when_flag_off()
    {
        $chapters = $this->chapters(
            [1, '001'],
            [1, '007'],
            [1, '017'],
            [1, '023']
        );

        $this->assertTrue(
            $this->isSingleChapterSplit(
                BibleFileset::SEGMENTATION_TYPE_SECTION,
                $chapters,
                false
            )
        );
    }

    /**
     * @test
     */
    public function legacy_collapses_null_segmentation_distinct_verses_when_flag_off()
    {
        $chapters = $this->chapters([1, '001'], [1, '007'], [1, '017']);

        $this->assertTrue($this->isSingleChapterSplit(null, $chapters, false));
    }

    /**
     * @test
     */
    public function section_book_is_not_collapsed_when_flag_on()
    {
        // PHM-style: one chapter, several intentional sections with distinct verse_starts.
        $chapters = $this->chapters(
            [1, '001'],
            [1, '007'],
            [1, '017'],
            [1, '023']
        );

        $this->assertFalse(
            $this->isSingleChapterSplit(
                BibleFileset::SEGMENTATION_TYPE_SECTION,
                $chapters,
                true
            )
        );
    }

    /**
     * @test
     */
    public function section_type_wins_over_matching_verses_when_flag_on()
    {
        $chapters = $this->chapters([1, '001'], [1, '001'], [1, '001']);

        $this->assertFalse(
            $this->isSingleChapterSplit(
                BibleFileset::SEGMENTATION_TYPE_SECTION,
                $chapters,
                true
            )
        );
    }

    /**
     * @test
     */
    public function null_segmentation_distinct_verses_is_not_collapsed_when_flag_on()
    {
        $chapters = $this->chapters([1, '001'], [1, '007'], [1, '017']);

        $this->assertFalse($this->isSingleChapterSplit(null, $chapters, true));
    }

    /**
     * @test
     */
    public function null_segmentation_mechanical_split_is_collapsed_when_flag_on()
    {
        // Same chapter, same verse_start across files → genuine mechanical split.
        $chapters = $this->chapters([1, '001'], [1, '001'], [1, '001']);

        $this->assertTrue($this->isSingleChapterSplit(null, $chapters, true));
    }

    /**
     * @test
     */
    public function chapter_type_with_matching_verses_is_collapsed()
    {
        $chapters = $this->chapters([1, '001'], [1, '001']);

        $this->assertTrue(
            $this->isSingleChapterSplit(
                BibleFileset::SEGMENTATION_TYPE_CHAPTER,
                $chapters,
                true
            )
        );
        $this->assertTrue(
            $this->isSingleChapterSplit(
                BibleFileset::SEGMENTATION_TYPE_CHAPTER,
                $chapters,
                false
            )
        );
    }

    /**
     * @test
     */
    public function multi_chapter_book_is_never_collapsed()
    {
        // Files span multiple chapters → each chapter stays its own item, both flag values.
        $chapters = $this->chapters([1, '001'], [2, '001'], [3, '001']);

        $this->assertFalse($this->isSingleChapterSplit(null, $chapters, true));
        $this->assertFalse($this->isSingleChapterSplit(null, $chapters, false));
        $this->assertFalse(
            $this->isSingleChapterSplit(
                BibleFileset::SEGMENTATION_TYPE_SECTION,
                $chapters,
                true
            )
        );
    }
}
