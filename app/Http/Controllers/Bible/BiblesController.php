<?php

namespace App\Http\Controllers\Bible;

use Symfony\Component\HttpFoundation\Response;
use Spatie\Fractalistic\ArraySerializer;
use Illuminate\Support\Str;
use App\Models\Bible\Bible;
use App\Models\Bible\BibleBook;
use App\Models\Bible\BibleFilesetType;
use App\Models\Bible\BibleFilesetSize;
use App\Models\Organization\Organization;
use App\Transformers\BibleTransformer;
use App\Transformers\BooksTransformer;
use App\Transformers\CopyrightTransformer;
use App\Traits\AccessControlAPI;
use App\Traits\CheckProjectMembership;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use App\Transformers\Serializers\DataArraySerializer;
use App\Http\Controllers\APIController;
use App\Http\Controllers\User\BookmarksController;
use App\Http\Controllers\User\HighlightsController;
use App\Http\Controllers\User\NotesController;
use App\Models\User\UserDownload;
use App\Models\Bible\BibleDefault;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\Book;
use App\Models\Language\Language;
use App\Services\Bibles\BibleFilesetService;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use ZipArchive;

use Illuminate\Support\Facades\Log;

class BiblesController extends APIController
{
    use AccessControlAPI;
    use CheckProjectMembership;

