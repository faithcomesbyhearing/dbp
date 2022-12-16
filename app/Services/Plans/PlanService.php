<?php

namespace App\Services\Plans;

use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\StreamBandwidth;
use App\Models\Plan\Plan;
use App\Models\Plan\PlanDay;
use App\Models\Plan\PlanDayComplete;
use App\Models\Plan\UserPlan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\Playlist\PlaylistItemsComplete;
use App\Models\Bible\Bible;
use App\Services\Plans\PlaylistService;
use Illuminate\Database\Eloquent\Collection;

class PlanService
{
    private $playlist_service;
    public function __construct()
    {
        $this->playlist_service = new PlaylistService();
    }

    /**
     * Create and get a new plan from a given bible
     *
     * @param int $plan_id
     * @param Bible $bible
     * @param int $user_id
     * @param bool $draft
     * @param bool $save_completed_items
     * @param bool $calculate_items_duration
     * @param bool $calculate_items_verses
     */
    public function translate(
        int $plan_id,
        Bible $bible,
        int $user_id = 0,
        bool $draft = true,
        bool $save_completed_items = true,
        bool $calculate_items_duration = false,
        bool $calculate_items_verses = false,
    ) : Plan {
        $plan = $this->getPlanWithDaysByIdAndUser($plan_id, $user_id);

        $plan_data = [
            'user_id'               => $user_id,
            'name'                  => $plan->name . ': ' . $bible->language->name . ' ' . substr($bible->id, -3),
            'featured'              => false,
            'draft'                 => $draft,
            'suggested_start_date'  => $plan->suggested_start_date,
            'thumbnail'             => $plan->thumbnail,
            'language_id'           => $bible->language_id,
        ];

        $new_plan = Plan::create($plan_data);
        $translation_data = [];
        $translated_percentage = 0;
        $play_day_data = [];
        $audio_fileset_types = collect(['audio_stream', 'audio_drama_stream', 'audio', 'audio_drama']);
        $bible_audio_filesets = $bible->filesets->whereIn('set_type_code', $audio_fileset_types);
        $count_plan_days = 0;
        $playlist_ids = [];
        $valid_bible_audio_filesets = $this->playlist_service->getValidAudioStreamFilesets($bible_audio_filesets);

        foreach ($plan->days as $day) {
            $playlist_ids[] = $day->playlist_id;
        }

        $playlists = Playlist::findByUserAndIds($user_id, $playlist_ids);

        $playlists_to_create = [];
        $translated_items = [];
        foreach ($plan->days as $day) {
            if (isset($playlists[$day->playlist_id])) {
                $playlist_by_day = $playlists[$day->playlist_id];
                $playlist_translated =$this->translatePlaylist(
                    $playlist_by_day,
                    $user_id,
                    $new_plan->id,
                    $bible,
                    $audio_fileset_types,
                    $valid_bible_audio_filesets
                );
                $playlists_to_create[] = $playlist_translated['playlist_data'];
                $translation_data[$day->playlist_id] = $playlist_translated["translation_data"];
                $translated_percentage += $playlist_translated["translated_percentage"];
                $translated_items[$day->playlist_id] = $playlist_translated["translated_items"];

                if ($day->hasContentAvailable($playlist_by_day)) {
                    $count_plan_days += 1;
                }
            }
        }

        Playlist::insert($playlists_to_create);

        $new_playlists = Playlist::findByUserAndPlan($user_id, $new_plan->id);

        $new_day_playlist_ids = [];
        $order = 1;

        foreach ($new_playlists as $new_palyslist_index => $new_playlist) {
            $play_day_data[] = [
                'plan_id'               => $new_plan->id,
                'playlist_id'           => $new_playlist->id,
                'order_column'          => $order,
            ];
            $order += 1;
            $new_day_playlist_ids[$playlist_ids[$new_palyslist_index]] = $new_playlist->id;
        }

        $playlist_items_to_create = [];
        $playlist_items_to_create_indexed = [];

        foreach ($translated_items as $playlist_id_key => $playlist_translated_item) {
            $order = 0;
            foreach ($playlist_translated_item as $translated_item) {
                $new_playlist_id_key = $new_day_playlist_ids[$playlist_id_key];
                $playlist_item_data = [
                    'playlist_id'   => $new_playlist_id_key,
                    'fileset_id'    => $translated_item['fileset_id'],
                    'book_id'       => $translated_item['book_id'],
                    'chapter_start' => $translated_item['chapter_start'],
                    'chapter_end'   => $translated_item['chapter_end'],
                    'verse_start'   => $translated_item['verse_start'] ?? null,
                    'verse_end'     => $translated_item['verse_end'] ?? null,
                    'verses'        => $translated_item['verses'] ?? 0,
                    'order_column'  => $translated_item['order_column'] ?? $order
                ];
                $key_translated = implode('-', $playlist_item_data);
                $playlist_items_to_create_indexed[$new_playlist_id_key][$key_translated] = [
                    "translated_id" => $translated_item['translated_id'],
                    "playlist_id_key" => $playlist_id_key
                ];
                $playlist_items_to_create[] = $playlist_item_data;
                $order += 1;
            }
        }

        if ($calculate_items_duration === true) {
            $this->calculateDurationForPlaylistItems($playlist_items_to_create);
        }

        if ($calculate_items_verses === true) {
            $bible_filesets = $bible
                ->filesets
                ->where('set_type_code', 'text_plain');

            $this->calculateVersesForPlaylistItems($playlist_items_to_create, $bible_filesets);
        }

        PlaylistItems::insert($playlist_items_to_create);

        $new_items = PlaylistItems::findByIdsWithFilesetRelation($new_day_playlist_ids);

        if (!$new_items->isEmpty()) {
            $this->linkTranslationPlaylistItems(
                $new_items,
                $playlist_items_to_create_indexed,
                $translation_data,
            );

            if ($user_id && $save_completed_items === true) {
                $this->createCompletePlaylistItems(
                    $user_id,
                    $new_items,
                    $playlist_items_to_create_indexed,
                    $translation_data,
                );
            }
        }

        PlanDay::insert($play_day_data);

        UserPlan::create([
            'user_id'               => $user_id,
            'plan_id'               => $new_plan->id
        ]);

        $old_plan_days_complete_indexed = [];

        if ($user_id && $save_completed_items === true) {
            $old_plan_days_complete_indexed = $this->getPlanDaysCompleteIndexed($plan->days);
        }

        $plan = $this->getPlanWithDaysByIdAndUser($new_plan->id, $user_id);

        if ($user_id && $save_completed_items === true) {
            $new_plist_id_indexed_old_plist_id = $this->gePlaylistIndexedNewAndOld($new_playlists, $playlist_ids);
            $this->createCompletePlayDays(
                $user_id,
                $plan->days,
                $new_plist_id_indexed_old_plist_id,
                $old_plan_days_complete_indexed,
            );
        }

        $translated_percentage = $count_plan_days > 0
            ? $translated_percentage / $count_plan_days
            : 0;

        $plan->translation_data = $this->transformTranslationData($translation_data);
        $plan->translated_percentage = $translated_percentage*100;

        return $plan;
    }


