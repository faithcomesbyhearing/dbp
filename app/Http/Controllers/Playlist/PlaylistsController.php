<?php

namespace App\Http\Controllers\Playlist;

use Spatie\Fractalistic\ArraySerializer;
use App\Traits\AccessControlAPI;
use App\Http\Controllers\APIController;
use App\Models\Bible\Bible;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleFileTimestamp;
use App\Models\Language\Language;
use App\Models\Plan\UserPlan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistFollower;
use App\Models\Playlist\PlaylistItems;
use App\Models\Bible\BibleVerse;
use App\Traits\CallsBucketsTrait;
use App\Traits\CheckProjectMembership;
use App\Transformers\PlaylistTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Plans\PlaylistService;

class PlaylistsController extends APIController
{
    use AccessControlAPI;
    use CheckProjectMembership;
    use CallsBucketsTrait;

    protected $items_limit = 1000;
    private $playlist_service;

    public function __construct()
    {
        parent::__construct();
        $this->playlist_service = new PlaylistService();
    }

    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     path="/playlists",
     *     tags={"Playlists"},
     *     summary="List a user's playlists",
     *     operationId="v4_internal_playlists.index",
     *     @OA\Parameter(
     *          name="featured",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Playlist/properties/featured"),
     *          description="Return featured playlists"
     *     ),
     *     @OA\Parameter(
     *          name="show_details",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Give full details of the playlist"
     *     ),
     *     @OA\Parameter(
     *          name="show_text",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Enable the full details of the playlist and retrieve the text of the items"
     *     ),
     *     @OA\Parameter(
     *          name="iso",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso"),
     *          description="The iso code to filter plans by. For a complete list see the `iso` field in the `/languages` route"
     *     ),
     *     security={{"api_token":{}}},
     *     @OA\Parameter(ref="#/components/parameters/limit"),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(ref="#/components/parameters/sort_by"),
     *     @OA\Parameter(ref="#/components/parameters/sort_dir"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_playlist_index"))
     *     )
     * )
     *
     * @param $user_id
     *
     * @return mixed
     *
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_playlist_index",
     *   description="The v4 playlist index response.",
     *   title="User playlists",
     *   allOf={
     *      @OA\Schema(ref="#/components/schemas/pagination"),
     *   },
     *   @OA\Property(
     *      property="data",
     *      type="array",
     *      @OA\Items(ref="#/components/schemas/v4_playlist")
     *   )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Validate Project / User Connection
        if (!empty($user) && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $sort_by    = checkParam('sort_by') ?? 'name';
        $sort_dir   = checkParam('sort_dir') ?? 'asc';
        $iso = checkParam('iso');

        $featured = checkBoolean('featured') || empty($user);
        $limit    = (int) (checkParam('limit') ?? 25);

        $show_details = checkBoolean('show_details');
        $show_text = checkBoolean('show_text');
        if ($show_text) {
            $show_details = $show_text;
        }

        $language_id = null;
        if ($iso !== null) {
            $language_id = cacheRemember('v4_language_id_from_iso', [$iso], now()->addDay(), function () use ($iso) {
                return optional(Language::where('iso', $iso)->select('id')->first())->id;
            });
        }
        
        if ($featured) {
            $cache_params = [$show_details, $featured, $sort_by, $sort_dir, $limit, $show_text, $language_id];
            $playlists = cacheRemember('v4_playlist_index', $cache_params, now()->addDay(), function () use ($show_details, $user, $featured, $sort_by, $sort_dir, $limit, $show_text, $language_id) {
                return $this->getPlaylists($show_details, $user, $featured, $sort_by, $sort_dir, $limit, $show_text, $language_id);
            });
            return $this->reply($playlists);
        }


        return $this->reply($this->getPlaylists($show_details, $user, $featured, $sort_by, $sort_dir, $limit, $show_text, $language_id));
    }

    private function getPlaylists($show_details, $user, $featured, $sort_by, $sort_dir, $limit, $show_text, $language_id)
    {
        $has_user = !empty($user);
        $featured = $featured || !$has_user;

        $select = ['user_playlists.*'];

        $following_playlists = [];
        if ($has_user) {
            $following_playlists = PlaylistFollower::where('user_id', $user->id)->get();
        }

        $playlists = Playlist::with('user')
            ->where('draft', 0)
            ->where('plan_id', 0)
            ->when($show_details, function ($query) use ($user) {
                $query->with(['items' => function ($query_items) use ($user) {
                    if (!empty($user)) {
                        $query_items->withPlaylistItemCompleted($user->id);
                    }
                    $query_items->with(['fileset' => function ($query_fileset) {
                        $query_fileset->with('bible');
                    }]);
                }]);
            })
            ->when($language_id, function ($q) use ($language_id) {
                $q->where('user_playlists.language_id', $language_id);
            })
            ->when($featured, function ($q) {
                $q->where('user_playlists.featured', '1');
            })
            ->unless($featured, function ($q) use ($user, $following_playlists) {
                $q->where('user_playlists.user_id', $user->id)
                    ->orWhereIn('user_playlists.id', $following_playlists->pluck('playlist_id'));
            })
            ->select($select)
            ->orderBy($sort_by, $sort_dir)->paginate($limit);

        if ($has_user) {
            $following_playlists = $following_playlists->pluck('playlist_id', 'playlist_id');
        }

        foreach ($playlists->getCollection() as $playlist) {
            if ($show_details) {
                $playlist->path = route('v4_internal_playlists.hls', ['playlist_id'  => $playlist->id, 'v' => $this->v, 'key' => $this->key]);
            }
            if ($show_text && isset($playlist->items)) {
                foreach ($playlist->items as $item) {
                    $item->verse_text = $item->getVerseText();
                }

                foreach ($playlist->items as $item) {
                    unset($item->fileset);
                }
            }
            $playlist->total_duration = PlaylistItems::where('playlist_id', $playlist->id)->sum('duration');
            $playlist->following = $following_playlists[$playlist->id] ?? false;
        }
        return $playlists;
    }

    /**
     * Store a newly created playlist in storage.
     *
     *  @OA\Post(
     *     path="/playlists",
     *     tags={"Playlists"},
     *     summary="Crete a playlist",
     *     operationId="v4_internal_playlists.store",
     *     security={{"api_token":{}}},
     *     @OA\RequestBody(required=true, description="Fields for User Playlist Creation", @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property(property="name",                  ref="#/components/schemas/Playlist/properties/name"),
     *              @OA\Property(property="draft",                 ref="#/components/schemas/Playlist/properties/draft"),
     *              @OA\Property(property="external_content",      ref="#/components/schemas/Playlist/properties/external_content"),
     *              @OA\Property(property="items",                 ref="#/components/schemas/v4_playlist_items")
     *          )
     *     )),
     *     @OA\Response(response=200, ref="#/components/responses/playlist")
     * )
     *
     * @return \Illuminate\Http\Response|array
     */
    public function store(Request $request)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $name = checkParam('name', true);
        $items = checkParam('items');
        $draft = checkBoolean('draft');
        $external_content = checkParam('external_content');

        $playlist_data = [
            'user_id'           => $user->id,
            'name'              => $name,
            'featured'          => false,
            'draft'             => (bool) $draft
        ];

        if ($external_content) {
            $playlist_data['external_content'] = $external_content;
        }

        $playlist = Playlist::create($playlist_data);

        if ($items) {
            $this->createNewPlaylistItems($playlist, $items);
        }

        return $this->show($request, $playlist->id);
    }

    private function createNewPlaylistItems($playlist, $playlist_items)
    {
        $new_items_size = sizeof($playlist_items);

        if ($new_items_size > $this->items_limit) {
            $allowed_size = $this->items_limit;
            $playlist_items = array_slice($playlist_items, 0, $allowed_size);
        }

        $playlist_items_to_create = [];
        $order = 1;

        foreach ($playlist_items as $playlist_item) {
            $playlist_item = (object) $playlist_item;
            $playlist_item_data = [
                'playlist_id'       => $playlist->id,
                'fileset_id'        => $playlist_item->fileset_id,
                'book_id'           => $playlist_item->book_id,
                'chapter_start'     => $playlist_item->chapter_start,
                'chapter_end'       => $playlist_item->chapter_end,
                'verse_start'       => $playlist_item->verse_start ?? null,
                'verse_end'         => $playlist_item->verse_end ?? null,
                'verses'            => $playlist_items->verses ?? 0,
                'order_column'      => $order
            ];
            $playlist_items_to_create[] = $playlist_item_data;
            $order += 1;
        }
        PlaylistItems::insert($playlist_items_to_create);
        $created_playlist_items = PlaylistItems::where('playlist_id', $playlist->id)->orderBy('order_column')->get();
        foreach ($created_playlist_items as $created_playlist_item) {
            $created_playlist_item->calculateDuration()->save();
            if (!$created_playlist_item->verses) {
                $created_playlist_item->calculateVerses()->save();
            }
        }
        return $created_playlist_items;
    }

    /**
     *
     * @OA\Get(
     *     path="/playlists/{playlist_id}/text",
     *     tags={"Playlists"},
     *     summary="A user's playlist text",
     *     operationId="v4_internal_playlists.show_text",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(
     *          name="playlist_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Playlist/properties/id"),
     *          description="The playlist id"
     *     ),
     *     @OA\Response(response=200, ref="#/components/responses/playlist")
     * )
     *
     * @param $playlist_id
     *
     * @return mixed
     *
     *
     */
    public function showText(Request $request, $playlist_id)
    {
        $user = $request->user();

        // Validate Project / User Connection
        if (!empty($user) && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $playlist = $this->getPlaylist($user, $playlist_id);

        if (!$playlist || (isset($playlist->original) && $playlist->original['error'])) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }

        if (isset($playlist->items)) {
            foreach ($playlist->items as $item) {
                $item->verse_text = $item->getVerseText();
            }
        }

        return $this->reply($playlist->items->pluck('verse_text', 'id'));
    }
    /**
     *
     * @OA\Get(
     *     path="/playlists/{playlist_id}",
     *     tags={"Playlists"},
     *     summary="A user's playlist",
     *     operationId="v4_internal_playlists.show",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(
     *          name="playlist_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Playlist/properties/id"),
     *          description="The playlist id"
     *     ),
     *     @OA\Parameter(
     *          name="show_text",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Enable the full details of the playlist and retrieve the text of the items"
     *     ),
     *     @OA\Response(response=200, ref="#/components/responses/playlist")
     * )
     *
     * @param $playlist_id
     *
     * @return mixed
     *
     *
     */
    public function show(Request $request, $playlist_id)
    {
        $user = $request->user();
        $show_text = checkBoolean('show_text');

        // Validate Project / User Connection
        if (!empty($user) && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $user_id = $user ? $user->id : 0;
        $playlist = Playlist::withUserAndItemsById($playlist_id, $user_id)->first();

        if (!$playlist || (isset($playlist->original) && $playlist->original['error'])) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }
        
        if ($show_text && isset($playlist->items)) {
            $playlist_text_filesets = $this->getPlaylistTextFilesets($playlist_id);

            foreach ($playlist->items as $item) {
                $item->verse_text = $item->getVerseText($playlist_text_filesets);
                $item->item_timestamps = $item->getTimestamps();
            }
        }

        $playlist->total_duration = $playlist->items->sum('duration');

        return $this->reply(fractal(
            $playlist,
            new PlaylistTransformer(
                [
                    'user' => $user,
                    'v' => $this->v,
                    'key' => $this->key
                ]
            ),
            new ArraySerializer()
        ));
    }

    /**
     * Update the specified playlist.
     *
     *  @OA\Put(
     *     path="/playlists/{playlist_id}",
     *     tags={"Playlists"},
     *     summary="Update a playlist",
     *     operationId="v4_playlist.update",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="playlist_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Playlist/properties/id")),
     *     @OA\Parameter(name="items", in="query", @OA\Schema(type="string"), description="Comma-separated ids of the playlist items to be sorted or deleted"),
     *     @OA\Parameter(name="delete_items", in="query",@OA\Schema(type="boolean"), description="Will delete all items"),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property(property="name", ref="#/components/schemas/Playlist/properties/name"),
     *              @OA\Property(property="external_content", type="string")
     *          )
     *     )),
     *     @OA\Response(response=200, ref="#/components/responses/playlist")
     * )
     *
     * @param  int $playlist_id
     *
     * @return array|\Illuminate\Http\Response
     */
    public function update(Request $request, $playlist_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $playlist = Playlist::with('items')
            ->with('user')
            ->where('user_id', $user->id)
            ->where('id', $playlist_id)->first();

        if (!$playlist) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }

        $update_values = [];

        $name = checkParam('name');
        if ($name) {
            $update_values['name'] = $name;
        }

        $external_content = checkParam('external_content');
        if ($external_content) {
            $update_values['external_content'] = $external_content;
        }

        $playlist->update($update_values);

        $items = checkParam('items');
        $delete_items = checkBoolean('delete_items');

        if ($items || $delete_items) {
            $items_ids = [];
            if (!$delete_items) {
                $items_ids = explode(',', $items);
                PlaylistItems::setNewOrder($items_ids);
            }
            $deleted_items = PlaylistItems::whereNotIn('id', $items_ids)->where('playlist_id', $playlist->id);
            $deleted_items->delete();
        }

        $playlist = $this->getPlaylist($user, $playlist_id);
        return $this->reply($playlist);
    }

    /**
     * Remove the specified playlist.
     *
     *  @OA\Delete(
     *     path="/playlists/{playlist_id}",
     *     tags={"Playlists"},
     *     summary="Delete a playlist",
     *     operationId="v4_internal_playlists.destroy",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="playlist_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Playlist/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(type="string"))
     *     )
     * )
     *
     * @param  int $playlist_id
     *
     * @return array|\Illuminate\Http\Response
     */
    public function destroy(Request $request, $playlist_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $playlist = Playlist::where('user_id', $user->id)->where('id', $playlist_id)->first();

        if (!$playlist) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }

        $playlist->delete();

        return $this->reply('Playlist Deleted');
    }

    /**
     * Follow the specified playlist.
     *
     *  @OA\Post(
     *     path="/playlists/{playlist_id}/follow",
     *     tags={"Playlists"},
     *     summary="Follow a playlist",
     *     operationId="v4_internal_playlists.start",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="playlist_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Playlist/properties/id")),
     *     @OA\Parameter(name="follow", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, ref="#/components/responses/playlist")
     * )
     *
     * @param  int $playlist_id
     *
     * @return array|\Illuminate\Http\Response
     */
    public function follow(Request $request, $playlist_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $playlist = Playlist::where('id', $playlist_id)->first();

        if (!$playlist) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }

        $follow = checkBoolean('follow');


        if ($follow) {
            $follower = PlaylistFollower::firstOrNew([
                'user_id'               => $user->id,
                'playlist_id'               => $playlist->id
            ]);
            $follower->save();
        } else {
            $follower = PlaylistFollower::where('playlist_id', $playlist->id)
                ->where('user_id', $user->id);
            $follower->delete();
        }

        $playlist = $this->getPlaylist($user, $playlist_id);
        return $this->reply($playlist);
    }

    /**
     * Store a newly created playlist item.
     *
     *  @OA\Post(
     *     path="/playlists/{playlist_id}/item",
     *     tags={"Playlists"},
     *     summary="Crete a playlist item",
     *     operationId="v4_internal_playlists_items.store",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="playlist_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Playlist/properties/id")),
     *     @OA\RequestBody(ref="#/components/requestBodies/PlaylistItems"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_playlist_items"))
     *     )
     * )
     *
     * @OA\RequestBody(
     *     request="PlaylistItems",
     *     required=true,
     *     description="Fields for Playlist item creation",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *              @OA\Property(property="fileset_id", ref="#/components/schemas/PlaylistItems/properties/fileset_id"),
     *              @OA\Property(property="book_id", ref="#/components/schemas/PlaylistItems/properties/book_id"),
     *              @OA\Property(property="chapter_start", ref="#/components/schemas/PlaylistItems/properties/chapter_start"),
     *              @OA\Property(property="chapter_end", ref="#/components/schemas/PlaylistItems/properties/chapter_end"),
     *              @OA\Property(property="verse_start", ref="#/components/schemas/PlaylistItems/properties/verse_start"),
     *              @OA\Property(property="verse_end", ref="#/components/schemas/PlaylistItems/properties/verse_end")
     *         )
     *     )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v4_playlist_items",
     *   title="User created playlist items",
     *   description="The v4 playlist items creation response.",
     *   @OA\Items(ref="#/components/schemas/PlaylistItemDetail")
     * )
     * @return mixed
     */
    public function storeItem(Request $request, $playlist_id)
    {
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $playlist = Playlist::with('items')
            ->with('user')
            ->where('user_id', $user->id)
            ->where('id', $playlist_id)->first();

        if (!$playlist) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }

        $playlist_items = json_decode($request->getContent());
        $single_item = checkParam('fileset_id');

        if ($single_item) {
            $playlist_items = [$playlist_items];
        }
        $created_playlist_items = $this->createPlaylistItems($playlist, $playlist_items);

        return $this->reply($single_item ? $created_playlist_items[0] : $created_playlist_items);
    }

    private function createPlaylistItems($playlist, $playlist_items)
    {
        $created_playlist_items = [];

        $current_items_size = sizeof($playlist->items);
        $new_items_size = sizeof($playlist_items);

        if ($current_items_size + $new_items_size > $this->items_limit) {
            $allowed_size = $this->items_limit - $current_items_size;
            $playlist_items = array_slice($playlist_items, 0, $allowed_size);
        }

        foreach ($playlist_items as $playlist_item) {
            $verses = $playlist_items->verses ?? 0;
            $playlist_item = (object) $playlist_item;
            $created_playlist_item = PlaylistItems::create([
                'playlist_id'       => $playlist->id,
                'fileset_id'        => $playlist_item->fileset_id,
                'book_id'           => $playlist_item->book_id,
                'chapter_start'     => $playlist_item->chapter_start,
                'chapter_end'       => $playlist_item->chapter_end,
                'verse_start'       => $playlist_item->verse_start ?? null,
                'verse_end'         => $playlist_item->verse_end ?? null,
                'verses'            => $verses
            ]);
            $created_playlist_item->calculateDuration()->save();
            if (!$verses) {
                $created_playlist_item->calculateVerses()->save();
            }
            $created_playlist_items[] = $created_playlist_item;
        }

        return $created_playlist_items;
    }

    public function createTranslatedPlaylistItems($playlist, $playlist_items)
    {
        return $this->playlist_service->createTranslatedPlaylistItems($playlist, $playlist_items);
    }

    /**
     * Complete a playlist item.
     *
     *  @OA\Post(
     *     path="/playlists/item/{item_id}/complete",
     *     tags={"Playlists"},
     *     summary="Complete a playlist item",
     *     operationId="v4_internal_playlists_items.complete",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="item_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/PlaylistItems/properties/id")),
     *     @OA\Parameter(name="complete", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_complete_playlist_item"))
     *     )
     * )
     *
     * @OA\Schema (
     *   schema="v4_complete_playlist_item",
     *   description="The v4 plan day complete response",
     *   @OA\Property(property="message", type="string"),
     *   @OA\Property(property="percentage_completed", ref="#/components/schemas/UserPlan/properties/percentage_completed")
     * )
     * @return mixed
     */
    public function completeItem(Request $request, $item_id)
    {
        $complete = checkParam('complete') ?? true;

        return DB::transaction(function () use ($request, $item_id, $complete) {
            // Validate Project / User Connection
            $user = $request->user();
            $user_is_member = $this->compareProjects($user->id, $this->key);

            if (!$user_is_member) {
                return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
            }

            $playlist_item = PlaylistItems::where('id', $item_id)->first();

            if (!$playlist_item) {
                return $this->setStatusCode(404)->replyWithError('Playlist Item Not Found');
            }

            $user_plan = UserPlan::join('plans', function ($join) use ($user) {
                $join->on('user_plans.plan_id', '=', 'plans.id')->where('user_plans.user_id', $user->id);
            })
                ->join('plan_days', function ($join) use ($playlist_item) {
                    $join
                        ->on('plan_days.plan_id', '=', 'plans.id')
                        ->where('plan_days.playlist_id', $playlist_item->playlist_id);
                })
                ->select('user_plans.*')
                ->first();

            if (!$user_plan) {
                return $this->setStatusCode(404)->replyWithError('User Plan Not Found');
            }

            $complete = $complete && $complete !== 'false';

            if ($complete) {
                $playlist_item->complete();
            } else {
                $playlist_item->unComplete();
            }

            $result = $complete ? 'completed' : 'not completed';
            $user_plan->calculatePercentageCompleted()->save();

            return $this->reply([
                'percentage_completed' => (int) $user_plan->percentage_completed,
                'message' => 'Playlist Item ' . $result
            ]);
        });
    }

    /**
     *
     * @OA\Get(
     *     path="/playlists/{playlist_id}/translate",
     *     tags={"Playlists"},
     *     summary="Translate a user's playlist",
     *     operationId="v4_internal_playlists.translate",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(
     *          name="playlist_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Playlist/properties/id"),
     *          description="The playlist id"
     *     ),
     *     @OA\Parameter(
     *          name="bible_id",
     *          in="query",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Bible/properties/id"),
     *          description="The id of the bible that will be used to translate the playlist"
     *     ),
     *     @OA\Parameter(
     *          name="show_details",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Give full details of the playlist"
     *     ),
     *     @OA\Response(response=200, ref="#/components/responses/playlist")
     * )
     *
     * @param $playlist_id
     *
     * @return mixed
     *
     *
     */
    public function translate(Request $request, $playlist_id, $user = false, $compare_projects = true, $plan_id = 0)
    {
        $user = $user ? $user : $request->user();

        // Validate Project / User Connection
        if ($compare_projects && !empty($user) && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $show_details = checkBoolean('show_details');
        $bible_id = checkParam('bible_id', true);
        $bible = cacheRemember('bible_translate', [$bible_id], now()->addDay(), function () use ($bible_id) {
            return Bible::whereId($bible_id)->first();
        });

        if (!$bible) {
            return $this->setStatusCode(404)->replyWithError('Bible Not Found');
        }

        $playlist = Playlist::findOne($playlist_id);

        if (!$playlist || (isset($playlist->original) && $playlist->original['error'])) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }

        $playlist = $this->playlist_service->translate($playlist_id, $bible, $user->id);

        if ($show_details && isset($playlist->items)) {
            foreach ($playlist->items as $item) {
                $item->verse_text = $item->getVerseText([]);
                $item->item_timestamps = $item->getTimestamps();
            }
        }

        return $this->reply(fractal(
            $playlist,
            new PlaylistTransformer(
                [
                    'user' => $user,
                    'v' => $this->v,
                    'key' => $this->key
                ]
            ),
            new ArraySerializer()
        ));
    }

    /**
     *  @OA\Post(
     *     path="/playlists/{playlist_id}/draft",
     *     tags={"Playlists"},
     *     summary="Change draft status in a playlist.",
     *     operationId="v4_internal_playlists.draft",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="playlist_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Playlist/properties/id")),
     *     @OA\Parameter(name="draft", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(type="string"))
     *     )
     * )
     */
    public function draft(Request $request, $playlist_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $playlist = Playlist::where('user_id', $user->id)->where('id', $playlist_id)->first();

        if (!$playlist) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }

        $draft = checkBoolean('draft');
        $playlist->draft = $draft;

        $playlist->save();

        return $this->reply('Playlist draft status changed');
    }

    public function getFileset($filesets, $type, $size)
    {
        return $this->playlist_service->getFileset($filesets, $type, $size);
    }

    private function getCodecMetadata($fileset)
    {
        return $this->playlist_service->getCodecMetadata($fileset);
    }

    public function itemHls(Response $response, $playlist_item_id, $book_id = null, $chapter = null, $verse_start = null, $verse_end = null)
    {
        $download = checkBoolean('download');

        $playlist_item = $this->getPlaylistItemFromLocation($playlist_item_id, $book_id, $chapter, $verse_start, $verse_end);
        if (!$playlist_item) {
            return $this->setStatusCode(404)->replyWithError('Playlist Item Not Found');
        }

        $hls_playlist = $this->getHlsPlaylist($response, [$playlist_item], $download);

        if ($download) {
            return $this->reply(['hls' => $hls_playlist['file_content'], 'signed_files' => $hls_playlist['signed_files']]);
        }

        return response($hls_playlist['file_content'], 200, [
            'Content-Disposition' => 'attachment; filename="item_' . $playlist_item->id . '.m3u8"',
            'Content-Type'        => 'application/x-mpegURL'
        ]);
    }

    private function getPlaylistItemFromLocation($playlist_item_id, $book_id, $chapter, $verse_start, $verse_end)
    {
        if (!$book_id) {
            return PlaylistItems::whereId($playlist_item_id)->first();
        }

        $fileset_id = $playlist_item_id;

        $fileset = cacheRemember('fileset', [$fileset_id], now()->addHours(12), function () use ($fileset_id) {
            return BibleFileset::whereId($fileset_id)->first();
        });

        $playlist_item = [
            'id' => implode('-', [$fileset_id, $book_id, $chapter, $verse_start, $verse_end]),
            'fileset' => $fileset,
            'book_id' => $book_id,
            'chapter_start' => $chapter,
            'chapter_end' => $chapter,
            'verse_start' => strtolower($verse_start) === 'null' ? null : $verse_start,
            'verse_end' => strtolower($verse_end) === 'null' ? null : $verse_end,
        ];

        return (object) $playlist_item;
    }

    public function hls(Response $response, $playlist_id)
    {
        $download = checkBoolean('download');
        $playlist = Playlist::with('items')->find($playlist_id);
        if (!$playlist) {
            return $this->setStatusCode(404)->replyWithError('Playlist Not Found');
        }

        $hls_playlist = $this->getHlsPlaylist($response, $playlist->items, $download);

        if ($download) {
            return $this->reply(['hls' => $hls_playlist['file_content'], 'signed_files' => $hls_playlist['signed_files']]);
        }

        return response($hls_playlist['file_content'], 200, [
            'Content-Disposition' => 'attachment; filename="' . $playlist_id . '.m3u8"',
            'Content-Type'        => 'application/x-mpegURL'
        ]);
    }

    private function processHLSAudio($bible_files, $signed_files, $transaction_id, $item, $download)
    {
        $durations = [];
        $hls_items = '';
        foreach ($bible_files as $bible_file) {
            if (isset($bible_file->streamBandwidth) && isset($bible_file->fileset)) {
                $currentBandwidth = $bible_file->streamBandwidth->first();
                $transportStream = sizeof($currentBandwidth->transportStreamBytes) ? $currentBandwidth->transportStreamBytes : $currentBandwidth->transportStreamTS;
    
                // Fix verse audio stream starting from different initial verses causing audio missmatch
                if (isset($item->verse_end) && isset($item->verse_start)) {
                    if (isset($transportStream[0]->timestamp)) {
                        $timestamps_count = BibleFileTimestamp::where('bible_file_id', $transportStream[0]->timestamp->bible_file_id)->count();
                        if ($timestamps_count === $transportStream->count() && $transportStream[0]->timestamp->verse_start !== 0) {
                            $transportStream->prepend((object)[]);
                        }
                    }
    
                    $transportStream = $this->processVersesOnTransportStream($item, $transportStream, $bible_file);
                }
    
                $fileset = $bible_file->fileset;
    
                foreach ($transportStream as $stream) {
                    $durations[] = $stream->runtime;
                    $hls_items .= "\n#EXTINF:$stream->runtime," . $item->id;
                    if (isset($stream->timestamp)) {
                        $hls_items .= "\n#EXT-X-BYTERANGE:$stream->bytes@$stream->offset";
                        $fileset = $stream->timestamp->bibleFile->fileset;
                        $stream->file_name = $stream->timestamp->bibleFile->file_name;
                    }
                    $bible_path = $bible_file->fileset->bible->first()->id;
                    $file_path = 'audio/' . $bible_path . '/' . $fileset->id . '/' . $stream->file_name;
                    if (!isset($signed_files[$file_path])) {
                        $signed_files[$file_path] = $this->signedUrl($file_path, $fileset->asset_id, $transaction_id);
                    }
                    $hls_file_path = $download ? $file_path : $signed_files[$file_path];
                    $hls_items .= "\n" . $hls_file_path;
                }
            }
        }

        return (object) ['hls_items' => $hls_items, 'signed_files' => $signed_files, 'durations' => $durations];
    }

    private function processMp3Audio($bible_files, $signed_files, $transaction_id, $download, $item)
    {
        $durations = [];
        $hls_items = '';
        foreach ($bible_files as $bible_file) {
            if ($bible_file) {
                $default_duration = $bible_file->duration ?? 180;
                $durations[] = $default_duration;
                if (isset($item->id)) {
                    $hls_items .= "\n#EXTINF:$default_duration," . $item->id;
                }
    
                if (isset($bible_file->fileset)) {
                    $bible_data = $bible_file->fileset->bible->first();
    
                    if ($bible_data) {
                        $bible_path = $bible_data->id;
                        $file_path = 'audio/' . $bible_path . '/' . $bible_file->fileset->id . '/' . $bible_file->file_name;
                        $hls_items .= "\n";
                    }
                  
                    if (!isset($signed_files[$file_path])) {
                        $signed_files[$file_path] = $this->signedUrl($file_path, $bible_file->fileset->asset_id, $transaction_id);
                    }
                }
                $hls_file_path = $download ? $file_path : $signed_files[$file_path];
                $hls_items .= "\n" . $hls_file_path;
            }
        }

        return (object) ['hls_items' => $hls_items, 'signed_files' => $signed_files, 'durations' => $durations];
    }

    private function processVersesOnTransportStream($item, $transportStream, $bible_file)
    {
        if ($item->chapter_end  === $item->chapter_start) {
            $transportStream = $transportStream->splice(1, $item->verse_end)->all();
            return collect($transportStream)->slice($item->verse_start - 1)->all();
        }

        $transportStream = $transportStream->splice(1)->all();
        if ($bible_file->chapter_start === $item->chapter_start) {
            return collect($transportStream)->slice($item->verse_start - 1)->all();
        }
        if ($bible_file->chapter_start === $item->chapter_end) {
            return collect($transportStream)->splice(0, $item->verse_end)->all();
        }

        return $transportStream;
    }

    private function getHlsPlaylist($response, $items, $download)
    {
        $signed_files = [];
        $transaction_id = random_int(0, 10000000);

        $durations = [];
        $hls_items = [];

        foreach ($items as $item) {
            if (isset($item->fileset)) {
                $fileset = $item->fileset;

                if (!Str::contains($fileset->set_type_code, 'audio')) {
                    continue;
                }
                $bible_files = BibleFile::with('streamBandwidth.transportStreamTS')
                ->with('streamBandwidth.transportStreamBytes.timestamp.bibleFile')
                ->where([
                    'hash_id' => $fileset->hash_id,
                    'book_id' => $item->book_id,
                ])
                    ->where('chapter_start', '>=', $item->chapter_start)
                    ->where('chapter_start', '<=', $item->chapter_end)
                    ->get();
    
                if ($fileset->set_type_code === 'audio_stream' || $fileset->set_type_code === 'audio_drama_stream') {
                    $result = $this->processHLSAudio($bible_files, $signed_files, $transaction_id, $item, $download);
                    $hls_items[] = $result->hls_items;
                    $signed_files = $result->signed_files;
                    $durations[] = collect($result->durations)->sum();
                } else {
                    $result = $this->processMp3Audio($bible_files, $signed_files, $transaction_id, $download, $item);
                    $hls_items[] = $result->hls_items;
                    $signed_files = $result->signed_files;
                    $durations[] = collect($result->durations)->sum();
                }
            }
        }
        $hls_items = join("\n" . '#EXT-X-DISCONTINUITY', $hls_items);
        $current_file = "#EXTM3U\n";
        $current_file .= '#EXT-X-TARGETDURATION:' . ceil(collect($durations)->sum()) . "\n";
        $current_file .= "#EXT-X-VERSION:4\n";
        $current_file .= '#EXT-X-MEDIA-SEQUENCE:0';
        $current_file .= $hls_items;
        $current_file .= "\n#EXT-X-ENDLIST";

        return ['signed_files' => $signed_files, 'file_content' => $current_file];
    }

    /**
     * @OA\Schema (
     *   type="object",
     *   schema="PlaylistItemDetail",
     *   @OA\Property(property="id", ref="#/components/schemas/PlaylistItems/properties/id"),
     *   @OA\Property(property="bible_id", ref="#/components/schemas/Bible/properties/id"),
     *   @OA\Property(property="fileset_id", ref="#/components/schemas/PlaylistItems/properties/fileset_id"),
     *   @OA\Property(property="book_id", ref="#/components/schemas/PlaylistItems/properties/book_id"),
     *   @OA\Property(property="chapter_start", ref="#/components/schemas/PlaylistItems/properties/chapter_start"),
     *   @OA\Property(property="chapter_end", ref="#/components/schemas/PlaylistItems/properties/chapter_end"),
     *   @OA\Property(property="verse_start", ref="#/components/schemas/PlaylistItems/properties/verse_start"),
     *   @OA\Property(property="verse_end", ref="#/components/schemas/PlaylistItems/properties/verse_end"),
     *   @OA\Property(property="duration", ref="#/components/schemas/PlaylistItems/properties/duration"),
     *   @OA\Property(property="completed", ref="#/components/schemas/PlaylistItems/properties/completed")
     * )
     * @OA\Schema (
     *   type="object",
     *   schema="v4_playlist",
     *   @OA\Property(property="id", ref="#/components/schemas/Playlist/properties/id"),
     *   @OA\Property(property="name", ref="#/components/schemas/Playlist/properties/name"),
     *   @OA\Property(property="featured", ref="#/components/schemas/Playlist/properties/featured"),
     *   @OA\Property(property="created_at", ref="#/components/schemas/Playlist/properties/created_at"),
     *   @OA\Property(property="updated_at", ref="#/components/schemas/Playlist/properties/updated_at"),
     *   @OA\Property(property="external_content", ref="#/components/schemas/Playlist/properties/external_content"),
     *   @OA\Property(property="following", ref="#/components/schemas/Playlist/properties/following"),
     *   @OA\Property(property="user", ref="#/components/schemas/v4_playlist_index_user"),
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_playlist_index_user",
     *   description="The user who created the playlist",
     *   @OA\Property(property="id", type="integer"),
     *   @OA\Property(property="name", type="string")
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_playlist_detail",
     *   allOf={
     *      @OA\Schema(ref="#/components/schemas/v4_playlist"),
     *   },
     *   @OA\Property(property="items",type="array",@OA\Items(ref="#/components/schemas/PlaylistItemDetail"))
     * )
     *
     * @OA\Response(
     *   response="playlist",
     *   description="Playlist Object",
     *   @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_playlist_detail"))
     * )
     */

    public function getPlaylist($user, $playlist_id)
    {
        $user_id = empty($user) ? 0 : $user->id;
        $select = ['user_playlists.*', DB::Raw('IF(playlists_followers.user_id, true, false) as following')];
        $playlist = Playlist::with(['user', 'items' => function ($query_items) use ($user_id) {
            if (!empty($user_id)) {
                $query_items->withPlaylistItemCompleted($user_id);
            }

            $query_items->with(['fileset' => function ($query_fileset) {
                $query_fileset->with('bible');
            }]);
        }])
            ->leftJoin('playlists_followers as playlists_followers', function ($join) use ($user_id) {
                $join->on('playlists_followers.playlist_id', '=', 'user_playlists.id')
                    ->where('playlists_followers.user_id', $user_id);
            })
            ->where('user_playlists.id', $playlist_id)
            ->select($select)
            ->first();

        if (!$playlist) {
            return $this->setStatusCode(404)->replyWithError('No playlist could be found for: ' . $playlist_id);
        }

        if (isset($playlist->items)) {
            $playlist->items = $playlist->items->map(function ($item) {
                if (isset($item->fileset, $item->fileset->bible)) {
                    $bible = $item->fileset->bible->first();
                    if ($bible) {
                        $item->bible_id = $bible->id;
                    }
                }
                unset($item->fileset);
                return $item;
            });
        }

        return $playlist;
    }

    public function getPlaylistTextFilesets($playlist_id)
    {
        $filesets = Arr::pluck(DB::connection('dbp_users')
            ->select('select DISTINCT(fileset_id) from playlist_items where playlist_id = ?', [$playlist_id]), 'fileset_id');

        $filesets_hashes = DB::connection('dbp')
            ->table('bible_filesets')
            ->select(['hash_id', 'id'])
            ->whereIn('id', $filesets)->get();

        $hashes_bibles = DB::connection('dbp')
            ->table('bible_fileset_connections')
            ->select(['hash_id', 'bible_id'])
            ->whereIn('hash_id', $filesets_hashes->pluck('hash_id'))->get();

        $text_filesets = DB::connection('dbp')
            ->table('bible_fileset_connections as fc')
            ->join('bible_filesets as f', 'f.hash_id', '=', 'fc.hash_id')
            ->select(['f.*', 'fc.bible_id'])
            ->where('f.set_type_code', 'text_plain')
            ->whereIn('fc.bible_id', $hashes_bibles->pluck('bible_id'))->get()->groupBy('bible_id');


        $fileset_text_info = $filesets_hashes->pluck('hash_id', 'id');
        $bible_hash = $hashes_bibles->pluck('bible_id', 'hash_id');

        foreach ($filesets as $fileset) {
            if (isset($fileset_text_info[$fileset])) {
                $bible_id = $bible_hash[$fileset_text_info[$fileset]];
                $fileset_text_info[$fileset] = $text_filesets[$bible_id] ?? null;
            }
        }

        return $fileset_text_info;
    }

    public function itemMetadata()
    {
        $fileset_id = checkParam('fileset_id', true);
        $book_id = checkParam('book_id', true);
        $chapter = checkParam('chapter', true);
        $verse_start = checkParam('verse_start') ?? null;
        $verse_end = checkParam('verse_end') ?? null;
        $fileset = BibleFileset::whereId($fileset_id)->first();

        if (!$fileset) {
            return $this->setStatusCode(404)->replyWithError('Fileset Not Found');
        }

        $playlist_item = new PlaylistItems();
        $playlist_item->setAttribute('id', rand());
        $playlist_item->setAttribute('fileset_id', $fileset_id);
        $playlist_item->setAttribute('book_id', $book_id);
        $playlist_item->setAttribute('chapter_start', $chapter);
        $playlist_item->setAttribute('chapter_end', $chapter);
        $playlist_item->setAttribute('verse_start', $verse_start);
        $playlist_item->setAttribute('verse_end', $verse_end);
        $playlist_item->calculateVerses();
        $playlist_item->calculateDuration();

        return $this->reply([
            'metadata' => $playlist_item->metadata,
            'verses' => $playlist_item->verses,
            'duration' => $playlist_item->duration,
            'timestamps' => $playlist_item->getTimestamps(),
        ]);
    }
}