    /**
     * Display a listing of the bibles.
     *
     * @OA\Get(
     *     path="/bibles",
     *     tags={"Bibles"},
     *     summary="Returns Bibles based on filter criteria",
     *     description="The base bible route returning by default bibles and filesets that your key has access to",
     *     operationId="v4_bible.all",
     *     @OA\Parameter(
     *          name="language_code",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso"),
     *          description="The iso code to filter results by. This will return results only in the language specified. For a complete list see the `iso` field in the `/languages` route",
     *     ),
     *     @OA\Parameter(
     *          name="media",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/BibleFilesetType/properties/set_type_code"),
     *          description="Will filter bibles based upon the media type of their filesets",
     *          example="audio_drama"
     *     ),
     *     @OA\Parameter(
     *          name="media_exclude",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/BibleFilesetType/properties/set_type_code"),
     *          description="Will exclude bibles based upon the media type of their filesets",
     *          example="audio"
     *     ),
     *     @OA\Parameter(
     *          name="country",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Country/properties/id"),
     *          description="The iso code to filter results by. This will return results only in the language specified. For a complete list see the `iso` field in the `/country` route",
     *          example="21"
     *     ),
     *     @OA\Parameter(
     *          name="audio_timing",
     *          in="query",
     *          @OA\Schema(type="boolean", default=false),
     *          description="This will return results only which have audio timing information available for that bible. The timing information is stored in table bible_file_timestamps.",
     *          example="true"
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(ref="#/components/parameters/limit"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible.all"))
     *     )
     * )
     * API Notes (Mar 2021):
     * I removed organization as a search criteria. There is no organization endpoint currently. Can be added back in, but the data needs validation first.
     * I removed size and size_exclude because there is no way for the API developer to see the list of available sizes. Need to add an endpoint showing available size types, or at least an enum
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function index()
    {
        $language_code      = checkParam('language_id|language_code');
        $organization_id    = checkParam('organization_id'); #removed from API for initial release
        $country            = checkParam('country');
        $media              = checkParam('media');
        $media_exclude      = checkParam('media_exclude');
        $audio_timing       = checkParam('audio_timing') ?? false;
        $size               = checkParam('size'); #removed from API for initial release
        $size_exclude       = checkParam('size_exclude'); #removed from API for initial release
        $limit              = (int) (checkParam('limit') ?? 50);
        $limit              = min($limit, 50);
        $page               = checkParam('page') ?? 1;
        $access_group_ids   = getAccessGroups();
        // removes opus filesets from the request to avoid memory overflows
        list($limit, $is_bibleis_gideons) = forceBibleisGideonsPagination($this->key, $limit);
        // created to support old bibleis versions
        $tag_exclude = null;
        $order_by = 'bibles.id';
        if (shouldUseBibleisBackwardCompat($this->key)) {
            $tag_exclude = 'opus';
        }

        if (isBackwardCompatible($this->key)) {
            $order_by = 'bibles.priority DESC';
        }

        $order_cache_key = str_replace(['bibles.', ' '], '', $order_by);

        if ($media) {
            $media_types = BibleFilesetType::select('set_type_code')->get();
            $media_type_exists = $media_types->where('set_type_code', $media);
            if ($media_type_exists->isEmpty()) {
                return $this
                    ->setStatusCode(Response::HTTP_NOT_FOUND)
                    ->replyWithError(
                        'media type not found. must be one of ' . $media_types->pluck('set_type_code')->implode(',')
                    );
            }
        }

        $organization = $organization_id
            ? Organization::where('id', $organization_id)->orWhere('slug', $organization_id)->first()
            : null;

        $cache_params = $this->removeSpaceFromCacheParameters([
            $language_code,
            $organization,
            $country,
            $media,
            $media_exclude,
            $size,
            $size_exclude,
            $tag_exclude,
            $limit,
            $page,
            $is_bibleis_gideons,
            $order_cache_key,
            $access_group_ids->toString(),
            $audio_timing
        ]);

        $bibles = cacheRemember(
            'bibles',
            $cache_params,
            now()->addDay(),
            function () use (
                $language_code,
                $organization,
                $country,
                $access_group_ids,
                $media,
                $media_exclude,
                $size,
                $size_exclude,
                $tag_exclude,
                $limit,
                $order_by,
                $audio_timing
            ) {
                $bibles = Bible::filterByLanguage($language_code)
                ->withRequiredFilesets([
                    'access_group_ids' => $access_group_ids,
                    'media'            => $media,
                    'media_exclude'    => $media_exclude,
                    'size'             => $size,
                    'size_exclude'     => $size_exclude,
                    'tag_exclude'      => $tag_exclude,
                ])
                ->leftJoin('bible_translations as ver_title', function ($join) {
                    $join->on('ver_title.bible_id', '=', 'bibles.id')->where('ver_title.vernacular', 1);
                })
                ->leftJoin('bible_translations as current_title', function ($join) {
                    $join->on('current_title.bible_id', '=', 'bibles.id');
                    if (isset($GLOBALS['i18n_id'])) {
                        $join->where('current_title.language_id', '=', $GLOBALS['i18n_id']);
                    }
                })
                ->leftJoin('languages as languages', function ($join) {
                    $join->on('languages.id', '=', 'bibles.language_id');
                })
                ->leftJoin('language_translations as language_autonym', function ($join) {
                    $join->on('language_autonym.language_source_id', '=', 'bibles.language_id')
                        ->on('language_autonym.language_translation_id', '=', 'bibles.language_id')
                        ->orderBy('priority', 'desc');
                })
                ->leftJoin('language_translations as language_current', function ($join) {
                    $join->on('language_current.language_source_id', '=', 'bibles.language_id')
                        ->orderBy('priority', 'desc');
                    if (isset($GLOBALS['i18n_id'])) {
                        $join->where('language_current.language_translation_id', '=', $GLOBALS['i18n_id']);
                    }
                })
                ->when($country, function ($q) use ($country) {
                    $q->whereHas('countryLanguage', function ($query) use ($country) {
                        $query->where('countries.id', $country);
                    });
                })
                ->when($organization, function ($q) use ($organization) {
                    $q->whereHas('organizations', function ($q) use ($organization) {
                        $q->where('organization_id', $organization->id);
                    })->orWhereHas('links', function ($q) use ($organization) {
                        $q->where('organization_id', $organization->id);
                    });
                })
                ->when($audio_timing, function ($q) {
                    $q->isTimingInformationAvailable();
                })
                ->select(
                    \DB::raw(
                        'MIN(current_title.name) as ctitle,
                        MIN(ver_title.name) as vtitle,
                        MIN(bibles.language_id) as language_id,
                        MIN(languages.iso) as iso,
                        MIN(bibles.date) as date,
                        MIN(language_autonym.name) as language_autonym,
                        MIN(language_current.name) as language_current,
                        MIN(languages.rolv_code) as language_rolv_code,
                        MIN(bibles.priority) as priority,
                        MIN(bibles.id) as id'
                    )
                )
                ->orderByRaw($order_by)->groupBy('bibles.id');

                $bibles = $bibles->paginate($limit);
                $bibles_return = fractal(
                    $bibles->getCollection(),
                    BibleTransformer::class,
                    new DataArraySerializer()
                );
                return $bibles_return->paginateWith(new IlluminatePaginatorAdapter($bibles));
            }
        );

        return $this->reply($bibles);
    }

    /**
     * Description:
     * Display the bible meta data for the specified ID.
     *
     * @OA\Get(
     *     path="/bibles/search/{search_text}",
     *     tags={"Bibles"},
     *     summary="Returns metadata for all bibles meeting the search_text in it's name",
     *     description="metadata for all bibles meeting the search_text in it's name",
     *     operationId="v4_bible.search",
     *     @OA\Parameter(name="search_text",in="path",required=true,@OA\Schema(ref="#/components/schemas/Bible/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json",
     *         @OA\Schema(ref="#/components/schemas/v4_bible.search"))
     *     )
     * )
     *
     * @param  string $search_text
     *
     * @return \Illuminate\Http\Response
     */
    public function search($search_text)
    {
        $limit          = (int) (checkParam('limit') ?? 15);
        $limit          = min($limit, 50);
        $page           = checkParam('page') ?? 1;
        $formatted_search = $this->transformQuerySearchText($search_text);
        $formatted_search_cache = str_replace(' ', '', $search_text);
        $access_group_ids = getAccessGroups();

        if ($formatted_search_cache === '' || !$formatted_search_cache || empty($formatted_search)) {
            return $this
                ->setStatusCode(Response::HTTP_BAD_REQUEST)
                ->replyWithError(trans('api.search_errors_400'));
        }

        $cache_params = [$limit, $page, $formatted_search_cache, $access_group_ids->toString()];
        $cache_key = generateCacheSafeKey('bibles_search', $cache_params);
        
        $bibles = cacheRememberByKey(
            $cache_key,
            now()->addDay(),
            function () use ($limit, $formatted_search, $access_group_ids) {
                $bibles = Bible::isContentAvailable($access_group_ids)
                    ->matchByFulltextSearch($formatted_search)
                    ->paginate($limit);

                return fractal(
                    $bibles->getCollection(),
                    BibleTransformer::class,
                    new DataArraySerializer()
                )->paginateWith(new IlluminatePaginatorAdapter($bibles));
            }
        );
        return $this->reply($bibles);
    }

