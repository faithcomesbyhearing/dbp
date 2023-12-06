<?php

namespace App\Http\Controllers\Bible;

use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\AccessControlAPI;
use App\Traits\CallsBucketsTrait;
use App\Traits\BibleFileSetsTrait;
use App\Http\Controllers\APIController;
use App\Models\User\UserDownload;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\Book;
use App\Models\Bible\BibleFilesetLookup;
use App\Transformers\BibleFileSetsDownloadTransFormer;

class BibleFilesetsDownloadController extends APIController
{
    use AccessControlAPI;
    use CallsBucketsTrait;
    use BibleFileSetsTrait;

    /**
     *
     * @OA\Get(
     *     path="/download/{fileset_id}/{book_id}/{chapter}",
     *     tags={"Bibles"},
     *     summary="Download specific fileset",
     *     description="For a given fileset return content (text, audio or video)",
     *     operationId="v4_download",
     *     @OA\Parameter(name="fileset_id", in="path", description="The fileset ID", required=true,
     *          @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id")
     *     ),
     *     @OA\Parameter(name="book_id", in="path", description="Will filter the results by the given book. For a complete list see the `book_id` field in the `/bibles/books` route.",
     *          required=false,
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id")
     *     ),
     *     @OA\Parameter(name="chapter", in="path", description="Will filter the results by the given chapter", required=false,
     *          @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_filesets_download.index"))
     *     ),
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_bible_filesets_download.index",
     *     description="v4_bible_filesets_download.index",
     *     title="v4_bible_filesets_download.index",
     *     @OA\Xml(name="v4_bible_filesets_download.index"),
     * )
     *
     * @param string|null $fileset_id
     * @param string|null $book_id
     * @param string|null $chapter
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     * @throws \Exception
     */
    public function index(
        Request $request,
        string $fileset_id,
        ?string $book_id = null,
        ?string $chapter = null,
        string $cache_key = 'bible_filesets_download_index'
    ) : JsonResponse {
        $type  = checkParam('type') ?? '';
        $limit = (int) (checkParam('limit') ?? 5000);
        $limit = max($limit, 5000);
        $key = $this->getKey();
        $hasUserLoggedIn = isUserLoggedIn();

        $fileset_cache_params = $this->removeSpaceFromCacheParameters([$fileset_id, $type]);

        $fileset = cacheRemember(
            'single_bible_fileset_download_index',
            $fileset_cache_params,
            now()->addHours(12),
            function () use ($fileset_id, $type) {
                $fileset_from_id = BibleFileset::where('id', $fileset_id)->first();
                if (!$fileset_from_id) {
                    return null;
                }

                $fileset_type = $fileset_from_id['set_type_code'];
                // fixes data issue where text filesets use the same filesetID
                $fileset_type = $this->getCorrectFilesetType($fileset_type, $type);
                return BibleFileset::with('bible')
                    ->uniqueFileset($fileset_id, $fileset_type)
                    ->first();
            }
        );

        if (!$fileset) {
            return $this->setStatusCode(Response::HTTP_NOT_FOUND)->replyWithError(
                trans('api.bible_fileset_errors_404')
            );
        }

        $bulk_access_control = $this->allowedForDownload($fileset);

        if (isset($bulk_access_control->original['error'])) {
            return $bulk_access_control;
        }

        $cache_params = $this->removeSpaceFromCacheParameters([
            $fileset_id,
            $book_id,
            $chapter,
            $key,
            $limit,
            $type,
            $hasUserLoggedIn
        ]);

        $fileset_chapters = cacheRemember(
            $cache_key,
            $cache_params,
            now()->addHours(12),
            function () use ($fileset, $book_id, $chapter, $limit) {
                $asset_id = $fileset->asset_id;
                $bible = optional($fileset->bible)->first();

                $book = $book_id
                    ? Book::where('id', $book_id)
                        ->orWhere('id_osis', $book_id)
                        ->orWhere('id_usfx', $book_id)
                        ->first()
                    : null;

                if ($fileset->set_type_code === BibleFileset::TYPE_TEXT_PLAIN) {
                    return $this->showTextFilesetChapter(
                        $limit,
                        $bible,
                        $fileset,
                        $book,
                        $chapter
                    );
                } else {
                    return $this->showAudioVideoFilesets(
                        $limit,
                        $bible,
                        $fileset,
                        $asset_id,
                        $fileset->set_type_code,
                        $book,
                        $chapter
                    );
                }
            }
        );

        if ($hasUserLoggedIn && !empty($fileset_chapters) && $fileset->isAudio()) {
            $user = $request->user();
            cacheRemember(
                'v4_user_download',
                [$user->id, $fileset_id],
                now()->addDay(),
                function () use ($user, $fileset_id) {
                    return UserDownload::create([
                        'user_id'        => $user->id,
                        'fileset_id'     => $fileset_id,
                    ]);
                }
            );
        }

        return $this->reply($fileset_chapters, [], '');
    }

    /**
     *
     * @OA\Get(
     *     path="/download/list",
     *     tags={"Bibles"},
     *     summary="List of filesets which can be downloaded for this API key",
     *     description="List of filesets which can be downloaded for this API key",
     *     operationId="v4_download_list",
     *     @OA\Parameter(name="limit", in="query", description="The number of search results to return",
     *          @OA\Schema(ref="#/components/schemas/BibleFileset/properties/id", type="integer", default=50)
     *     ),
     *     @OA\Parameter(name="page", in="query", description="The current page of the results",
     *          @OA\Schema(type="integer",default=1)
     *     ),
     *     @OA\Parameter(
     *          name="type",
     *          in="query",
     *          description="Filter by type of content (audio, video, text)",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_bible_filesets_download.list"))
     *     ),
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_bible_filesets_download.list",
     *     description="v4_bible_filesets_download.list",
     *     title="v4_bible_filesets_download.list",
     *     @OA\Xml(name="v4_bible_filesets_download.list"),
     * )
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     * @throws \Exception
     */
    public function list(string $cache_key = 'bible_filesets_download_list') : JsonResponse
    {
        $limit = (int) (checkParam('limit') ?? 50);
        $page = (int) (checkParam('page') ?? 1);
        $type = checkParam('type');
        $limit = min($limit, 50);
        $access_group_ids = getAccessGroups();

        $cache_params = $this->removeSpaceFromCacheParameters([$access_group_ids->toString(), $limit, $page, $type]);

        $filesets = cacheRemember(
            $cache_key,
            $cache_params,
            now()->addHours(12),
            function () use ($limit, $access_group_ids, $type) {
                return BibleFilesetLookup::getDownloadContentByKey(
                    $limit,
                    $access_group_ids,
                    $type
                );
            }
        );

        return $this->reply(fractal($filesets, new BibleFileSetsDownloadTransFormer));
    }
}
