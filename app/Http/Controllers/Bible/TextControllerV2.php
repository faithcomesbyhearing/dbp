<?php

namespace App\Http\Controllers\Bible;

use App\Models\Bible\BibleVerse;
use DB;

use Illuminate\Http\Response;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\Book;
use App\Models\Language\AlphabetFont;
use App\Traits\AccessControlAPI;
use App\Traits\CheckProjectMembership;
use App\Traits\CallsBucketsTrait;
use App\Transformers\TextTransformer;
use App\Http\Controllers\APIController;
use App\Http\Controllers\Bible\Traits\TextControllerTrait;

class TextControllerV2 extends APIController
{
    use CallsBucketsTrait;
    use AccessControlAPI;
    use CheckProjectMembership;
    use TextControllerTrait;

    /**
     * Display a listing of the Verses
     * Will either parse the path or query params to get data before passing it to the bible_equivalents table
     *
     * @param string|null $bible_url_param
     * @param string|null $book_url_param
     * @param string|null $chapter_url_param
     *
     * API Note: I removed the v4 openapi docs. Returning text for a fileset/book/chapter is now handled in BibleFileSetsController:showChapter, along with all other filesets
     *
     * @OA\Get(
     *     path="/text/verse",
     *     tags={"Library Text"},
     *     summary="Returns Signed URLs or Text",
     *     description="V2's base fileset route",
     *     operationId="v2_text_verse",
     *     @OA\Parameter(name="fileset_id", in="query", description="The Bible fileset ID", required=true, @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")),
     *     @OA\Parameter(name="book", in="query", description="The Book ID. For a complete list see the `book_id` field in the `/bibles/books` route.", required=true, @OA\Schema(ref="#/components/schemas/Book/properties/id")),
     *     @OA\Parameter(name="chapter", in="query", description="The chapter number", required=true, @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v2_text_verse")),
     *         @OA\MediaType(mediaType="application/xml",  @OA\Schema(ref="#/components/schemas/v2_text_verse")),
     *         @OA\MediaType(mediaType="text/x-yaml",      @OA\Schema(ref="#/components/schemas/v2_text_verse"))
     *     )
     * )
     *
     * @return Response
     */
    public function index($bible_url_param = null, $book_url_param = null, $chapter_url_param = null)
    {
        // Fetch and Assign $_GET params
        $fileset_id  = checkParam('dam_id|fileset_id', true, $bible_url_param);
        $book_id     = checkParam('book_id', false, $book_url_param);
        $chapter     = checkParam('chapter_id', false, $chapter_url_param);
        $verse_start = checkParam('verse_start');
        $verse_end   = checkParam('verse_end');

        if (!empty($verse_start) && empty($verse_end)) {
            $verse_end = $verse_start;
        }

        $book = Book::where('id', $book_id)->orWhere('id_osis', $book_id)->first();

        $fileset = BibleFileset::with('bible')->uniqueFileset($fileset_id, 'text_plain')->first();
        if (!$fileset) {
            return $this->setStatusCode(404)->replyWithError('No fileset found for the provided params');
        }
        $bible = optional($fileset->bible)->first();

        $access_blocked = $this->blockedByAccessControl($fileset);
        if ($access_blocked) {
            return $access_blocked;
        }
        $asset_id = $fileset->asset_id;
        $cache_params = [$asset_id, $fileset_id, $book_id, $chapter, $verse_start, $verse_end];
        $verses = $this->getVerses(
            $cache_params,
            $fileset,
            $bible,
            $book,
            $chapter,
            $verse_start,
            $verse_end,
        );

        return $this->reply(fractal($verses, new TextTransformer(), $this->serializer));
    }

    /**
     *
     * @OA\Get(
     *     path="/search",
     *     tags={"Search"},
     *     summary="Search a bible for a word",
     *     description="",
     *     operationId="v2_text_search",
     *     @OA\Parameter(
     *          name="query",
     *          in="query",
     *          description="The word or phrase being searched", required=true,
     *          @OA\Schema(type="string"),
     *          example="Jesus"
     *     ),
     *     @OA\Parameter(
     *          name="fileset_id",
     *          in="query",
     *          description="The Bible fileset ID", required=true,
     *          @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")
     *     ),
     *     @OA\Parameter(name="limit",  in="query", description="The number of search results to return",
     *          @OA\Schema(type="integer",default=15)),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(name="books",  in="query", description="The usfm book ids to search through separated by a comma",
     *          @OA\Schema(type="string",example="GEN,EXO,MAT")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v2_text_search"))
     *     )
     * )
     *
     * @return Response
     *
     * @OA\Schema(
     *   schema="v2_text_search",
     *   type="object",
     *   @OA\Property(property="verses", ref="#/components/schemas/v4_bible_filesets_chapter"),
     *   @OA\Property(property="meta",ref="#/components/schemas/pagination")
     *
     * )
     */
    public function search()
    {
        if (!$this->api) {
            return view('docs.v2.text_search');
        }

        $query      = checkParam('query', true);
        $fileset_id = checkParam('fileset_id|dam_id', true);
        $book_id    = checkParam('book|book_id|books');

        $testament_filter = getTestamentString($fileset_id);
        $fileset = BibleFileset::with('bible')
            ->uniqueFileset(
                $fileset_id,
                'text_plain',
                false,
                $testament_filter
            )
            ->first();
        if (!$fileset) {
            return $this->setStatusCode(404)->replyWithError('No fileset found for the provided params');
        }
        $bible = $fileset->bible->first();

        $search_text  = '%' . $query . '%';
        $select_columns = [
            'bible_verses.book_id as book_id',
            'bible_books.bible_id as bible_id',
            'books.name as book_name',
            'bible_books.name as book_vernacular_name',
            'bible_verses.chapter',
            'bible_verses.verse_start',
            'bible_verses.verse_end',
            'bible_verses.verse_text',
            'book_testament',
            \DB::raw("'$fileset_id' as dam_id_request"),
        ];
        $verses = BibleVerse::where('hash_id', $fileset->hash_id)
            ->withVernacularMetaData($bible, $testament_filter)
            ->when($book_id, function ($query) use ($book_id) {
                $books = explode(',', $book_id);
                $query->whereIn('bible_verses.book_id', $books);
            })
            ->where('bible_verses.verse_text', 'like', $search_text);

        if ($bible && $bible->numeral_system_id) {
            $select_columns_extra = array_merge(
                $select_columns,
                [
                    'glyph_chapter.glyph as chapter_vernacular',
                    'glyph_start.glyph as verse_start_vernacular',
                    'glyph_end.glyph as verse_end_vernacular',
                ]
            );
            $verses->select($select_columns_extra);
        } else {
            $verses->select($select_columns);
        }

        return $this->reply([
            [['total_results' => strval($verses->count())]],
            fractal($verses->get(), new TextTransformer(), $this->serializer)
        ]);
    }
}
