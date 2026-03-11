<?php

namespace App\Http\Controllers\Bible;

use Symfony\Component\HttpFoundation\Response;
use App\Models\Bible\Book;
use App\Models\Bible\BibleFileset;
use App\Services\Bibles\FilesetBookIdBatchResolver;
use App\Transformers\BooksTransformer;
use App\Http\Controllers\APIController;
use Illuminate\Http\JsonResponse;

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
        $fileset = cacheRemember(
            'v4_books_fileset',
            $cache_params,
            now()->addDay(),
            function () use ($id, $fileset_type) {
                return BibleFileset::with('bible')
                    ->where('id', $id)
                    ->where('set_type_code', $fileset_type)
                    ->where('archived', false)
                    ->where('content_loaded', true)
                    ->first();
            }
        );

        if (!$fileset) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)
                ->replyWithError('Fileset Not Found');
        }

        $bible = $fileset->bible->first();

        if (!$bible) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)
                ->replyWithError('Bible Not Found for Fileset');
        }

        $books = cacheRemember('v4_books', $cache_params, now()->addDay(), function () use ($fileset, $bible) {
            $books = $this->getActiveBooksFromFileset($fileset, $bible->id, $bible->versification);
            return fractal($books, new BooksTransformer(), $this->serializer);
        });

        return $this->reply($books);
    }

    /**
     * Returns the active books for a given fileset.
     *
     * @param BibleFileset $fileset
     * @param string $bible_id
     * @param string $versification
     *
     * @return \Illuminate\Support\Collection
     */
    public function getActiveBooksFromFileset(BibleFileset $fileset, string $bible_id, string $versification)
    {
        $batch_resolver = new FilesetBookIdBatchResolver();
        $fileset_book_ids = $batch_resolver->resolve(collect([$fileset]));
        $book_ids = $fileset_book_ids[$fileset->id] ?? [];

        if (empty($book_ids)) {
            return collect();
        }

        return Book::getBooksByIdsForBible($bible_id, $book_ids, $versification);
    }
}