    /**
     * Description:
     * Return the bible data by given Bible ID.
     *
     * @OA\Get(
     *     path="/bibles/search",
     *     tags={"Bibles"},
     *     summary="Returns metadata for all bibles meeting the given version in it's Bible ID",
     *     description="metadata for all bibles meeting the version in it's Bible ID",
     *     operationId="v4_bible_by_id.search",
     *     @OA\Parameter(name="version",in="query",required=true,@OA\Schema(ref="#/components/schemas/Bible/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json",
     *         @OA\Schema(ref="#/components/schemas/v4_bible.search"))
     *     )
     * )
     *
     * @return \Illuminate\Http\Response
     */
    public function searchByBibleVersion()
    {
        $limit          = (int) (checkParam('limit') ?? 15);
        $limit          = min($limit, 50);
        $page           = checkParam('page') ?? 1;
        $version_query = checkParam('version', true);
        $version_query = $this->transformQuerySearchText($version_query);
        $version_query_cache = str_replace(' ', '', $version_query);

        if ($version_query_cache === '' || !$version_query_cache || empty($version_query)) {
            return $this
                ->setStatusCode(Response::HTTP_BAD_REQUEST)
                ->replyWithError(trans('api.search_errors_400'));
        }

        $access_group_ids = getAccessGroups();
        $cache_params = [$limit, $page, $version_query_cache, $access_group_ids->toString()];
        $cache_key = generateCacheSafeKey('bibles_by_id_search', $cache_params);


        $bibles = cacheRememberByKey(
            $cache_key,
            now()->addDay(),
            function () use ($access_group_ids, $limit, $version_query) {
                $bibles = Bible::isContentAvailable($access_group_ids)
                ->matchByBibleVersion($version_query)
                ->paginate($limit);

                return fractal(
                    $bibles->getCollection(),
                    BibleTransformer::class,
                    new DataArraySerializer()
                )->paginateWith(new IlluminatePaginatorAdapter($bibles));
            }
        );

        return $this->reply($bibles);
    }

    /**
     * Description:
     * Display the bible meta data for the specified ID.
     *
     * @OA\Get(
     *     path="/bibles/{id}",
     *     tags={"Bibles"},
     *     summary="Returns detailed metadata for a single Bible",
     *     description="Detailed information for a single Bible",
     *     operationId="v4_bible.one",
     *     @OA\Parameter(name="id",in="path",required=true,@OA\Schema(ref="#/components/schemas/Bible/properties/id")),
     *     @OA\Parameter(name="include_font",in="query"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible.one"))
     *     )
     * )
     *
     * @param  string $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id = null)
    {
        $id   = checkParam('dam_id', false, $id);

        $include_font = is_null(checkParam('include_font')) ? true : checkBoolean('include_font', false);

        if ($this->v === 2 || $this->v === 3) {
            $id = substr($id, 0, 6);
        }
        $key_error_404 = 'api.bibles_errors_404';
        $access_group_ids = getAccessGroups();
        $bible = Bible::whereId($id)->isContentAvailable($access_group_ids)->count();

        if (!$bible) {
            return $this
                ->setStatusCode(Response::HTTP_NOT_FOUND)
                ->replyWithError(trans($key_error_404, ['bible_id' => $id]));
        }

        $cache_params = [$id, $access_group_ids->toString(), $include_font];
        $bible = cacheRemember(
            'bibles_show',
            $cache_params,
            now()->addDay(),
            function () use ($access_group_ids, $id, $include_font) {
                return Bible::with([
                    'translations',
                    'books.book',
                    'links',
                    'organizations.logo',
                    'organizations.logoIcon',
                    'organizations.translations',
                    'alphabet.primaryFont',
                    'filesets' => function ($query) use ($access_group_ids, $include_font) {
                        $query->isContentAvailable($access_group_ids)
                            ->when($include_font, function ($sub_query) {
                                $sub_query->with('fonts');
                            })
                            ->conditionToExcludeOldTextFormat()
                            ->conditionToExcludeOldDA16Format();
                    }
                ])->find($id);
            }
        );

        if (!$bible || !sizeof($bible->filesets)) {
            return $this
                ->setStatusCode(Response::HTTP_NOT_FOUND)
                ->replyWithError(trans($key_error_404, ['bible_id' => $id]));
        }

        return $this->reply(fractal($bible, new BibleTransformer(), $this->serializer));
    }

    /**
     *
     * @OA\Get(
     *     path="/bibles/{id}/book",
     *     tags={"Bibles"},
     *     summary="Book information for a Bible",
     *     description="Returns a list of translated book names and general information for the given Bible. The actual list of books may vary from fileset to fileset. For example, a King James Fileset may contain deuterocanonical books that are missing from one of it's sibling filesets nested within the bible parent.",
     *     operationId="v4_bible.books",
     *     @OA\Parameter(name="id",in="path",required=true,@OA\Schema(ref="#/components/schemas/Bible/properties/id")),
     *     @OA\Parameter(name="book_id",in="query", description="The book id. For a complete list see the `book_id` field in the `/bibles/books` route.",@OA\Schema(ref="#/components/schemas/Book/properties/id")),
     *     @OA\Parameter(
     *          name="verify_content",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Filter all the books that have content"
     *     ),
     *     @OA\Parameter(
     *          name="verse_count",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Retrieve how many verses the chapters of the books have"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible.books"))
     *     )
     * )
     *
     * @param string $bible_id
     * @param string|null $book_id
     *
     * @return APIController::reply()
     */
    public function books($bible_id, $book_id = null)
    {
        $book_id   = checkParam('book_id', false, $book_id);

        $verify_content = checkBoolean('verify_content');
        $verse_count = checkBoolean('verse_count');

        $access_group_ids = getAccessGroups();
        $cache_params = [$bible_id, $access_group_ids->toString(), $verify_content, $verse_count];
        $bible = cacheRemember(
            'bible_books_bible',
            $cache_params,
            now()->addDay(),
            function () use ($bible_id, $verify_content, $access_group_ids) {
                if (!$verify_content) {
                    return Bible::find($bible_id);
                }

                return Bible::with([
                    'filesets' => function ($query) use ($access_group_ids) {
                        $query->isContentAvailable($access_group_ids);
                    }
                ])->find($bible_id);
            }
        );


        if (!$bible) {
            return $this
                ->setStatusCode(Response::HTTP_NOT_FOUND)
                ->replyWithError(trans('api.bibles_errors_404', ['bible_id' => $bible_id]));
        }

        $cache_params = [$bible_id, $book_id];
        $books = cacheRemember(
            'bible_books_books',
            $cache_params,
            now()->addDay(),
            function () use ($bible_id, $book_id, $bible) {
                return BibleBook::getAllSortedByBookSeqOrVersification($bible_id, $bible->versification, $book_id);
            }
        );

        if ($verify_content) {
            $cache_params = [$bible_id, $access_group_ids->toString(), $verify_content, $book_id];
            $books = cacheRemember(
                'bible_books_books_verified',
                $cache_params,
                now()->addDay(),
                function () use ($books, $bible) {
                    $book_controller = new BooksController();
                    $active_books = [];
                    foreach ($bible->filesets as $fileset) {
                        $books_fileset = $book_controller
                            ->getActiveBooksFromFileset($fileset->id, $fileset->set_type_code)
                            ->pluck('id');
                        $active_books = $this->processActiveBooks(
                            $books_fileset,
                            $active_books,
                            $fileset->set_type_code
                        );
                    }

                    return $books->map(function ($book) use ($active_books) {
                        if (isset($active_books[$book->book_id])) {
                            $book->content_types = array_unique($active_books[$book->book_id]);
                        }
                        return $book;
                    })->filter(function ($book) {
                        return $book->content_types;
                    });
                }
            );

            if ($verse_count) {
                $cache_params = [$bible_id, $access_group_ids->toString(), $book_id];
                $books = cacheRemember(
                    'bible_books_verse_count',
                    $cache_params,
                    now()->addDay(),
                    function () use ($books, $bible) {
                        $text_filesets = $bible->filesetTypeTextPlainAssociated();

                        if ($text_filesets->isEmpty()) {
                            return $books;
                        }

                        return $books->map(function ($book) use ($text_filesets) {
                            $verses_count = [];
                            $book_testament  = $book->book ? $book->book->book_testament : null;

                            $text_fileset = $text_filesets
                                ->where('set_size_code', $book_testament)
                                ->first();

                            if (!$text_fileset) {
                                $text_fileset = $text_filesets->first(function($item) use ($book_testament) {
                                    return Str::contains($item->set_size_code, $book_testament);
                                });

                                if (!$text_fileset) {
                                    $text_fileset = $text_filesets
                                        ->where('set_size_code', BibleFilesetSize::SIZE_COMPLETE)
                                        ->first();
                                }
                            }

                            if (!$text_fileset) {
                                return $book;
                            }

                            $verses_by_book = BibleVerse::where('hash_id', $text_fileset->hash_id)
                                ->where('book_id', $book->book_id)
                                ->whereIn('chapter', array_map('\intval', explode(',', $book->chapters)))
                                ->select('chapter', \DB::raw('COUNT(id) as verse_count'))
                                ->groupBy('chapter')
                                ->get();

                            foreach ($verses_by_book as $verse) {
                                if ($verse['verse_count']) {
                                    $verses_count[] = [
                                        'chapter' => $verse['chapter'], 'verses' => $verse['verse_count']
                                    ];
                                }
                            }
                            $book->verses_count = $verses_count;
                            return $book;
                        });
                    }
                );
            }
        }

        return $this->reply(fractal($books, new BooksTransformer));
    }

