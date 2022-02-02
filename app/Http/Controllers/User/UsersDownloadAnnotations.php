<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Controllers\APIController;
use App\Traits\AccessControlAPI;
use App\Traits\CallsBucketsTrait;
use App\Traits\CheckProjectMembership;
use App\Models\User\Study\Highlight;
use App\Models\User\Study\Bookmark;
use App\Models\User\Study\Note;
use App\Models\User\Annotations;
use App\Transformers\UsersDownloadAnnotationsTransFormer as Transformer;

class UsersDownloadAnnotations extends APIController
{
    use AccessControlAPI;
    use CallsBucketsTrait;
    use CheckProjectMembership;

    const USERS_DOWNLOAD_ANNOTATIONS_CACHE_KEY = 'users_download_annotations';

    /**
     *
     * @OA\Get(
     *     path="/users/{user_id}/annotations/{bible_id}/{book_id}/{chapter}",
     *     tags={"Annotations"},
     *     summary="Download annotations for specific user and bible fileset",
     *     description="For a given fileset return content (text, audio or video)",
     *     operationId="v4_download",
     *     @OA\Parameter(name="user_id", in="path", description="The User ID", required=true,
     *          @OA\Schema(ref="#/components/schemas/User/properties/id")),
     *      @OA\Parameter(name="bible_id", in="path", description="Will filter the results by the given bible", required=true,
     *          @OA\Schema(ref="#/components/schemas/Bible/properties/id")
     *     ),
     *     @OA\Parameter(name="book_id", in="path",
     *          description="Will filter the results by the given book. For a complete list see the `book_id` field in the `/bibles/books` route.",
     *          @OA\Schema(ref="#/components/schemas/Book/properties/id")
     *     ),
     *     @OA\Parameter(name="chapter", in="path",
     *          description="Will filter the results by the given chapter",
     *          @OA\Schema(ref="#/components/schemas/BibleFile/properties/chapter_start")
     *     ),
     *     @OA\Parameter(name="notes_sort_by", in="query",
     *          description="The field to sort by for the notes",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(name="bookmarks_sort_by", in="query",
     *          description="The field to sort by for the bookmarks",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(name="highlights_sort_by", in="query",
     *          description="The field to sort by for the highlights",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(name="sort_dir", in="query", description="The direction to sort by",
     *          @OA\Schema(type="string",enum={"asc","desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_users_download_annotations.index"))
     *     ),
     * )
     *
     * @OA\Schema (
     *     type="object",
     *     schema="v4_users_download_annotations.index",
     *     description="v4_users_download_annotations.index",
     *     title="v4_users_download_annotations.index",
     *     @OA\Xml(name="v4_users_download_annotations.index"),
     * )
     *
     * @param Request $request
     * @param int $user_id
     * @param string|null $bible_id
     * @param string|null $book_id
     * @param int|null $chapter
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     * @throws \Exception
     */
    public function index(
        Request $request,
        int $user_id,
        string $bible_id,
        string $book_id = null,
        string $chapter = null,
        string $cache_key = UsersDownloadAnnotations::USERS_DOWNLOAD_ANNOTATIONS_CACHE_KEY
    ) {
        $final_user_id = !empty($request->user()) ? $request->user()->id : $user_id;
        $key = $this->getKey();

        if (!$this->compareProjects($final_user_id, $key)) {
            return $this
                ->setStatusCode(Response::HTTP_UNAUTHORIZED)
                ->replyWithError(trans('api.projects_users_not_connected'));
        }

        $cache_params = $this->removeSpaceFromCacheParameters([$final_user_id, $bible_id, $book_id, $chapter, $key]);

        $annotations = cacheRemember(
            $cache_key,
            $cache_params,
            now()->addHours(12),
            function () use ($bible_id, $book_id, $chapter, $final_user_id) {
                $notes = $this->getNotes($final_user_id, $bible_id, $book_id, $chapter);
                $bookmarks = $this->getBookmarks($final_user_id, $bible_id, $book_id, $chapter);
                $highlights = $this->getHighlights($final_user_id, $bible_id, $book_id, $chapter);

                return new Annotations(
                    $notes,
                    $bookmarks,
                    $highlights
                );
            }
        );

        return $this->reply(fractal($annotations, new Transformer));
    }