    /**
     * Get the bible filesets attached to the fileset attached to each palylist item
     *
     * @param Array $playlist_items_to_create
     *
     * @return Collection list filesets indexed by ID
     */
    public function getBibleFilesetsFromPlaylistItems(Array $playlist_items_to_create) : Collection
    {
        $fileset_ids = [];

        foreach ($playlist_items_to_create as $item_to_create) {
            $fileset_ids[$item_to_create['fileset_id']] = true;
        }

        return BibleFileset::select(['id', 'hash_id', 'set_type_code'])
            ->whereIn('id', array_keys($fileset_ids))
            ->get()
            ->keyBy('id');
    }

    /**
     * Get the bible files attached to the fileset attached to each palylist item
     *
     * @param Array $playlist_items_to_create
     * @param Collection $bible_filesets - bible fileset collection indexed by filese ID
     *
     * @return Array
     */
    public function getBibleFilesFromPlaylistItems(
        Array $playlist_items_to_create,
        Collection $bible_filesets
    ) : Array|Collection {
        $bible_files = BibleFile::select(['id', 'hash_id', 'book_id', 'chapter_start', 'duration'])
            ->with('streamBandwidth.transportStreamTS')
            ->with('streamBandwidth.transportStreamBytes');

        $flag_constraint = false;
        foreach ($playlist_items_to_create as $item_to_create) {
            $bible_files->orWhere(
                function ($query_or_duration) use ($item_to_create, $bible_filesets, &$flag_constraint) {
                    if (isset($bible_filesets[$item_to_create['fileset_id']])) {
                        $flag_constraint = true;
                        $query_or_duration
                            ->where('chapter_start', '>=', $item_to_create['chapter_start'])
                            ->where('chapter_start', '<=', $item_to_create['chapter_end'])
                            ->where('hash_id', $bible_filesets[$item_to_create['fileset_id']]->hash_id)
                            ->where('book_id', $item_to_create['book_id']);
                    }
                }
            );
        }

        return $flag_constraint ? $bible_files->get() : [];
    }