    private function processActiveBooks($books, $active_books, $set_type_code)
    {
        foreach ($books as $book) {
            $active_books[$book] =  $active_books[$book] ?? [];
            $active_books[$book][] = $set_type_code;
        }
        return $active_books;
    }

    /**
     * @OA\Get(
     *     path="/bibles/defaults/types",
     *     tags={"Bibles"},
     *     summary="Default Bible for a language",
     *     description="Returns default Bible for a language",
     *     operationId="v4_bible.defaults",
     *     @OA\Parameter(
     *          name="language_code",
     *          in="query",
     *          @OA\Schema(type="string",example="en", maxLength=6),
     *          description="The language code to filter results by"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bibles_defaults"))
     *     )
     * )
     *
     * @OA\Schema (
     *    type="object",
     *    schema="v4_bibles_defaults",
     *    description="The bible defaults",
     *    title="v4_bibles_defaults",
     *    @OA\Xml(name="v4_bibles_defaults"),
     *    @OA\Property(property="en", type="object",
     *          @OA\Property(property="video", ref="#/components/schemas/Bible/properties/id"),
     *          @OA\Property(property="audio", ref="#/components/schemas/Bible/properties/id")
     *     )
     *   )
     * )
     *
     */
    public function defaults()
    {
        $language_code = checkParam('language_code');
        $defaults = BibleDefault::when($language_code, function ($q) use ($language_code) {
            $q->where('language_code', $language_code);
        })
            ->get();
        $result = [];
        foreach ($defaults as $default) {
            if (!isset($result[$default->language_code])) {
                $result[$default->language_code] = [];
            }
            $result[$default->language_code][$default->type] = $default->bible_id;
        }
        return $this->reply($result);
    }

