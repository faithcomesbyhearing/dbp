<?php

namespace App\Http\Controllers\Bible;

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
                return $this->setStatusCode(404)->replyWithError('Fileset Not Found');
            }
            return fractal($books, new BooksTransformer(), $this->serializer);
        });

        return $this->reply($books);
    }

    public function getActiveBooksFromFileset($id, $fileset_type)
    {
        $fileset = BibleFileset::with('bible')->where('id', $id)->where('set_type_code', $fileset_type)->first();
        if (!$fileset) {
            return $this->setStatusCode(404)->replyWithError('Fileset Not Found'); // BWF: shouldn't reply like this, as it masks error later on
        }
        $is_plain_text = BibleVerse::where('hash_id', $fileset->hash_id)->exists();

        $versification = optional($fileset->bible->first())->versification;
        $book_order_column_exists = \Schema::connection('dbp')->hasColumn('books', $versification . '_order');
        $book_order_column = $book_order_column_exists ? 'books.' . $versification . '_order' : 'books.protestant_order';

        $dbp_database = config('database.connections.dbp.database');
        return \DB::connection('dbp')->table($dbp_database . '.bible_filesets as fileset')
            ->where('fileset.id', $id)
            ->leftJoin($dbp_database . '.bible_fileset_connections as connection', 'connection.hash_id', 'fileset.hash_id')
            ->leftJoin($dbp_database . '.bibles', 'bibles.id', 'connection.bible_id')
            ->when($fileset_type, function ($q) use ($fileset_type) {
                $q->where('set_type_code', $fileset_type);
            })
            ->when($is_plain_text, function ($query) use ($fileset) {
                $this->compareFilesetToSophiaBooks($query, $fileset->hash_id);
            }, function ($query) use ($fileset) {
                $this->compareFilesetToFileTableBooks($query, $fileset->hash_id);
            })
            ->orderBy($book_order_column)->select([
                'books.id',
                'books.id_usfx',
                'books.id_osis',
                'books.book_testament',
                'books.testament_order',
                'books.book_group',
                'bible_books.chapters',
                'bible_books.name',
                'books.protestant_order',
                $book_order_column . ' as book_order_column'
            ])->get();
    }

    /**
     *
     * @param $query
     * @param $id
     */
    private function compareFilesetToSophiaBooks($query, $hash_id)
    {
        // If the fileset references sophia.*_vpl than fetch the existing books from that database
        $dbp_database = config('database.connections.dbp.database');
        $sophia_books = BibleVerse::where('hash_id', $hash_id)->select('book_id')->distinct()->get();

        // Join the books for the books returned from Sophia
        $query->join($dbp_database . '.bible_books', function ($join) use ($sophia_books) {
            $join->on('bible_books.bible_id', 'bibles.id')
                ->whereIn('bible_books.book_id', $sophia_books->pluck('book_id'));
        })->rightJoin($dbp_database . '.books', 'books.id', 'bible_books.book_id');
    }

    /**
     *
     * @param $query
     * @param $hashId
     */
    private function compareFilesetToFileTableBooks($query, $hashId)
    {
        // If the fileset referencesade dbp.bible_files from that table
        $dbp_database = config('database.connections.dbp.database');
        $fileset_book_ids = DB::connection('dbp')
            ->table('bible_files')
            ->where('hash_id', $hashId)
            ->select(['book_id'])
            ->distinct()
            ->get()
            ->pluck('book_id');

        // Join the books for the books returned from bible_files
        $query->join($dbp_database . '.bible_books', function ($join) use ($fileset_book_ids) {
            $join->on('bible_books.bible_id', 'bibles.id')
                ->whereIn('bible_books.book_id', $fileset_book_ids);
        })->rightJoin($dbp_database . '.books', 'books.id', 'bible_books.book_id');
    }
}
