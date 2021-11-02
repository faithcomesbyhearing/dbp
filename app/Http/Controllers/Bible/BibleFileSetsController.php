<?php

namespace App\Http\Controllers\Bible;

use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use Illuminate\Support\Str;
use App\Traits\AccessControlAPI;
use App\Traits\CallsBucketsTrait;
use App\Http\Controllers\APIController;
use App\Models\Bible\Bible;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileSecondary;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\BibleFilesetType;
use App\Models\Bible\Book;
use App\Models\Language\Language;

use App\Transformers\FileSetTransformer;
use App\Transformers\TextTransformer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class BibleFileSetsController extends APIController
{
    use AccessControlAPI;
    use CallsBucketsTrait;

    /**
     *
     * @OA\Get(
     *     path="/bibles/filesets/{fileset_id}",
     *     summary="Returns Bibles Filesets",
     *     description="Returns a list of bible filesets",
     *     operationId="v4_internal_filesets.show",
     *     @OA\Parameter(name="fileset_id", in="path", description="The fileset ID", required=true,
     *          @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")
     *     ),
     *     @OA\Parameter(name="book_id", in="query", description="Will filter the results by the given book. For a complete list see the `book_id` field in the `/bibles/books` route.",
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id")
     *     ),
     *     @OA\Parameter(name="chapter_id", in="query", description="Will filter the results by the given chapter",
     *          @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start")
     *     ),
     *     @OA\Parameter(name="type", in="query", description="The fileset type", required=true,
     *          @OA\Schema(ref="#/components/schemas/BibleFileset/properties/set_type_code")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_filesets.show"))
     *     ),
     *     deprecated=true
     * )
     *     API Note: this is confusing. If ENGESV is provided as fileset, and no type is provided, it returns ENGESVN1DA. Thus it is not part of the public API.
     *     Prefer instead v4_filesets.chapter
     *     If we implement a key-based access control, this endpoint should be limited to bible.is and gideons
     *
     * @param null $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     * @throws \Exception
     */
    public function show(
        $id = null,
        $set_type_code = null,
        $cache_key = 'bible_filesets_show'
    ) {
        $fileset_id = checkParam('dam_id|fileset_id', true, $id);
        $book_id = checkParam('book_id');
        $chapter_id = checkParam('chapter_id|chapter');
        $type = checkParam('type', $set_type_code !== null, $set_type_code);

        $cache_params = [$this->v, $fileset_id, $book_id, $type, $chapter_id];

        $fileset_chapters = cacheRemember(
            $cache_key,
            $cache_params,
            now()->addHours(12),
            function () use ($fileset_id, $book_id, $type, $chapter_id) {
                $book = Book::where('id', $book_id)
                    ->orWhere('id_osis', $book_id)
                    ->orWhere('id_usfx', $book_id)
                    ->first();
                $fileset = BibleFileset::with('bible')
                    ->uniqueFileset($fileset_id, $type)
                    ->first();
                if (!$fileset) {
                    return $this->setStatusCode(404)->replyWithError(
                        trans('api.bible_fileset_errors_404')
                    );
                }

                $access_blocked = $this->blockedByAccessControl($fileset);
                if ($access_blocked) {
                    return $access_blocked;
                }
                $asset_id = $fileset->asset_id;
                $bible = optional($fileset->bible)->first();
                
                return $this->showAudioVideoFilesets(
                    null,
                    $bible,
                    $fileset,
                    $asset_id,
                    $type,
                    $book,
                    $chapter_id
                );
            }
        );

        return $this->reply($fileset_chapters, [], $transaction_id ?? '');
    }

    /**
     *
     * @OA\Get(
     *     path="/bibles/filesets/{fileset_id}/{book}/{chapter}",
     *     tags={"Bibles"},
     *     summary="Returns content for a single fileset, book and chapter",
     *     description="For a given fileset, book and chapter, return content (text, audio or video)",
     *     operationId="v4_bible_filesets.showChapter",
     *     @OA\Parameter(name="fileset_id", in="path", description="The fileset ID", required=true,
     *          @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")
     *     ),
     *     @OA\Parameter(name="book", in="path", description="Will filter the results by the given book. For a complete list see the `book_id` field in the `/bibles/books` route.", required=true,
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id")
     *     ),
     *     @OA\Parameter(name="chapter", in="path", description="Will filter the results by the given chapter", required=true,
     *          @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start")
     *     ),
     *     @OA\Parameter(name="verse_start", in="query", description="Will filter the results by the given starting verse",
     *          @OA\Schema(ref="#/components/schemas/BibleFile/properties/verse_start")
     *     ),
     *     @OA\Parameter(name="verse_end", in="query", description="Will filter the results by the given ending verse",
     *          @OA\Schema(ref="#/components/schemas/BibleFile/properties/verse_end")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_filesets.showChapter"))
     *     )
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_bible_filesets.showChapter",
     *     description="v4_bible_filesets.showChapter",
     *     title="v4_bible_filesets.showChapter",
     *     @OA\Xml(name="v4_bible_filesets.showChapter"),
     * )
     *
     * @param string|null $fileset_url_param
     * @param string|null $book_url_param
     * @param string|null $chapter_url_param
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     * @throws \Exception
     */
    public function showChapter(
        $fileset_url_param = null,
        $book_url_param = null,
        $chapter_url_param = null,
        $cache_key = 'bible_filesets_show'
    ) {
        $fileset_id   = checkParam('dam_id|fileset_id', true, $fileset_url_param);
        $book_id      = checkParam('book_id', true, $book_url_param);
        $chapter_id   = checkParam('chapter_id|chapter', true, $chapter_url_param);
        $verse_start  = checkParam('verse_start') ?? 1;
        $verse_end    = checkParam('verse_end');
        $type         = checkParam('type') ?? '';

        $cache_params = $this->removeSpaceFromCacheParameters(
            [
                $this->v,
                $fileset_id,
                $book_id,
                $chapter_id,
                $verse_start,
                $verse_end,
                $type
            ]
        );

        $fileset_chapters = cacheRemember(
            $cache_key,
            $cache_params,
            now()->addHours(12),
            function () use ($fileset_id, $book_id, $chapter_id, $verse_start, $verse_end, $type) {
                $book = Book::where('id', $book_id)
                    ->orWhere('id_osis', $book_id)
                    ->orWhere('id_usfx', $book_id)
                    ->first();
                $fileset_from_id = BibleFileset::where('id', $fileset_id)->first();
                if (!$fileset_from_id) {
                    return $this->setStatusCode(404)->replyWithError(
                        trans('api.bible_fileset_errors_404')
                    );
                }
                $fileset_type = $fileset_from_id['set_type_code'];
                // fixes data issue where text filesets use the same filesetID
                $fileset_type = $this->getCorrectFilesetType($fileset_type, $type);

                $fileset = BibleFileset::with('bible')
                    ->uniqueFileset($fileset_id, $fileset_type)
                    ->first();
                if (!$fileset) {
                    return $this->setStatusCode(404)->replyWithError(
                        trans('api.bible_fileset_errors_404')
                    );
                }

                $access_blocked = $this->blockedByAccessControl($fileset);
                if ($access_blocked) {
                    return $access_blocked;
                }
                $asset_id = $fileset->asset_id;
                $bible = optional($fileset->bible)->first();

                if ($fileset_type === 'text_plain') {
                    return $this->showTextFilesetChapter(
                        null,
                        $bible,
                        $fileset,
                        $book,
                        $chapter_id,
                        $verse_start,
                        $verse_end
                    );
                } else {
                    return $this->showAudioVideoFilesets(
                        null,
                        $bible,
                        $fileset,
                        $asset_id,
                        $fileset_type,
                        $book,
                        $chapter_id
                    );
                }
            }
        );

        return $this->reply($fileset_chapters, [], $transaction_id ?? '');
    }

    /**
     *
     * @OA\Get(
     *     path="bibles/filesets/bulk/{fileset_id}/{book}",
     *     tags={"Bibles"},
     *     summary="Returns all content for a given fileset",
     *     description="For a given fileset return content (text, audio or video)",
     *     operationId="v4_internal_bible_filesets.showBulk",
     *     @OA\Parameter(name="fileset_id", in="path", description="The fileset ID", required=true,
     *          @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")
     *     ),
     *     @OA\Parameter(name="book", in="query", description="Will filter the results by the given book. For a complete list see the `book_id` field in the `/bibles/books` route.",
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id")
     *     ),
     *     @OA\Parameter(name="limit", in="query", description="limit for the pagination of the query"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_filesets.showBulk"))
     *     ),
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_bible_filesets.showBulk",
     *     description="v4_bible_filesets.showBulk",
     *     title="v4_bible_filesets.showBulk",
     *     @OA\Xml(name="v4_bible_filesets.showBulk"),
     *     @OA\Property(property="id", ref="#/components/schemas/BibleFileset/properties/id"),
     *     @OA\Property(property="type", ref="#/components/schemas/BibleFileset/properties/set_type_code"),
     *     @OA\Property(property="size", ref="#/components/schemas/BibleFileset/properties/set_size_code"),
     * )
     *
     * @param string|null $fileset_url_param
     * @param string|null $book_url_param
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     * @throws \Exception
     */
    public function showBulk(
        $fileset_url_param,
        $book_url_param = null,
        $cache_key = 'bible_filesets_show_bulk'
    ) {
        $fileset_id   = checkParam('dam_id|fileset_id', true, $fileset_url_param);
        $book_id      = checkParam('book_id', false, $book_url_param);
        $type         = checkParam('type') ?? '';
        $cache_params = [$this->v, $fileset_id, $book_id, $type];
        $limit        = (int) (checkParam('limit') ?? 5000);
        $limit        = max($limit, 5000);
        $page         = checkParam('page') ?? 1;
        $cache_key    = $cache_key . $page;

        $fileset_chapters = cacheRemember(
            $cache_key,
            $cache_params,
            now()->addHours(12),
            function () use ($fileset_id, $book_id, $limit, $type) {
                $fileset_from_id = BibleFileset::where('id', $fileset_id)->first();
                if (!$fileset_from_id) {
                    return $this->setStatusCode(404)->replyWithError(
                        trans('api.bible_fileset_errors_404')
                    );
                }
                $book = $book_id
                    ? Book::where('id', $book_id)
                        ->orWhere('id_osis', $book_id)
                        ->orWhere('id_usfx', $book_id)
                        ->first()
                    : null;
                $fileset_type = $fileset_from_id['set_type_code'];
                // fixes data issue where text filesets use the same filesetID
                $fileset_type = $this->getCorrectFilesetType($fileset_type, $type);
                $fileset = BibleFileset::with('bible')
                    ->uniqueFileset($fileset_id, $fileset_type)
                    ->first();
                if (!$fileset) {
                    return $this->setStatusCode(404)->replyWithError(
                        trans('api.bible_fileset_errors_404')
                    );
                }

                $bulk_access_control = $this->allowedByBulkAccessControl($fileset);
  
                if (isset($bulk_access_control->original['error'])) {
                    return $bulk_access_control;
                }

                $asset_id = $fileset->asset_id;
                $bible = optional($fileset->bible)->first();
                
                if ($fileset_type === 'text_plain') {
                    return $this->showTextFilesetChapter(
                        $limit,
                        $bible,
                        $fileset,
                        $book
                    );
                } else {
                    return $this->showAudioVideoFilesets(
                        $limit,
                        $bible,
                        $fileset,
                        $asset_id,
                        $fileset_type,
                        $book
                    );
                }
            }
        );

        return $this->reply($fileset_chapters, [], $transaction_id ?? '');
    }

    private function getCorrectFilesetType($fileset_type, $param_type)
    {
        $is_text_fileset = in_array($fileset_type, ['text_plain', 'text_format', 'text_html']);
        if ($is_text_fileset && $param_type === '') {
            $fileset_type = 'text_plain';
        } elseif ($is_text_fileset) {
            $fileset_type = $param_type;
        }

        return $fileset_type;
    }

    private function showTextFilesetChapter(
        $limit,
        $bible,
        $fileset,
        $book = null,
        $chapter_id = null,
        $verse_start = null,
        $verse_end = null
    ) {
        $select_columns = [
            'bible_verses.book_id as book_id',
            'books.name as book_name',
            'books.protestant_order as book_order',
            'bible_books.name as book_vernacular_name',
            'bible_verses.chapter',
            'bible_verses.verse_start',
            'bible_verses.verse_end',
            'bible_verses.verse_text',
        ];
        $text_query = BibleVerse::withVernacularMetaData($bible)
        ->where('hash_id', $fileset->hash_id)
        ->when($book, function ($query) use ($book) {
            return $query->where('bible_verses.book_id', $book->id);
        })
        ->when($chapter_id, function ($query) use ($chapter_id) {
            return $query->where('chapter', $chapter_id);
        })
        ->when($verse_start, function ($query) use ($verse_start) {
            return $query->where('verse_end', '>=', $verse_start);
        })
        ->when($verse_end, function ($query) use ($verse_end) {
            return $query->where('verse_end', '<=', $verse_end);
        })
        ->orderBy('verse_start')
        ->orderBy('books.name', 'ASC')
        ->orderBy('bible_verses.chapter');

        if ($bible && $bible->numeral_system_id) {
            $select_columns_extra = array_merge(
                $select_columns,
                [
                    'glyph_chapter.glyph as chapter_vernacular',
                    'glyph_start.glyph as verse_start_vernacular',
                    'glyph_end.glyph as verse_end_vernacular',
                ]
            );
            $text_query->select($select_columns_extra);
        } else {
            $text_query->select($select_columns);
        }

        if ($limit !== null) {
            $fileset_chapters = $text_query->paginate($limit);
            $filesets_pagination = new IlluminatePaginatorAdapter($fileset_chapters);
        } else {
            $fileset_chapters = $text_query->get();
        }

        if ($fileset_chapters->count() === 0) {
            return $this->setStatusCode(404)->replyWithError(
                'No Fileset Chapters Found for the provided params'
            );
        }

        $fileset_return = fractal(
            $fileset_chapters,
            new TextTransformer(),
            $this->serializer
        );

        return (
            $limit !== null ?
            $fileset_return->paginateWith($filesets_pagination) :
            $fileset_return
        );
    }

    private function showAudioVideoFilesets(
        $limit,
        $bible,
        $fileset,
        $asset_id,
        $type,
        $book = null,
        $chapter_id = null
    ) {
        $query = BibleFile::where('bible_files.hash_id', $fileset->hash_id)
        ->join(
            config('database.connections.dbp.database') .
                '.bible_books',
            function ($q) use ($bible) {
                $q
                    ->on(
                        'bible_books.book_id',
                        'bible_files.book_id'
                    )
                    ->where('bible_books.bible_id', $bible->id);
            }
        )
        ->join(
            config('database.connections.dbp.database') . '.books',
            'books.id',
            'bible_files.book_id'
        )
        ->when($chapter_id, function ($query) use ($chapter_id) {
            return $query->where(
                'bible_files.chapter_start',
                $chapter_id
            );
        })
        ->when($book, function ($query) use ($book) {
            return $query->where('bible_files.book_id', $book->id);
        })
        ->select([
            'bible_files.duration',
            'bible_files.hash_id',
            'bible_files.id',
            'bible_files.book_id',
            'bible_files.chapter_start',
            'bible_files.chapter_end',
            'bible_files.verse_start',
            'bible_files.verse_end',
            'bible_files.file_name',
            'bible_books.name as book_name',
            'books.protestant_order as book_order',
        ]);

        if ($type === 'video_stream') {
            $query
                ->orderByRaw(
                    "FIELD(bible_files.book_id, 'MAT', 'MRK', 'LUK', 'JHN') ASC"
                )
                ->orderBy('chapter_start', 'ASC')
                ->orderBy('verse_start', 'ASC');
        }
        if ($limit !== null) {
            $fileset_chapters = $query->paginate($limit);
            $filesets_pagination = new IlluminatePaginatorAdapter($fileset_chapters);
        } else {
            $fileset_chapters = $query->get();
        }
        if ($fileset_chapters->count() === 0) {
            return $this->setStatusCode(404)->replyWithError(
                'No Fileset Chapters Found for the provided params'
            );
        }

        $fileset_chapters = $this->generateSecondaryFiles(
            $fileset,
            $fileset_chapters,
            $bible,
            $asset_id
        );
        $fileset_return = fractal(
            $this->generateFilesetChapters(
                $fileset,
                $fileset_chapters,
                $bible,
                $asset_id
            ),
            new FileSetTransformer(),
            $this->serializer
        );
        if (isset($fileset_chapters->metadata)) {
            $fileset_return->addMeta($fileset_chapters->metadata);
        }

        return (
          $limit !== null ?
          $fileset_return->paginateWith($filesets_pagination) :
          $fileset_return
        );
    }

    /**
     *
     * Copyright
     *
     * @OA\Get(
     *     path="/bibles/filesets/{fileset_id}/copyright",
     *     tags={"Bibles"},
     *     summary="Fileset Copyright information",
     *     description="A fileset's copyright information and organizational connections",
     *     operationId="v4_internal_bible_filesets.copyright",
     *     @OA\Parameter(
     *          name="fileset_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id"),
     *          description="The fileset ID to retrieve the copyright information for"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_filesets.copyright"))
     *     )
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_bible_filesets.copyright",
     *     description="v4_bible_filesets.copyright",
     *     title="v4_bible_filesets.copyright",
     *     @OA\Xml(name="v4_bible_filesets.copyright"),
     *     @OA\Property(property="id", ref="#/components/schemas/BibleFileset/properties/id"),
     *     @OA\Property(property="type", ref="#/components/schemas/BibleFileset/properties/set_type_code"),
     *     @OA\Property(property="size", ref="#/components/schemas/BibleFileset/properties/set_size_code"),
     *     @OA\Property(property="copyright", ref="#/components/schemas/BibleFilesetCopyright")
     * )
     *
     * @param string $id
     * @return mixed
     */
    public function copyright($id)
    {
        $iso = checkParam('iso') ?? 'eng';

        $cache_params = [$id, $iso];
        $fileset = cacheRemember(
            'bible_fileset_copyright',
            $cache_params,
            now()->addDay(),
            function () use ($iso, $id) {
                $language_id = optional(Language::where('iso', $iso)->select('id')->first())->id;
                return BibleFileset::where('id', $id)->with([
                    'copyright.organizations.logos',
                    'copyright.organizations.translations' => function ($q) use ($language_id) {
                        $q->where('language_id', $language_id);
                    }
                ])
                    ->select(['hash_id', 'id', 'asset_id', 'set_type_code as type', 'set_size_code as size'])->first();
            }
        );

        return $this->reply($fileset);
    }

    /**
     * Returns the Available Media Types for Filesets within the API.
     *
     * @OA\Get(
     *     path="/bibles/filesets/media/types",
     *     tags={"Bibles"},
     *     summary="Available fileset types",
     *     description="A list of all the file types that exist within the filesets",
     *     operationId="v4_bible_filesets.types",
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(type="object",example={"audio_drama"="Dramatized Audio","audio"="Audio","text_plain"="Plain Text","text_format"="Formatted Text","video_stream"="Video","audio_stream"="Audio HLS Stream", "audio_drama_stream"="Dramatized Audio HLS Stream"})
     *         )
     *     )
     * )
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     *
     */
    public function mediaTypes()
    {
        return $this->reply(
            BibleFilesetType::all()->pluck('name', 'set_type_code')
        );
    }

    /**
     * @OA\Post(
     *     path="/bibles/filesets/check/types",
     *     tags={"Bibles"},
     *     summary="Check fileset types",
     *     description="Check Bible File locations if they have audio or video.",
     *     operationId="v4_internal_bible_filesets.checkTypes",
     *     @OA\RequestBody(ref="#/components/requestBodies/PlaylistItems"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_internal_fileset_check"))
     *     )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v4_internal_fileset_check",
     *   title="Fileset check types response",
     *   description="The v4 fileset check types response.",
     *   @OA\Items(
     *      @OA\Property(property="fileset_id", ref="#/components/schemas/PlaylistItems/properties/fileset_id"),
     *      @OA\Property(property="book_id", ref="#/components/schemas/PlaylistItems/properties/book_id"),
     *      @OA\Property(property="chapter_start", ref="#/components/schemas/PlaylistItems/properties/chapter_start"),
     *      @OA\Property(property="chapter_end", ref="#/components/schemas/PlaylistItems/properties/chapter_end"),
     *      @OA\Property(property="verse_start", ref="#/components/schemas/PlaylistItems/properties/verse_start"),
     *      @OA\Property(property="verse_end", ref="#/components/schemas/PlaylistItems/properties/verse_end"),
     *      @OA\Property(property="has_audio", type="boolean"),
     *      @OA\Property(property="has_video", type="boolean")
     *   )
     * )
     */
    public function checkTypes(Request $request)
    {
        $bible_locations = json_decode($request->getContent());
        $result = [];
        foreach ($bible_locations as $bible_location) {
            $invalid_bible_fileset = $this->validateBibleFileset($bible_location);
            if ($invalid_bible_fileset) {
                return $this->setStatusCode(422)->replyWithError($invalid_bible_fileset);
            }
            $cache_params = [$bible_location->fileset_id];
            $hashes = cacheRemember(
                'v4_bible_filesets.checkTypes',
                $cache_params,
                now()->addMonth(),
                function () use ($bible_location) {
                    $filesets = BibleFileset::where(
                        'id',
                        $bible_location->fileset_id
                    )
                        ->whereNotIn('set_type_code', ['text_format'])
                        ->first()
                        ->bible->first()->filesets;
                    $audio_filesets_hashes = $filesets
                        ->whereIn('set_type_code', [
                            'audio_drama',
                            'audio',
                            'audio_stream',
                            'audio_drama_stream'
                        ])
                        ->pluck('hash_id')
                        ->flatten();
                    $video_filesets_hashes = $filesets
                        ->where('set_type_code', 'video_stream')
                        ->pluck('hash_id')
                        ->flatten();
                    return [
                        'audio' => $audio_filesets_hashes,
                        'video' => $video_filesets_hashes
                    ];
                }
            );
            $where_fields = [
                ['book_id', $bible_location->book_id],
                ['chapter_start', '>=', $bible_location->chapter_start],
                [
                    \DB::raw('IFNULL( chapter_end, chapter_start)'),
                    '<=',
                    $bible_location->chapter_end
                ]
            ];
            if (isset($bible_location->verse_start)) {
                $where_fields[] = [
                    'verse_start',
                    '<=',
                    (int) $bible_location->verse_start
                ];
                $where_fields[] = [
                    \DB::raw(
                        'IFNULL( chapter_end, ' .
                            (int) $bible_location->verse_end .
                            ')'
                    ),
                    '>=',
                    $bible_location->verse_end
                ];
            }
            $bible_location->has_audio = BibleFile::whereIn(
                'hash_id',
                $hashes['audio']
            )
                ->where($where_fields)
                ->exists();
            $bible_location->has_video = BibleFile::whereIn(
                'hash_id',
                $hashes['video']
            )
                ->where($where_fields)
                ->exists();
            $result[] = $bible_location;
        }

        return $this->reply($result);
    }

    private function generateSecondaryFiles(
        $fileset,
        $fileset_chapters,
        $bible,
        $asset_id
    ) {
        $secondary_files = BibleFileSecondary::where(
            'hash_id',
            $fileset->hash_id
        )
        // this MIN is used to only pick one file name for each type
        // TODO: discuss and apply  a different way of selecting secondary files (specially for thumbnails)
        ->select(\DB::raw('MIN(file_name) as file_name,  file_type'))
        ->groupBy('file_type')->get();

        $secondary_file_paths = ['thumbnail' => null, 'zip_file' => null,];
        foreach ($secondary_files as $secondary_file) {
            $secondary_file_url = $this->signedUrl(
                storagePath($bible->id, $fileset, null, $secondary_file->file_name),
                $asset_id,
                random_int(0, 10000000)
            );
            if ($secondary_file->file_type === 'art') {
                $secondary_file_paths['thumbnail'] = $secondary_file_url;
            } elseif ($secondary_file->file_type === 'zip') {
                $secondary_file_paths['zip_file'] = $secondary_file_url;
            }
        }

        if ($fileset_chapters->count() === 1) {
            $fileset_chapters[0]->thumbnail = $secondary_file_paths['thumbnail'];
            $fileset_chapters[0]->zip_file = $secondary_file_paths['zip_file'];
        } else {
            $fileset_chapters->metadata = $secondary_file_paths;
        }
        return $fileset_chapters;
    }

    /**
     * @param      $fileset
     * @param      $fileset_chapters
     * @param      $bible
     * @param      $asset_id
     *
     * @throws \Exception
     * @return array
     */
    private function generateFilesetChapters(
        $fileset,
        $fileset_chapters,
        $bible,
        $asset_id
    ) {
        $is_stream =
            $fileset->set_type_code === 'video_stream' ||
            $fileset->set_type_code === 'audio_stream' ||
            $fileset->set_type_code === 'audio_drama_stream';
        $is_video = Str::contains($fileset->set_type_code, 'video');

        if ($is_stream) {
            foreach ($fileset_chapters as $key => $fileset_chapter) {
                $routeParameters = [
                    'fileset_id' => $fileset->id,
                    'book_id' => $fileset_chapter->book_id,
                    'chapter' => $fileset_chapter->chapter_start,
                    'verse_start' => $fileset_chapter->verse_start,
                    'verse_end' => $fileset_chapter->verse_end
                ];
                $fileset_chapters[$key]->file_name = route('v4_media_stream', array_filter(
                    $routeParameters,
                    function ($filesetProperty) {
                        return !is_null($filesetProperty) && $filesetProperty !== '';
                    }
                ));
            }
        } else {
            // Multiple files per chapter
            $hasMultiMp3Chapter = $this->hasMultipleMp3Chapters($fileset_chapters);
            if (sizeof($fileset_chapters) > 1 && !$is_video && $hasMultiMp3Chapter) {
                $fileset_chapters[0]->file_name = route(
                    'v4_media_stream',
                    [
                        'fileset_id' => $fileset->id,
                        'book_id' => $fileset_chapters[0]->book_id,
                        'chapter' => $fileset_chapters[0]->chapter_start,
                    ]
                );
                $collection = collect($fileset_chapters);
                $fileset_chapters[0]->duration = $collection->sum('duration');
                $fileset_chapters[0]->verse_end = $collection->last()->verse_end;
                $fileset_chapters[0]->multiple_mp3 = true;
                $fileset_chapters = [$fileset_chapters[0]];
            } else {
                foreach ($fileset_chapters as $key => $fileset_chapter) {
                    $fileset_chapters[$key]->file_name = $this->signedUrl(
                        storagePath(
                            $bible->id,
                            $fileset,
                            $fileset_chapter
                        ),
                        $asset_id,
                        random_int(0, 10000000)
                    );
                }
            }
        }

        if ($is_video) {
            foreach ($fileset_chapters as $key => $fileset_chapter) {
                $fileset_chapters[$key]->thumbnail = $this->signedUrl(
                    'video/thumbnails/' .
                        $fileset_chapters[$key]->book_id .
                        '_' .
                        str_pad(
                            $fileset_chapter->chapter_start,
                            2,
                            '0',
                            STR_PAD_LEFT
                        ) .
                        '.jpg',
                    $asset_id,
                    random_int(0, 10000000)
                );
            }
        }

        return $fileset_chapters;
    }

    private function hasMultipleMp3Chapters($fileset_chapters)
    {
        foreach ($fileset_chapters as $chapter) {
            if ($chapter['chapter_start'] !== $fileset_chapters[0]['chapter_start']) {
                return false;
            }
        }
        return true;
    }

    private function validateBibleFileset($request)
    {
        $validator = Validator::make((array) $request, [
            'book_id'       => ((request()->method() === 'POST') ? 'required|' : '') . 'exists:dbp.books,id',
            'fileset_id'    => ((request()->method() === 'POST') ? 'required|' : '') . 'exists:dbp.bible_filesets,id',
            'chapter_start' => ((request()->method() === 'POST') ? 'required|' : '') . 'max:150|min:1|integer',
            'chapter_end'   => ((request()->method() === 'POST') ? 'required|' : '') . 'max:150|min:1|integer',
            'verse_end'     => ((request()->method() === 'POST') ? 'required|' : '') . 'max:177|min:1|integer',
        ]);
        if ($validator->fails()) {
            return ['errors' => $validator->errors()];
        }
    }
}