    /**
     * Get user notes records by user ID given and bible ID
     *
     * @param int $user_id
     * @param string|null $bible_id
     * @param string|null $book_id
     * @param int|null $chapter
     *
     * @return Illuminate\Database\Eloquent\Collection
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_users_download_annotations_notes_index",
     *   description="The v4 user dowload notes index responses all records that belong to a specific user and bible.",
     *   title="v4_users_download_annotations_notes_index",
     *   @OA\Xml(name="v4_users_download_annotations_notes_index"),
     *   @OA\Property(property="data", type="array",
     *     @OA\Items(
     *       @OA\Property(property="id",                ref="#/components/schemas/Note/properties/id"),
     *       @OA\Property(property="bible_id",          ref="#/components/schemas/Bible/properties/id"),
     *       @OA\Property(property="book_id",           ref="#/components/schemas/Book/properties/id"),
     *       @OA\Property(property="chapter",           ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *       @OA\Property(property="verse_start",       ref="#/components/schemas/BibleFile/properties/verse_start"),
     *       @OA\Property(property="verse_end",         ref="#/components/schemas/BibleFile/properties/verse_end"),
     *       @OA\Property(property="notes",             ref="#/components/schemas/Note/properties/notes")
     *     ),
     *   )
     * )
     */
    private function getNotes(
        int $user_id,
        string $bible_id,
        string $book_id = null,
        string $chapter = null
    ) : Collection {
        $sort_by = checkParam('notes_sort_by');
        $sort_dir = $this->checkSortDirParam();

        $order_by = !$sort_by
            ? \DB::raw('user_notes.book_id, user_notes.chapter, user_notes.verse_start')
            : 'user_notes.' . $sort_by;

        return Note::select([
                'user_notes.id',
                'user_notes.bible_id',
                'user_notes.book_id',
                'user_notes.chapter',
                'user_notes.verse_start',
                'user_notes.verse_end',
                'user_notes.notes',
            ])
            ->where('user_notes.user_id', $user_id)
            ->when($bible_id, function ($query) use ($bible_id) {
                $query->where('user_notes.bible_id', $bible_id);
            })->when($book_id, function ($query) use ($book_id) {
                $query->where('user_notes.book_id', $book_id);
            })->when($chapter, function ($query) use ($chapter) {
                $query->where('user_notes.chapter', $chapter);
            })->when($order_by, function ($query) use ($order_by, $sort_dir) {
                $query->orderBy($order_by, $sort_dir);
            })->get();
    }

    /**
     * Get user bookmarks records by user ID given and bible ID
     *
     * @param int $user_id
     * @param string|null $bible_id
     * @param string|null $book_id
     * @param int|null $chapter
     *
     * @return Illuminate\Database\Eloquent\Collection
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_users_download_annotations_bookmarks_index",
     *   description="The v4 user dowload bookmarks index responses all records that belong to a specific user and bible.",
     *   title="v4_users_download_annotations_bookmarks_index",
     *   @OA\Xml(name="v4_users_download_annotations_bookmarks_index"),
     *   @OA\Property(property="data", type="array",
     *     @OA\Items(
     *       @OA\Property(property="id",                ref="#/components/schemas/Bookmark/properties/id"),
     *       @OA\Property(property="bible_id",          ref="#/components/schemas/Bible/properties/id"),
     *       @OA\Property(property="book_id",           ref="#/components/schemas/Book/properties/id"),
     *       @OA\Property(property="chapter",           ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *       @OA\Property(property="verse_start",       ref="#/components/schemas/BibleFile/properties/verse_start"),
     *     ),
     *   )
     * )
     */
    private function getBookmarks(
        int $user_id,
        string $bible_id,
        string $book_id = null,
        string $chapter = null
    ) : Collection {
        $sort_by = checkParam('bookmarks_sort_by');
        $sort_dir = $this->checkSortDirParam();

        $order_by = !$sort_by
            ? \DB::raw('user_bookmarks.book_id, user_bookmarks.chapter, user_bookmarks.verse_start')
            : 'user_bookmarks.' . $sort_by;

        return Bookmark::select([
                'user_bookmarks.id',
                'user_bookmarks.bible_id',
                'user_bookmarks.book_id',
                'user_bookmarks.chapter',
                'user_bookmarks.verse_start',
            ])
            ->where('user_bookmarks.user_id', $user_id)
            ->when($bible_id, function ($query_bible) use ($bible_id) {
                $query_bible->where('user_bookmarks.bible_id', $bible_id);
            })->when($book_id, function ($query_book) use ($book_id) {
                $query_book->where('user_bookmarks.book_id', $book_id);
            })->when($chapter, function ($query_chapter) use ($chapter) {
                $query_chapter->where('user_bookmarks.chapter', $chapter);
            })->when($order_by, function ($query_order) use ($order_by, $sort_dir) {
                $query_order->orderBy($order_by, $sort_dir);
            })->get();
    }

