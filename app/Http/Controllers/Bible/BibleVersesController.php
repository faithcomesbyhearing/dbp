<?php

namespace App\Http\Controllers\Bible;

use Symfony\Component\HttpFoundation\Response;
use App\Traits\AccessControlAPI;
use App\Traits\CheckProjectMembership;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use App\Transformers\Serializers\DataArraySerializer;
use App\Http\Controllers\APIController;
use App\Models\Bible\BibleVerse;
use App\Transformers\BibleVerseTransformer;

class BibleVersesController extends APIController
{
    use AccessControlAPI;
    use CheckProjectMembership;

    /**
     * Display a listing of the bibles.
     *
     * @OA\Get(
     *     path="/bibles/verses/{language_code}/{book_id}/{chapter_id}/{verse_number?}",
     *     tags={"BibleVerses"},
     *     summary="Returns Bibles Verses based on filter criteria",
     *     description="The base bible route returning by default bibles and filesets that your key has access to",
     *     operationId="v4_bible_verses.verse_by_language",
     *     @OA\Parameter(
     *          name="language_code",
     *          in="path",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/id"),
     *          description="",
     *          required=true,
     *     ),
     *     @OA\Parameter(
     *          name="book_id",
     *          in="path",
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id"),
     *          description="The book to filter bible_verses by",
     *          example="MAT",
     *          required=true,
     *     ),
     *     @OA\Parameter(
     *          name="chapter",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/BibleVerse/properties/chapter"),
     *          description="The chapter to filter bible_verses by",
     *          required=true,
     *     ),
     *     @OA\Parameter(
     *          name="verse_number",
     *          in="path",
     *          @OA\Schema(ref="#/components/schemas/BibleVerse/properties/verse_number"),
     *          description="The verse start to filter bible_verses by",
     *          example="10"
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(ref="#/components/parameters/limit"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_verses.all"))
     *     )
     * )
     * @return mixed
     */
    public function showVerseByLanguage(
        string $language_code,
        string $book_id,
        string $chapter_id,
        string $verse_number = null,
    ) {
        $limit          = (int) (checkParam('limit') ?? 15);
        $limit          = min($limit, 50);
        $page           = checkParam('page') ?? 1;

        $access_group_ids = getAccessGroups();
        $cache_params = [$limit, $page, $language_code, $this->key, $book_id, $chapter_id, $verse_number];
        $cache_key = generateCacheSafeKey('bible_verses_by_language', $cache_params);
        $verses = cacheRememberByKey(
            $cache_key,
            now()->addDay(),
            function () use ($access_group_ids, $limit, $language_code, $verse_number, $book_id, $chapter_id) {
                return BibleVerse::withBibleFilesets($book_id, $chapter_id, $verse_number)
                    ->filterByLanguage($language_code)
                    ->isContentAvailable($access_group_ids)
                    ->paginate($limit);
            }
        );

        return $this->reply(fractal(
            $verses,
            new BibleVerseTransformer(),
        ));
    }

    /**
     * Display a listing of the bibles.
     *
     * @OA\Get(
     *     path="/bible/{bible_id}/verses/{book_id}/{chapter_id}/{verse_number?}",
     *     tags={"BibleVerses"},
     *     summary="Returns Bibles Verses based on filter criteria",
     *     description="The base bible route returning by default bibles and filesets that your key has access to",
     *     operationId="v4_bible_verses.verse_by_bible",
     *     @OA\Parameter(
     *          name="bible_id",
     *          in="path",
     *          @OA\Schema(ref="#/components/schemas/Bible/properties/id"),
     *          description="The Bible ID to filter bible_verses by",
     *          required=true,
     *     ),
     *     @OA\Parameter(
     *          name="book_id",
     *          in="path",
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id"),
     *          description="The book to filter bible_verses by",
     *          example="MAT",
     *          required=true,
     *     ),
     *     @OA\Parameter(
     *          name="chapter",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/BibleVerse/properties/chapter"),
     *          description="The chapter to filter bible_verses by",
     *          required=true,
     *     ),
     *     @OA\Parameter(
     *          name="verse_number",
     *          in="path",
     *          @OA\Schema(ref="#/components/schemas/BibleVerse/properties/verse_number"),
     *          description="The verse start to filter bible_verses by",
     *          example="10"
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(ref="#/components/parameters/limit"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_verses.all"))
     *     )
     * )
     * @return mixed
     */
    public function showVerseByBible(string $bible_id, string $book_id, string $chapter_id, string $verse_number = null)
    {
        $limit          = (int) (checkParam('limit') ?? 15);
        $limit          = min($limit, 50);
        $page           = checkParam('page') ?? 1;
        $access_group_ids = getAccessGroups();

        $cache_params = [$limit, $page, $bible_id, $this->key, $book_id, $chapter_id, $verse_number];
        $cache_key = generateCacheSafeKey('bible_verses_by_bible', $cache_params);
        $verses = cacheRememberByKey(
            $cache_key,
            now()->addDay(),
            function () use ($access_group_ids, $limit, $bible_id, $verse_number, $book_id, $chapter_id) {
                return BibleVerse::withBibleFilesets($book_id, $chapter_id, $verse_number)
                    ->filterByBible($bible_id)
                    ->isContentAvailable($access_group_ids)
                    ->paginate($limit);
            }
        );

        return $this->reply(fractal(
            $verses,
            new BibleVerseTransformer(),
        ));
    }
}