    /**
     * Filter the bible files array according to the chapter values of the playlist item
     *
     * @param Array $bible_files
     * @param Array $item_to_create
     *
     * @return Array
     */
    public function filterBibleFilesByChapter(Array $bible_files, Array $item_to_create) : ?Array
    {
        return array_filter(
            $bible_files,
            function ($bible_file) use ($item_to_create) {
                return $bible_file->chapter_start >= $item_to_create['chapter_start'] &&
                    $bible_file->chapter_start <= $item_to_create['chapter_end'];
            }
        );
    }

    /**
     * Get transportStream from a bible file
     *
     * @param StreamBandwidth $current_band_width
     * @param Array $item_to_create
     */
    public function getTransportStreamFromBibleFile(StreamBandwidth $current_band_width, Array $item_to_create)
    {
        $transportStream = sizeof($current_band_width->transportStreamBytes)
            ? $current_band_width->transportStreamBytes
            : $current_band_width->transportStreamTS;
        if ($item_to_create['verse_end'] && $item_to_create['verse_start']) {
            $transportStream = $this->processVersesOnTransportStream(
                $item_to_create,
                $transportStream,
                $bible_file
            );
        }

        return $transportStream;
    }

    /**
     * Update the duration property for each playlist item
     * @param Array &$playlist_items_to_create
     *
     * @return void
     */
    public function calculateDurationForPlaylistItems(Array &$playlist_items_to_create) : void
    {
        $bible_filesets = $this->getBibleFilesetsFromPlaylistItems($playlist_items_to_create);
        $bible_files = $this->getBibleFilesFromPlaylistItems($playlist_items_to_create, $bible_filesets);

        $bible_files_indexed = [];

        foreach ($bible_files as $bible_file) {
            $bible_files_indexed[$bible_file->hash_id][$bible_file->book_id][] = $bible_file;
        }

        foreach ($playlist_items_to_create as &$item_to_create) {
            $bible_fileset_attached = $bible_filesets[$item_to_create['fileset_id']];

            if (isset($bible_files_indexed[$bible_fileset_attached->hash_id][$item_to_create['book_id']])) {
                $hash_id = $bible_fileset_attached->hash_id;

                $bible_files_to_perform_filtered = $this->filterBibleFilesByChapter(
                    $bible_files_indexed[$hash_id][$item_to_create['book_id']],
                    $item_to_create
                );
                $duration = 0;

                if ($bible_fileset_attached->set_type_code === 'audio_stream' ||
                    $bible_fileset_attached->set_type_code === 'audio_drama_stream'
                ) {
                    foreach ($bible_files_to_perform_filtered as $bible_file) {
                        $currentBandwidth = $bible_file->streamBandwidth->first();
                        $transportStream = $this->getTransportStreamFromBibleFile($currentBandwidth, $item_to_create);

                        foreach ($transportStream as $stream) {
                            $duration += $stream->runtime;
                        }
                    }
                } else {
                    foreach ($bible_files_to_perform_filtered as $bible_file) {
                        $duration += $bible_file->duration ?? 180;
                    }
                }

                $item_to_create['duration'] = $duration;
            }
        }

        unset($item_to_create);
    }

