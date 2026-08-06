<?php

namespace Tests\Integration;

use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleVerse;
use App\Traits\BibleFileSetsTrait;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression test for ADO-97684: BibleFileSetsTrait::showTextFilesetChapter()
 * must return verses in canonical reading order (chapter, then verse-within-chapter),
 * not verse-within-chapter first (which produced MAT 1:1, MAT 2:1, MAT 3:1, ...).
 *
 * Invokes the shared private method directly via reflection rather than through
 * the download/bulk/chapter HTTP routes, since it is the query built there (not
 * the routes' access-control layer) that this ticket fixes.
 *
 * @group bible_filesets
 */
class BibleFilesetVerseOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('request', Request::create('/', 'GET', ['v' => 4]));
    }

    private function showTextFilesetChapter($limit, $fileset, ?string $book_id, ?int $chapter_id = null)
    {
        $host = new class {
            use BibleFileSetsTrait;
            public $serializer;
        };

        $method = new ReflectionMethod($host, 'showTextFilesetChapter');
        $method->setAccessible(true);

        return $method->invoke($host, $limit, $fileset, $book_id, $chapter_id);
    }

    /**
     * @test
     */
    public function versesAreOrderedCanonicallyAcrossChapters()
    {
        // bible_verses has tens of millions of rows, so an unscoped cross-fileset
        // aggregate with ORDER BY RAND() is far too expensive. Instead, pick one
        // random fileset at a time (bible_filesets is small) and check it for a
        // multi-chapter book (scoped to its hash_id, so it's index-cheap), retrying
        // a bounded number of times. A book spanning >1 chapter is required, or the
        // bug is invisible.
        $fileset = null;
        $book_id = null;

        for ($attempt = 0; $attempt < 20 && $book_id === null; $attempt++) {
            $candidate_fileset = BibleFileset::where('set_type_code', 'text_plain')
                ->inRandomOrder()
                ->first();
            if (!$candidate_fileset) {
                break;
            }

            $candidate_book_id = BibleVerse::where('hash_id', $candidate_fileset->hash_id)
                ->select('book_id')
                ->groupBy('book_id')
                ->havingRaw('COUNT(DISTINCT chapter) > 1')
                ->inRandomOrder()
                ->value('book_id');

            if ($candidate_book_id !== null) {
                $fileset = $candidate_fileset;
                $book_id = $candidate_book_id;
            }
        }

        $this->assertNotNull($book_id, 'No multi-chapter text_plain book found after 20 random attempts');

        $result = $this->showTextFilesetChapter(null, $fileset, $book_id);
        $data = $result->toArray()['data'];

        $this->assertNotEmpty($data);

        $pairs = array_map(fn ($v) => [(int) $v['chapter'], (int) $v['verse_start']], $data);

        // The fixture query guarantees the book has >1 chapter, but not that every
        // chapter survives into the response (access control, pagination, etc.) —
        // confirm the returned rows actually exercise the cross-chapter ordering
        // this test is meant to catch.
        $distinct_chapters = array_unique(array_column($pairs, 0));
        $this->assertGreaterThan(1, count($distinct_chapters), 'Response must span more than one chapter to exercise cross-chapter ordering');

        $sorted = $pairs;
        usort($sorted, fn ($a, $b) => $a <=> $b);
        $this->assertSame($sorted, $pairs, 'Verses must be in ascending [chapter, verse] order');
    }
}