    /**
     * @OA\Get(
     *     path="/bibles/{bible_id}/copyright",
     *     tags={"Bibles"},
     *     summary="Bible Copyright information",
     *     description="All bible fileset's copyright information and organizational connections",
     *     operationId="v4_bible.copyright",
     *     @OA\Parameter(
     *          name="bible_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Bible/properties/id"),
     *          description="The Bible ID to retrieve the copyright information for"
     *     ),
     *     @OA\Parameter(
     *          name="iso",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso", default="eng"),
     *          description="The iso code to filter organization translations by. For a complete list see the `iso` field in the `/languages` route."
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The requested bible copyrights",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible.copyright"))
     *     )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v4_bible.copyright",
     *   title="Bible copyrights response",
     *   description="The v4 bible copyrights response.",
     *   @OA\Items(ref="#/components/schemas/v4_bible_filesets.copyright")
     * )
     *
     */
    public function copyright($bible_id)
    {
        $bible = Bible::whereId($bible_id)->first();
        if (!$bible) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError('Bible not found');
        }

        $iso = checkParam('iso') ?? 'eng';

        $cache_params = [$bible_id, $iso];
        $copyrights = cacheRemember('bible_copyrights', $cache_params, now()->addDay(), function () use ($bible, $iso) {
            $language_id = optional(Language::where('iso', $iso)->select('id')->first())->id;
            $filesets_hash_ids = $bible->filesets->pluck('hash_id')->toArray();
            return empty($filesets_hash_ids)
                ? []
                : BibleFileset::select(['hash_id', 'id', 'set_type_code as type', 'set_size_code as size'])
                    ->whereIn('hash_id', $filesets_hash_ids)->with([
                        'copyright.organizations.logos',
                        'copyright.organizations.translations' => function ($q) use ($language_id) {
                            $q->where('language_id', $language_id);
                        }
                    ])
                    ->get();
        });