    /**
     * Update the verses property for each playlist item
     * @param Array &$playlist_items_to_create
     * @param Collection $bible_filesets
     *
     * @return void
     */
    public function calculateVersesForPlaylistItems(Array &$playlist_items_to_create, Collection $bible_filesets) : void
    {
        $book_ids = [];
        foreach ($playlist_items_to_create as $item_to_create) {
            $book_ids[$item_to_create['book_id']] = true;
        }

        $bible_verses = BibleVerse::select([ \DB::raw('COUNT(id) as verse_count'), 'book_id', 'chapter'])
            ->whereIn('hash_id', $bible_filesets->pluck('hash_id'))
            ->whereIn('book_id', array_keys($book_ids))
            ->groupBy(['book_id', 'chapter'])
            ->get();

        $bible_verses_indexed = [];

        foreach ($bible_verses as $bible_verse) {
            $bible_verses_indexed[$bible_verse->book_id][(int)$bible_verse->chapter] = $bible_verse->verse_count;
        }

        foreach ($playlist_items_to_create as &$item_to_create) {
            if (isset($bible_verses_indexed[$item_to_create['book_id']])) {
                $verses = 0;
                foreach ($bible_verses_indexed[$item_to_create['book_id']] as $chapter => $verse_count) {
                    if ((int) $chapter >= $item_to_create['chapter_start'] &&
                        (int) $chapter <= $item_to_create['chapter_end']
                    ) {
                        $verses = $verses + (int) $verse_count;
                    }
                }
                $item_to_create['verses'] = $verses;
            }
        }

        unset($item_to_create);
    }

    /**
     * Filter the collection of transport stream by verse_start and verse_end properties
     *
     * @param Array $item
     * @param Collection $transportStream
     * @param BibleFile $bible_file
     */
    public function processVersesOnTransportStream(Array $item, Collection $transportStream, BibleFile $bible_file)
    {
        if ($item['chapter_end'] === $item['chapter_start']) {
            $transportStream = $transportStream->splice(1, $item['verse_end'])->all();
            return collect($transportStream)->slice($item['verse_start'] - 1)->all();
        }

        $transportStream = $transportStream->splice(1)->all();
        if ($bible_file->chapter_start === $item['chapter_start']) {
            return collect($transportStream)->slice($item['verse_start'] - 1)->all();
        }
        if ($bible_file->chapter_start === $item['chapter_end']) {
            return collect($transportStream)->splice(0, $item['verse_end'])->all();
        }

        return $transportStream;
    }

    /**
     * Get an indexed by playlist ID to match the new playlist IDs with the old playlist IDs
     *
     * @param Collection $days
     * @return Array
     */
    private function gePlaylistIndexedNewAndOld(Collection $new_playlists, Array $old_playlist_ids) : Array
    {
        $new_plist_id_indexed_old_plist_id = [];
        foreach ($new_playlists as $new_palyslist_index => $new_playlist) {
            $new_plist_id_indexed_old_plist_id[$new_playlist->id] = $old_playlist_ids[$new_palyslist_index];
        }
        return $new_plist_id_indexed_old_plist_id;
    }

    /**
     * Get an indexed by playlist ID to know if a day has been completed
     *
     * @param Collection $days
     * @return Array
     */
    private function getPlanDaysCompleteIndexed(Collection $days) : Array
    {
        $plan_days_complete_indexed = [];
        foreach ($days as $day) {
            if ($day->completed === true) {
                $plan_days_complete_indexed[$day->playlist_id] = true;
            }
        }
        return $plan_days_complete_indexed;
    }

    /**
     * Create the complate play day records by user.
     *
     * @param int $user_id
     * @param Collection $plan_days
     * @param Array $old_day_playlist_ids
     * @param Array $plan_days_complete_indexed
     *
     * @return bool
     */
    public function createCompletePlayDays(
        int $user_id,
        Collection $plan_days,
        Array $old_day_playlist_ids,
        Array $plan_days_complete_indexed
    ) : bool {
        $plan_days_complete_to_create = [];
        foreach ($plan_days as $new_day_translate) {
            if (isset($old_day_playlist_ids[$new_day_translate->playlist_id])) {
                $old_day_playlist_id = $old_day_playlist_ids[$new_day_translate->playlist_id];

                if (isset($plan_days_complete_indexed[$old_day_playlist_id])) {
                    $plan_days_complete_to_create[] = [
                        "user_id" => $user_id,
                        "plan_day_id" => $new_day_translate->id
                    ];

                    $new_day_translate->completed = true;
                }
            }
        }

        if (!empty($plan_days_complete_to_create)) {
            return PlanDayComplete::insert($plan_days_complete_to_create);
        }

        return false;
    }

