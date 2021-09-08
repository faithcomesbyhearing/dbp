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
use App\Transformers\FontsTransformer;
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
        $verse_start = checkParam('verse_start') ?? 1;
        $verse_end   = checkParam('verse_end') ?? $verse_start;

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
}