        return $this->reply(fractal($copyrights, CopyrightTransformer::class, new ArraySerializer()));
    }

    /**
     * @OA\Get(
     *     path="/bibles/{bible_id}/chapter",
     *     tags={"Bibles"},
     *     summary="Bible chapter information",
     *     description="All bible chapter information",
     *     operationId="v4_internal_bible.chapter",
     *     security={{"dbp_user_token":{}}},
     *     @OA\Parameter(
     *          name="bible_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Bible/properties/id"),
     *          description="The Bible ID to retrieve the chapter information for"
     *     ),
     *     @OA\Parameter(
     *          name="book_id",
     *          in="query",
     *          required=true,
     *          description="Will filter the results by the given book. For a complete list see the `book_id` field in the `/bibles/books` route.",
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id")
     *     ),
     *     @OA\Parameter(
     *          name="chapter",
     *          in="query",
     *          required=true,
     *          description="Will filter the results by the given chapter",
     *          @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start")
     *     ),
     *     @OA\Parameter(
     *          name="zip",
     *          in="query",
     *          @OA\Schema(type="boolean", default=false),
     *          description="Download the given data and package as a compressed file"
     *     ),
     *     @OA\Parameter(
     *          name="copyrights",
     *          in="query",
     *          @OA\Schema(type="boolean", default=false),
     *          description="Will include copyright data"
     *     ),
     *     @OA\Parameter(
     *          name="drama",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="If sent, will determine whether drama or non-drama audio is sent. If  this parameter is not present drama and non-drama will be retrieved"
     *     ),
     *     @OA\Parameter(
     *          name="iso",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso", default="eng"),
     *          description="The iso code to filter copyrights organization translations by. For a complete list see the `iso` field in the `/languages` route."
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The requested bible chapter",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible.chapter"))
     *     )
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_bible.chapter",
     *   title="Bible chapter response",
     *   description="The v4 bible chapter response.",
     *   @OA\Property(property="bible_id", ref="#/components/schemas/Bible/properties/id"),
     *   @OA\Property(property="book_id", ref="#/components/schemas/Book/properties/id"),
     *   @OA\Property(property="chapter", ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *   @OA\Property(property="copyrights",  type="array",
     *      @OA\Items(
     *          @OA\Property(property="id", ref="#/components/schemas/BibleFileset/properties/id"),
     *          @OA\Property(property="type", ref="#/components/schemas/BibleFileset/properties/set_type_code"),
     *          @OA\Property(property="size", ref="#/components/schemas/BibleFileset/properties/set_size_code"),
     *          @OA\Property(property="copyright", ref="#/components/schemas/v4_bible_filesets.copyright")
     *      )
     *   ),
     *   @OA\Property(property="filesets", type="object",
     *      @OA\Property(property="video", type="object",
     *          @OA\Property(property="gospel_films", ref="#/components/schemas/v4_bible_filesets.show/properties/data"),
     *          @OA\Property(property="jesus_films", ref="#/components/schemas/v4_bible_chapter_jesus_films")
     *      ),
     *      @OA\Property(property="audio", type="object",
     *         @OA\Property(property="drama", ref="#/components/schemas/v4_bible.fileset_chapter"),
     *         @OA\Property(property="non_drama", ref="#/components/schemas/v4_bible.fileset_chapter"),
     *      ),
     *      @OA\Property(property="text", type="object",
     *         @OA\Property(property="verses", ref="#/components/schemas/v4_bible_filesets_chapter/properties/data"),
     *         @OA\Property(property="formatted_verses", type="string"),
     *      ),
     *   ),
     *   @OA\Property(property="timestamps", type="object",
     *       @OA\Property(property="drama", ref="#/components/schemas/v4_bible.fileset_chapter_timestamp"),
     *       @OA\Property(property="non_drama", ref="#/components/schemas/v4_bible.fileset_chapter_timestamp"),
     *   )
     * )
     * @OA\Schema(
     *      type="object",
     *      schema="v4_bible.fileset_chapter",
     *      @OA\Property(property="book_id",        ref="#/components/schemas/BibleFile/properties/book_id"),
     *      @OA\Property(property="book_name",      ref="#/components/schemas/BookTranslation/properties/name"),
     *      @OA\Property(property="chapter_start",  ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *      @OA\Property(property="chapter_end",    ref="#/components/schemas/BibleFile/properties/chapter_end"),
     *      @OA\Property(property="verse_start",    ref="#/components/schemas/BibleFile/properties/verse_start"),
     *      @OA\Property(property="verse_end",      ref="#/components/schemas/BibleFile/properties/verse_end"),
     *      @OA\Property(property="thumbnail",      type="string", description="The image url", maxLength=191),
     *      @OA\Property(property="timestamp",      ref="#/components/schemas/BibleFileTimestamp/properties/timestamp"),
     *      @OA\Property(property="path",           ref="#/components/schemas/BibleFile/properties/file_name"),
     *      @OA\Property(property="duration",       ref="#/components/schemas/BibleFile/properties/duration"),
     *      @OA\Property(property="fileset", type="object",
     *          @OA\Property(property="id", ref="#/components/schemas/BibleFileset/properties/id"),
     *          @OA\Property(property="type", ref="#/components/schemas/BibleFileset/properties/set_type_code"),
     *          @OA\Property(property="size", ref="#/components/schemas/BibleFileset/properties/set_size_code"),
     *      )
     * )
     * @OA\Schema(
     *      type="array",
     *      schema="v4_bible.fileset_chapter_timestamp",
     *      @OA\Items(
     *          @OA\Property(property="timestamp",        ref="#/components/schemas/BibleFileTimestamp/properties/timestamp"),
     *          @OA\Property(property="verse_start",      ref="#/components/schemas/BibleFile/properties/verse_start")
     *      )
     * )
     * @OA\Schema(
     *      type="array",
     *      schema="v4_bible_chapter_jesus_films",
     *      @OA\Items(
     *          @OA\Property(property="component_id", type="string"),
     *          @OA\Property(property="verses", type="array", @OA\Items(type="integer")),
     *          @OA\Property(property="meta", type="object",
     *                @OA\Property(property="thumbnail", type="string"),
     *                @OA\Property(property="thumbnail_high", type="string"),
     *                @OA\Property(property="title", type="string"),
     *                @OA\Property(property="shortDescription", type="string"),
     *                @OA\Property(property="longDescription", type="string"),
     *                @OA\Property(property="file_name", type="string")
     *          )
     *      )
     * )
     */
    public function chapter(Request $request, $bible_id)
    {
        // Make that only bibleis and gideons can access this endpoint
        if (!isBackwardCompatible($this->key)) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError(trans('api.errors_404'));
        }

        $access_group_ids = getAccessGroups();
        $bible = cacheRemember(
            'v4_chapter_bible',
            [$bible_id, $access_group_ids->toString()],
            now()->addDay(),
            function () use ($bible_id, $access_group_ids) {
                return Bible::with([
                    'filesets' => function ($query) use ($access_group_ids) {
                        $query->isContentAvailable($access_group_ids);
                    }
                ])->whereId($bible_id)->first();
            }
        );
        if (!$bible) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError('Bible not found');
        }

        $user = $request->user();
        $show_annotations = !empty($user);

        // Validate Project / User Connection
        if ($show_annotations && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $book_id = checkParam('book_id', true);
        $chapter = checkParam('chapter', true);

        $zip = checkBoolean('zip');

        $copyrights = checkBoolean('copyrights');
        $drama = checkParam('drama') ?? 'all';
        if ($drama !== 'all') {
            $drama = checkBoolean('drama') ? 'drama' : 'non-drama';
        }
        
        $book = cacheRemember('v4_chapter_book', [$book_id], now()->addDay(), function () use ($book_id) {
            return Book::whereId($book_id)->first();
        });

        if (!$book) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError('Book not found');
        }

        $result = (object) [];

        if ($show_annotations) {
            $highlights_controller = new HighlightsController();
            $bookmarks_controller = new BookmarksController();
            $notes_controller = new NotesController();
            $request->request->add(['bible_id' => $bible_id]);
            $result->annotations = (object) [
                'highlights' => optional($highlights_controller->index($request, $user->id)->original)['data'] ?? [],
                'bookmarks' => optional($bookmarks_controller->index($request, $user->id)->original)['data'] ?? [],
                'notes' => optional($notes_controller->index($request, $user->id)->original)['data'] ?? [],
            ];
        }
        $result->bible_id = $bible->id;
        $result->book_id = $book_id;
        $result->chapter = $chapter;

        if ($copyrights) {
            $result->copyrights = cacheRemember('v4_chapter_copyrights', [$bible->id], now()->addDay(), function () use ($bible) {
                return $this->copyright($bible->id)->original;
            });
        }

        $cache_params = [$bible_id, $book_id, $chapter, $zip, $drama];

        $chapter_filesets = cacheRemember('v4_chapter_filesets', $cache_params, now()->addHours(12), function () use ($drama, $zip, $bible, $book, $bible_id, $book_id, $chapter, $user) {
            $chapter_filesets = (object) [
                'video' => (object) ['gospel_films' => [], 'jesus_films' => []],
                'audio' => (object) [],
                'text' => (object) [],
                'timestamps' => (object) [],
            ];

            if ($zip) {
                $chapter_filesets->downloads = [];
            }

            $text_plain = $this->getFileset($bible->filesets, 'text_plain', $book->book_testament);
            if ($text_plain) {
                $text_controller = new TextController();
                $verses = $text_controller->index($text_plain->id, $book_id, $chapter)->original['data'] ?? [];
                if (!empty($verses)) {
                    $chapter_filesets->text->verses = $verses;
                }
            }

            $text_format = $this->getFileset($bible->filesets, 'text_format', $book->book_testament);
            if ($text_format) {
                $fileset_controller = new BibleFileSetsController();
                $formatted_verses = $fileset_controller->show($text_format->id, $text_format->set_type_code, 'v4_chapter_filesets_show')->original['data'] ?? [];
                if (!empty($formatted_verses)) {
                    $path = $formatted_verses[0]['path'];
                    $cache_params = [$bible_id, $book_id, $chapter, $text_format->id];
                    $formatted_verses = cacheRemember('bible_chapter_formatted_verses', $cache_params, now()->addDay(), function () use ($path) {
                        try {
                            $client = new Client();
                            $html = $client->get($path);
                            $body = $html->getBody() . '';
                            return $body;
                        } catch (Exception $e) {
                            return false;
                        }
                    });
                    if ($formatted_verses) {
                        $chapter_filesets->text->formatted_verses = $formatted_verses;
                    }
                }
            }

            $drama_all = $drama === 'all';

            if ($drama === 'drama' || $drama_all) {
                $chapter_filesets = $this->getAudioFilesetData($chapter_filesets, $bible, $book, $chapter, 'audio_drama', 'drama', $zip, 'audio', 'non_drama', !$drama_all && $zip);

                if (!empty($user) && $zip && isset($chapter_filesets->audio->drama)) {
                    $fileset_id = $chapter_filesets->audio->drama['fileset']['id'];

                    cacheRemember('v4_user_download', [$user->id, $fileset_id], now()->addDay(), function () use ($user, $fileset_id) {
                        UserDownload::create([
                            'user_id'        => $user->id,
                            'fileset_id'     => $fileset_id,
                        ]);
                        return true;
                    });
                }
            }

            if ($drama === 'non-drama' || $drama_all) {
                $chapter_filesets = $this->getAudioFilesetData($chapter_filesets, $bible, $book, $chapter, 'audio', 'non_drama', $zip, 'audio_drama', 'drama', !$drama_all && $zip);

                if (!empty($user) && $zip && isset($chapter_filesets->audio->non_drama)) {
                    $fileset_id = $chapter_filesets->audio->non_drama['fileset']['id'];
                    cacheRemember('v4_user_download', [$user->id, $fileset_id], now()->addDay(), function () use ($user, $fileset_id) {
                        UserDownload::create([
                            'user_id'        => $user->id,
                            'fileset_id'     => $fileset_id,
                        ]);
                        return true;
                    });
                }
            }

            $video_stream = $this->getFileset($bible->filesets, 'video_stream', $book->book_testament);
            if ($video_stream) {
                $fileset_controller = new BibleFileSetsController();
                $gospel_films = $fileset_controller->show($video_stream->id, $video_stream->set_type_code, 'v4_chapter_filesets_show')->original['data'] ?? [];
                $chapter_filesets->video->gospel_films = array_map(function ($gospel_film) use ($video_stream) {
                    unset($video_stream->laravel_through_key);
                    unset($video_stream->meta);
                    $gospel_film['fileset'] = $video_stream;
                    return $gospel_film;
                }, $gospel_films);
            }

            $video_stream_controller = new VideoStreamController();
            try {
                $jesus_films = $video_stream_controller->jesusFilmChapters($bible->language->iso)->original;
            } catch (Exception $e) {
                $jesus_films = [];
            }

            if (isset($jesus_films['verses'])) {
                $verses = $jesus_films['verses'];
                $metadata = $jesus_films['meta'];
                $films = [];

                foreach ($verses as $key => $verse) {
                    if (!$verse) {
                        continue;
                    }
                    foreach ($verse as $book_key => $chapters) {
                        foreach ($chapters as $chapter_key => $item) {
                            if (substr(strtoupper($book_key), 0, 3) === $book->id && intval($chapter_key) === intval($chapter)) {
                                $films[] = (object) ['component_id' => $key, 'verses' => $item];
                            }
                        }
                    }
                }
                $chapter_filesets->video->jesus_films = collect($films)->map(function ($film) use ($metadata) {
                    $film->meta = $metadata[$film->component_id] ?? [];
                    return $film;
                });
            }

            return $chapter_filesets;
        });

        $result->filesets = $chapter_filesets;
        $result->timestamps = $result->filesets->timestamps;
        unset($result->filesets->timestamps);
        return $this->replyWithDownload($result, $zip, $bible, $book, $chapter);
    }

    public function getFileset(
        ?\Illuminate\Support\Collection $filesets,
        string $type,
        string $testament
    ) {
        foreach ($filesets as $fileset) {
            $fileset->addMetaRecordsAsAttributes();
        }

        return BibleFilesetService::getFilesetFromValidFilesets($filesets, $type, $testament);
    }

    private function getAudioFilesetData($results, $bible, $book, $chapter, $type, $name, $download, $secondary_type, $secondary_name, $get_secondary = false)
    {
        if (!$download) {
            $download = false;
        }

        $fileset_controller = new BibleFileSetsController();
        $fileset = $this->getStreamNonStreamFileset($download, $bible, $type, $book);

        if (!$fileset && $get_secondary) {
            $name = $secondary_name;
            $fileset = $this->getStreamNonStreamFileset($download, $bible, $secondary_type, $book);
        }

        if ($fileset) {
            $fileset = BibleFileset::with('meta')
            ->where([
                'id' => $fileset->id,
                'set_type_code' => $fileset->set_type_code,
                'set_size_code' => $fileset->set_size_code,
            ])->first();
            $fileset->addMetaRecordsAsAttributes();
            unset($fileset->meta);
            // Get fileset
            $fileset_result = !empty($fileset->id)
                ? $fileset_controller
                    ->show($fileset->id, $fileset->set_type_code, 'v4_chapter_filesets_show')
                    ->original['data'] ?? []
                : [];
            if (!empty($fileset_result)) {
                $results->audio->$name = $fileset_result[0];
                $results->audio->$name['fileset'] = $fileset;

                if (isset($fileset_result[0]['multiple_mp3'])) {
                    $fileset_type = $fileset->set_type_code;
                    $results->audio->$name['fileset']->set_type_code = $fileset_type . '_stream';
                    unset($fileset_result[0]['multiple_mp3']);
                }

                if ($download) {
                    $file_name = $fileset->id . '-' . $book->id . '-' . $chapter . '.mp3';
                    $results->downloads[] = (object) ['path' => $results->audio->$name['path'], 'file_name' => $file_name];
                    $results->audio->$name['path'] = $file_name;
                }
            }

            // Get timestamps
            $audio_controller = new AudioController();
            $audioTimestamps = !empty($fileset->id)
                ? $audio_controller
                    ->timestampsByReference($fileset->id, $book->id, $chapter)
                    ->original['data'] ?? []
                : [];
            $results->timestamps->$name = $audioTimestamps;
        }

        return $results;
    }

    private function getStreamNonStreamFileset($download, $bible, $type, $book)
    {
        $stream = $download ? false : $this->getFileset($bible->filesets, $type . '_stream', $book->book_testament);
        $non_stream = $this->getFileset($bible->filesets, $type, $book->book_testament);
        return $stream ? $stream :  $non_stream;
    }

    private function replyWithDownload($result, $zip, $bible, $book, $chapter)
    {
        if (!$zip) {
            return $this->reply($result);
        }

        $public_dir = public_path() . '/downloads';
        if (!File::exists($public_dir)) {
            File::makeDirectory($public_dir);
        }
        $file_name = $bible->id . '-' . $book->id . '-' . $chapter . '.zip';
        $zip_file = rand() . time() . '-' . $file_name;
        $file_to_path = $public_dir . '/' . $zip_file;
        $zip = new ZipArchive;
        if ($zip->open($file_to_path, ZipArchive::CREATE) === true) {
            $zip->addFromString('contents.json', json_encode($result));

            foreach ($result->filesets->downloads as $download) {
                try {
                    $client = new Client();
                    $mp3 = $client->get($download->path);
                    $zip->addFromString($download->file_name, $mp3->getBody());
                } catch (\Throwable $th) {
                    \Log::channel('errorlog')->error($th->getMessage());
                }
            }
            unset($result->filesets->downloads);
            $zip->close();
        }

        $headers = ['Content-Type' => 'application/octet-stream'];
        $response = response()->download($file_to_path, $file_name, $headers)->deleteFileAfterSend(true);
        return $response;
    }

    /**
     * @OA\Get(
     *     path="/bibles/{bible_id}/chapter/annotations",
     *     tags={"Bibles"},
     *     summary="Bible chapter annotations",
     *     description="Bible chapter annotations",
     *     operationId="v4_internal_bible.chapter.annotations",
     *     security={{"dbp_user_token":{}}},
     *     @OA\Parameter(
     *          name="bible_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Bible/properties/id"),
     *          description="The Bible ID to retrieve the chapter information for"
     *     ),
     *     @OA\Parameter(
     *          name="book_id",
     *          in="query",
     *          description="Will filter the results by the given book. For a complete list see the `book_id` field in the `/bibles/books` route.",
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id")
     *     ),
     *     @OA\Parameter(
     *          name="chapter",
     *          in="query",
     *          description="Will filter the results by the given chapter",
     *          @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The requested bible chapter annotations",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible.chapter.annotations"))
     *     )
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_bible.chapter.annotations",
     *   title="Bible chapter annotations response",
     *   description="The v4 bible chapter annotations response.",
     * )
     */
    public function annotations(Request $request, $bible_id)
    {
        $bible = Bible::whereId($bible_id)->first();
        if (!$bible) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError('Bible not found');
        }

        $user = $request->user();

        // Validate Project / User Connection
        if (!$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $book_id = checkParam('book_id');
        $chapter = checkParam('chapter');
        $chapter_max_verses = 180;
        $limit              = (int) (checkParam('limit') ?? $chapter_max_verses);
        $limit              = $limit > $chapter_max_verses ? $chapter_max_verses : $limit;

        if ($book_id) {
            $book = Book::whereId($book_id)->first();
            if (!$book) {
                return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError('Book not found');
            }
        }

        $result = (object) [];
        $highlights_controller = new HighlightsController();
        $bookmarks_controller = new BookmarksController();
        $notes_controller = new NotesController();

        $request->merge(['bible_id' => $bible_id, 'limit' => $limit]);
        $result->highlights = $highlights_controller->index($request, $user->id)->original['data'];
        $result->bookmarks = $bookmarks_controller->index($request, $user->id)->original['data'];
        $result->notes = $notes_controller->index($request, $user->id)->original['data'];

        $result->bible_id = $bible->id;
        $result->book_id = $book_id;
        $result->chapter = $chapter;


        return $this->reply($result);
    }
}