    /**
     * Create the complate playlist item records by user.
     *
     * @param int $user_id
     * @param Collection $items
     * @param Array $old_day_playlist_ids
     * @param Array $plan_days_complete_indexed
     *
     * @return bool
     */
    public function createCompletePlaylistItems(
        int $user_id,
        Collection $items,
        Array $playlist_indexed,
        Array $translation_data,
    ) : bool {
        if (!$user_id) {
            return false;
        }

        $pitems_complete_to_create = [];

        foreach ($items as $playlist_item) {
            $key_translated = $playlist_item->generateUniqueKey();

            if (isset($playlist_indexed[$playlist_item->playlist_id][$key_translated])) {
                $translated_item_indexed = $playlist_indexed[$playlist_item->playlist_id][$key_translated];
                $translated_item_id = $translated_item_indexed['translated_id'];
                $translated_playlist_id = $translated_item_indexed['playlist_id_key'];

                if (isset($translation_data[$translated_playlist_id][$translated_item_id]) &&
                    $translation_data[$translated_playlist_id][$translated_item_id]->completed === true
                ) {
                    $pitems_complete_to_create[] = [
                        "user_id" => $user_id,
                        "playlist_item_id" => $playlist_item->id,
                    ];
                    $playlist_item->completed = true;
                }
            }
        }

        if (!empty($pitems_complete_to_create)) {
            return PlaylistItemsComplete::insert($pitems_complete_to_create);
        }

        return false;
    }

    /**
     * Add the translation_item property to each translation data record if the record has a translation available
     *
     * @param Collection $items - playlist items
     * @param Array $playlist_indexed - array to know if an item has a translation
     * @param Array $translation_data - array of playlist items translated
     *
     * @return void
     */
    public function linkTranslationPlaylistItems(
        Collection $items,
        Array $playlist_indexed,
        Array $translation_data
    ) : void {
        foreach ($items as $playlist_item) {
            $key_translated = $playlist_item->generateUniqueKey();

            if (isset($playlist_indexed[$playlist_item->playlist_id][$key_translated])) {
                $translated_item_indexed = $playlist_indexed[$playlist_item->playlist_id][$key_translated];
                $translated_item_id = $translated_item_indexed['translated_id'];
                $translated_playlist_id = $translated_item_indexed['playlist_id_key'];

                if (isset($translation_data[$translated_playlist_id][$translated_item_id])) {
                    $translation_data[$translated_playlist_id][$translated_item_id]
                        ->translation_item = $playlist_item;
                }
            }
        }
    }

    /**
     * For each play day that belong to a Plan, it will attached the correct playlist for the given Plan Object
     *
     * @param Plan $plan
     * @param int $user_id
     *
     * @return void
     */
    public function setPlaylistItemsForEachPlaylist(Plan $plan, int $user_id) : void
    {
        $new_day_playlist_ids = [];

        foreach ($plan->days as $day) {
            $new_day_playlist_ids[] = $day->playlist_id;
        }

        $playlists = Playlist::findWithFollowersByUserAndIds($user_id, $new_day_playlist_ids);

        foreach ($plan->days as $day) {
            if (isset($playlists[$day->playlist_id])) {
                $day->playlist = $playlists[$day->playlist_id];
            }
        }
    }

    /**
     * For each play list item that belong to a Plan, it will create the verse_text property for the given Plan Object
     *
     * @param Plan $plan
     *
     * @return void
     */
    public function setVerseTextToEachPlaylistItem(Plan $plan) : void
    {
        foreach ($plan->days as $day) {
            foreach ($day->playlist->items as $item) {
                $item->verse_text = $item->getVerseText();
            }
        }
    }

    /**
     * Transform into an array a plan translation data
     *
     * @param Array $translation_data
     *
     * @return Array
     */
    private function transformTranslationData(?Array $translation_data) : Array
    {
        $new_translation_data = [];

        if ($translation_data) {
            foreach ($translation_data as $translation_data_playlist) {
                $new_translation_data_item = [];
                foreach ($translation_data_playlist as $translation_data_item) {
                    $new_translation_data_item[] = $translation_data_item;
                }
                $new_translation_data[] = $new_translation_data_item;
            }
        }

        return $new_translation_data;
    }

