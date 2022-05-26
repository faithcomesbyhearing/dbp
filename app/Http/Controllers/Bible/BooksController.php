<?php

namespace App\Http\Controllers\Bible;

use Symfony\Component\HttpFoundation\Response;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\Book;
use App\Models\Bible\BibleFileset;
use App\Transformers\BooksTransformer;
use App\Http\Controllers\APIController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BooksController extends APIController
{

    /**
     * Note: this now conflicts with another route: "The specified bible_id `books` could not be found". Removed from api.php
     * Returns a static list of Scriptural Books and Accompanying meta data
     *
     * @version 4
     * @category v4_bible_books_all
     *
     * @OA\Get(
     *     path="/bibles/books",
     *
     *     summary="Returns the books of the Bible",
     *     description="Returns all of the books of the Bible both canonical and deuterocanonical",
     *     operationId="v4_internal_bible_books_all",
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_books_all"))
     *     ),
     *     deprecated=true
     * )
     *
     * @return JsonResponse
     */
    // public function index()
    // {
    //     $books = cacheRememberForever('v4_books:index', function () {
    //         $books = Book::orderBy('protestant_order')->get();
    //         return fractal($books, new BooksTransformer(), $this->serializer);
    //     });
    //     return $this->reply($books);
    // }

    /**
     *
     * Returns the books and chapters for a specific fileset
     *
     * @version  4
     * @category v4_bible_filesets.books
     *
     * @OA\Get(
     *     path="/bibles/filesets/{fileset_id}/books",
     *     summary="Returns the books of the Bible",
     *     description="Returns the books and chapters for a specific fileset",
     *     operationId="v4_internal_bible_filesets.books",
     *     @OA\Parameter(name="fileset_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")
     *     ),
     *     @OA\Parameter(
     *         name="fileset_type",
     *         in="query",
     *         required=true,
     *         @OA\Schema(ref="#/components/schemas/BibleFileset/properties/set_type_code"),
     *         description="The type of fileset being queried"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible.books"))
     *     ),
     *     deprecated=true
     * )
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        $fileset_type = checkParam('fileset_type') ?? 'text_plain';

        $cache_params = [$id, $fileset_type];
        $books = cacheRemember('v4_books', $cache_params, now()->addDay(), function () use ($fileset_type, $id) {
            $books = $this->getActiveBooksFromFileset($id, $fileset_type);
            if (isset($books->original, $books->original['error'])) {
                return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError('Fileset Not Found');
            }
            return fractal($books, new BooksTransformer(), $this->serializer);
        });

        return $this->reply($books);
    }

    public function getActiveBooksFromFileset($id, $fileset_type)
    {
        $fileset = BibleFileset::with('bible')->where('id', $id)->where('set_type_code', $fileset_type)->first();
        if (!$fileset) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError('Fileset Not Found'); // BWF: shouldn't reply like this, as it masks error later on
        }

        $versification = optional($fileset->bible->first())->versification;
        return Book::getActiveBooksFromFileset($fileset, $versification, $fileset_type);
    }
}