    /**
     * Get user Highlights records by user ID given and bible ID
     *
     * @param int $user_id
     * @param string|null $bible_id
     * @param string|null $book_id
     * @param int|null $chapter
     *
     * @return Illuminate\Database\Eloquent\Collection
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_users_download_annotations_highlights_index",
     *   description="The v4 user dowload highlights index responses all records that belong to a specific user and bible.",
     *   title="v4_users_download_annotations_highlights_index",
     *   @OA\Xml(name="v4_users_download_annotations_highlights_index"),
     *   @OA\Property(property="data", type="array",
     *     @OA\Items(
     *       @OA\Property(property="id",                ref="#/components/schemas/Highlight/properties/id"),
     *       @OA\Property(property="bible_id",          ref="#/components/schemas/Bible/properties/id"),
     *       @OA\Property(property="book_id",           ref="#/components/schemas/Book/properties/id"),
     *       @OA\Property(property="chapter",           ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *       @OA\Property(property="verse_start",       ref="#/components/schemas/BibleFile/properties/verse_start"),
     *       @OA\Property(property="verse_end",         ref="#/components/schemas/BibleFile/properties/verse_end"),
     *       @OA\Property(property="highlighted_color", ref="#/components/schemas/Highlight/properties/highlighted_color")
     *     ),
     *   )
     * )
     */
    private function getHighlights(
        int $user_id,
        string $bible_id,
        string $book_id = null,
        string $chapter = null
    ) : Collection {
        $sort_by = checkParam('highlights_sort_by');
        $sort_dir = $this->checkSortDirParam();

        $order_by = !$sort_by
            ? \DB::raw('user_highlights.book_id, user_highlights.chapter, user_highlights.verse_start')
            : 'user_highlights.' . $sort_by;

        $select_fields = [
            'user_highlights.id',
            'user_highlights.bible_id',
            'user_highlights.book_id',
            'user_highlights.chapter',
            'user_highlights.verse_start',
            'user_highlights.verse_end',
            'user_highlights.highlighted_color',
        ];

        return Highlight::with(['color'])
            ->where('user_id', $user_id)
            ->when($bible_id, function ($query) use ($bible_id) {
                $query->where('user_highlights.bible_id', $bible_id);
            })
            ->when($book_id, function ($query) use ($book_id) {
                $query->where('user_highlights.book_id', $book_id);
            })
            ->when($chapter, function ($query) use ($chapter) {
                $query->where('chapter', $chapter);
            })
            ->select($select_fields)
            ->when($order_by, function ($query) use ($sort_dir, $order_by) {
                $query->orderBy($order_by, $sort_dir);
            })
            ->get();
    }

    private function checkSortDirParam(): string
    {
        $sort_dir = checkParam('sort_dir') ?? 'asc';
        if (!in_array(Str::lower($sort_dir), ['asc', 'desc'])) {
            $sort_dir = 'asc';
        }

        return $sort_dir;
    }
}