    /**
     * Get all data related with the translated playlist
     *
     * @param Playlist $playlist
     * @param int $user_id
     * @param int $plan_id
     * @param Bible $bible
     * @param Collection $audio_fileset_types
     * @param Collection $bible_audio_filesets
     *
     * @return Plan
     */
    private function translatePlaylist(
        $playlist,
        $user_id,
        $plan_id,
        $bible,
        $audio_fileset_types,
        $bible_audio_filesets
    ) : Array {
        $translated_items = [];
        $metadata_items = [];
        $total_translated_items = 0;
        
        if (isset($playlist->items)) {
            foreach ($playlist->items as $item) {
                if (isset($item->fileset, $item->fileset->set_type_code)) {
                    $item->fileset->addMetaRecordsAsAttributes();
                    $ordered_types = $audio_fileset_types->filter(function ($type) use ($item) {
                        return $type !== $item->fileset->set_type_code;
                    })->prepend($item->fileset->set_type_code);
                    $preferred_fileset = $ordered_types->map(
                        function ($type) use ($bible_audio_filesets, $item) {
                            return $this->playlist_service->getFilesetFromValidFilesets(
                                $bible_audio_filesets,
                                $type,
                                $item->fileset->set_size_code
                            );
                        }
                    )->firstWhere('id');
                    $has_translation = isset($preferred_fileset);
                    $is_streaming = true;

                    if ($has_translation) {
                        $item->fileset_id = $preferred_fileset->id;
                        $is_streaming = $preferred_fileset->set_type_code === 'audio_stream'
                            || $preferred_fileset->set_type_code === 'audio_drama_stream';
                        $translated_items[$item->id] = [
                            'translated_id' => $item->id,
                            'fileset_id' => $item->fileset_id,
                            'book_id' => $item->book_id,
                            'chapter_start' => $item->chapter_start,
                            'chapter_end' => $item->chapter_end,
                            'verse_start' => $is_streaming ? $item->verse_start : null,
                            'verse_end' => $is_streaming ? $item->verse_end : null,
                            'order_column' => $item->order_column,
                            'duration' => $item->duration,
                            'verses' => $item->verses,
                        ];
                        $total_translated_items += 1;
                    }
                }
                $metadata_items[$item->id] = $item;
            }

            $translated_percentage = sizeof($playlist->items) ? $total_translated_items / sizeof($playlist->items) : 0;
        }

        return [
            "playlist_data" =>
                [
                    'user_id'           => $user_id,
                    'name'              =>
                        $playlist->name . ': ' . $bible->language->name . ' ' . substr($bible->id, -3),
                    'external_content'  => $playlist->external_content,
                    'featured'          => false,
                    'draft'             => true,
                    'plan_id'           => $plan_id
                ],
            "translation_data" => $metadata_items,
            "translated_items" => $translated_items,
            "translated_percentage" => $translated_percentage
        ];
    }

    /**
     * Get a plan by ID with the Days and User relations
     *
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Plan
     */
    public function getPlanWithDaysByIdAndUser(int $plan_id, int $user_id = null) : Plan
    {
        return Plan::getWithDaysAndUserById($plan_id, $user_id);
    }

    /**
     * Get a plan by ID
     *
     * @param int $plan_id
     *
     * @return Plan
     */
    public function getPlanById(int $plan_id) : ?Plan
    {
        return Plan::findOne($plan_id);
    }

    /**
     * Calculate the duration and verses values for each playlist item that belong to plan
     *
     * @param int $plan_id
     *
     * @return Plan
     */
    public function calculateDurationAndVersesUpdatePlan(Plan $plan, bool $save = false) : Plan
    {
        $playlist_ids = [];
        foreach ($plan->days as $day) {
            $playlist_ids[] = $day->playlist_id;
        }

        $playlist_items = PlaylistItems::whereIn('playlist_id', $playlist_ids)->get();
        $this->playlist_service->calculateDuration($playlist_items);
        $this->playlist_service->calculateVerses($playlist_items);

        if ($save === true) {
            foreach ($playlist_items as $playlist_item) {
                $playlist_item->save();
            }
        }

        return $plan;
    }
}
