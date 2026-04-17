<?php

namespace App\Http\Controllers\Bible;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
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
                // Support the logic where filesets with same ID exist mainly for text/plain types.
                // In that case, prioritize text_plain type first to get the correct fileset_type_code
                $fileset_from_id = BibleFileset::prioritizeTextPlainType($fileset_id)->first();
                if (!$fileset_from_id) {
                    return null;
                }

                if (!empty($type)) {
                    // if type is specified, use it
                    $fileset_type = $type;
                } else {
                    $fileset_type = $fileset_from_id['set_type_code'];
                }

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
                $normalized_book_id = optional(Book::findBookByAnyIdentifier($book_id))->id;
                if ($fileset->set_type_code === BibleFileset::TYPE_TEXT_PLAIN) {
                    return $this->showTextFilesetChapter(
                        $limit,
                        $fileset,
                        $normalized_book_id,
                        $chapter
                    );
                } else {
                    return $this->getAudioVideoFilesetsToDownload(
                        $fileset,
                        $normalized_book_id,
                        $chapter,
                        $limit
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

    /**
     * Proxies package creation to BBHub (POST /package/create-by-filesets).
     * Same API key rules as v2 `/library/language`: `key` + `v`, no AccessControl.
     *
     * @OA\Post(
     *     path="/download/package-create",
     *     operationId="v4_download_package_create",
     *     tags={"Bibles"},
     *     summary="Create a download package from filesets",
     *     description="Accepts a JSON payload containing fileset IDs and an encryption type, then proxies the request to BBHub package creation.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"filesets","encryptionType"},
     *             @OA\Property(
     *                 property="filesets",
     *                 type="array",
     *                 minItems=1,
     *                 uniqueItems=true,
     *                 @OA\Items(type="string", minLength=1),
     *                 example={"ENGESVN2DA","ENGESVO1DA"}
     *             ),
     *             @OA\Property(
     *                 property="encryptionType",
     *                 type="integer",
     *                 example=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Response from the upstream BBHub service (status and body are proxied).",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 additionalProperties=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Request body must be valid JSON.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Request body must be valid JSON.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed for the request body.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="The filesets field is required.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="BBHub is unavailable.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="BBHub is unavailable.")
     *         )
     *     )
     * )
     */
    public function packageCreate(Request $request): Response|JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            $this->setStatusCode(Response::HTTP_BAD_REQUEST);
            return $this->replyWithError('Request body must be valid JSON.');
        }

        $validator = Validator::make($payload, [
            'filesets'         => 'required|array|min:1',
            'filesets.*'       => 'string|min:1|distinct',
            'encryptionType'   => 'required|integer',
        ]);
        if ($validator->fails()) {
            $this->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
            return $this->replyWithError($validator->errors()->first());
        }

        $baseUrl = rtrim((string) config('services.bbhub.url'), '/');
        $url = $baseUrl . '/package/create-by-filesets';
        $timeout = (int) config('services.bbhub.service_timeout', 60);

        try {
            $upstream = Http::retry(3, 100, function ($exception) {
                return $exception instanceof ConnectionException;
            })
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            \Log::warning('BBHub connection failed after retries', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);
            $this->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
            return $this->replyWithError('BBHub is unavailable.');
        }

        $response = response($upstream->body(), $upstream->status());
        $contentType = $upstream->header('Content-Type');
        if ($contentType) {
            $response->header('Content-Type', $contentType);
        }

        return $response;
    }
}
