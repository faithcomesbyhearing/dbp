<?php

namespace App\Services\Plans;

use App\Models\Plan\Plan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\Bible\Bible;
use Illuminate\Database\Eloquent\Collection;
use App\Services\Bibles\BibleFilesetService;
use App\Services\Plans\PlaylistItemService;

class PlaylistService
{
    private $playlist_item_service;

    public function __construct()
    {
        $this->playlist_item_service = new PlaylistItemService();
    }

    public function translate(int $playlist_id, Bible $bible, int $user_id = 0, $plan_id = 0)
    {
        $playlist = Playlist::findWithBibleRelationByUserAndId($user_id, $playlist_id);

        $audio_fileset_types = collect(['audio_stream', 'audio_drama_stream', 'audio', 'audio_drama']);
        $bible_id = $bible->id;
        $access_group_ids = getAccessGroups();
        $bible_audio_filesets = BibleFilesetService::getFilesetsByBibleTypeAndAccessGroup(
            $bible_id,
            $audio_fileset_types,
            $access_group_ids
        );
        $translated_items = [];
        $metadata_items = [];
        $total_translated_items = 0;
        if (isset($playlist->items)) {
            $books_target_bible = $bible->books->keyBy('book_id');

            foreach ($playlist->items as $item) {
                if (isset($item->fileset, $item->fileset->set_type_code)) {
                    $item->fileset->addMetaRecordsAsAttributes();
                    $ordered_types = $audio_fileset_types->filter(function ($type) use ($item) {
                        return $type !== $item->fileset->set_type_code;
                    })->prepend($item->fileset->set_type_code);

                    $preferred_fileset = $ordered_types->map(function ($type) use ($bible_audio_filesets, $item) {
                        return $this->getFileset($bible_audio_filesets, $type, $item->fileset->set_size_code);
                    })->firstWhere('id');
                    $has_translation = isset($preferred_fileset);
                    $is_streaming = true;

                    if ($has_translation &&
                        $books_target_bible->has($item->book_id) &&
                        $preferred_fileset->hasFileRelatedBookAndChapter($item->book_id, $item->chapter_start)
                    ) {
                        $item->fileset_id = $preferred_fileset->id;
                        $is_streaming = $preferred_fileset->set_type_code === 'audio_stream' ||
                            $preferred_fileset->set_type_code === 'audio_drama_stream';
                        $translated_items[] = [
                            'translated_id' => $item->id,
                            'fileset_id' => $item->fileset_id,
                            'book_id' => $item->book_id,
                            'chapter_start' => $item->chapter_start,
                            'chapter_end' => $item->chapter_end,
                            'verse_start' => $is_streaming ? $item->verse_sequence : null,
                            'verse_start_alt' => $is_streaming ? $item->verse_start : null,
                            'verse_end' => $is_streaming ? $item->verse_end : null,
                            'verse_sequence' => $is_streaming ? $item->verse_sequence : null,
                            'verses' => $item->verses,
                        ];
                        $total_translated_items += 1;
                    }
                    $metadata_items[] = $item;
                }
            }
            $translated_percentage = sizeof($playlist->items) ? $total_translated_items / sizeof($playlist->items) : 0;
        }
        $playlist_data = [
            'user_id'           => $user_id,
            'name'              => $playlist->name . ': ' . $bible->language->name . ' ' . substr($bible->id, -3),
            'external_content'  => $playlist->external_content,
            'featured'          => false,
            'draft'             => true,
            'plan_id'           => $plan_id,
            'language_id'       => $bible->language_id
        ];

        $playlist = Playlist::create($playlist_data);
        $items = $this->createTranslatedPlaylistItems($playlist, $translated_items);

        foreach ($metadata_items as $item) {
            if (isset($items[$item->id])) {
                $item->translation_item = $items[$item->id];
            }
        }

        $playlist = Playlist::findWithPlaylistItemsByUserAndId($user_id, $playlist->id);
        $playlist->total_duration = $playlist->items->sum('duration');

        $playlist->translation_data = $metadata_items;
        $playlist->translated_percentage = $translated_percentage * 100;

        return $playlist;
    }

    public function createTranslatedPlaylistItems($playlist, $playlist_items)
    {
        $playlist_items_to_create = [];
        $order = 1;
        foreach ($playlist_items as $playlist_item) {
            $playlist_item_data = [
                'playlist_id'       => $playlist->id,
                'fileset_id'        => $playlist_item['fileset_id'],
                'book_id'           => $playlist_item['book_id'],
                'chapter_start'     => $playlist_item['chapter_start'],
                'chapter_end'       => $playlist_item['chapter_end'],
                'verse_start'       => $playlist_item['verse_start'] ?? null,
                'verse_end'         => $playlist_item['verse_end'] ?? null,
                'verse_sequence'    => $playlist_item['verse_sequence'] ?? null,
                'verses'            => $playlist_item['verses'] ?? 0,
                'order_column'      => $order
            ];
            $playlist_items_to_create[] = $playlist_item_data;
            $order += 1;
        }

        $this->playlist_item_service->calculateDurationForPlaylistItems($playlist_items_to_create);
        PlaylistItems::insert($playlist_items_to_create);
        $new_items = PlaylistItems::findByIdsWithFilesetRelation([$playlist->id], 'order_column');

        $created_playlist_items = [];

        foreach ($new_items as $key => $new_playlist_item) {
            $new_playlist_item->translated_id = $playlist_items[$key]['translated_id'];
            $created_playlist_items[$new_playlist_item->translated_id] = $new_playlist_item;
        }

        return $created_playlist_items;
    }

    /**
     * Filter the filesets by audio and streaming given a colletion of filesets
     *
     * @param Collection $filesets
     *
     * @return \Illuminate\Support\Collection $filesets
     */
    public function getValidAudioStreamFilesets(Collection $filesets) : \Illuminate\Support\Collection
    {
        $valid_filesets = $filesets->filter(function ($fileset) {
            $valid_item = isset($fileset->set_type_code);
            $codec_meta = $this->getCodecMetadata($fileset);
            $is_mp3 = isset($codec_meta['description']) && $codec_meta['description'] === 'mp3';
            $is_audio_stream =
              str_contains($fileset->set_type_code, 'audio') &&
              str_contains($fileset->set_type_code, 'stream');
            $is_audio_fileset = $is_mp3 || $is_audio_stream;
            return ($valid_item && $is_audio_fileset);
        });

        return collect($valid_filesets);
    }

    public function getFileset($filesets, $type, $size)
    {
        $valid_filesets = $this->getValidAudioStreamFilesets($filesets);
        return BibleFilesetService::getFilesetFromValidFilesets($valid_filesets, $type, $size);
    }

    private function getCodecMetadata($fileset)
    {
        if (isset($fileset->meta)) {
            return $fileset->meta->filter(function ($metadata) {
                return $metadata['name'] === 'codec';
            })->first();
        }
        return null;
    }

    /**
     * Calculate the duration value for each playlist item that belong to plan
     *
     * @param int $plan_id
     *
     * @return Plan
     */
    public function calculateDuration(?Collection $playlist_items) : void
    {
        foreach ($playlist_items as $playlist_item) {
            $playlist_item->calculateDuration();
        }
    }

    /**
     * Calculate the verses value for each playlist item that belong to plan
     *
     * @param int $plan_id
     *
     * @return Plan
     */
    public function calculateVerses(?Collection $playlist_items) : void
    {
        foreach ($playlist_items as $playlist_item) {
            $playlist_item->calculateVerses();
        }
    }

    /**
     * Check a given playlist items if each items has a valid filset ID and return a valid playlist items array
     *
     * @param array $playlist_items
     * @return array
     */
    public function getValidPlaylistItems(array $playlist_items) : array
    {
        $playlist_items_filtered = [];
        $fileset_ids = [];

        foreach ($playlist_items as $playlist_item) {
            if (optional($playlist_item)->fileset_id) {
                $fileset_ids[$playlist_item->fileset_id] = $playlist_item->fileset_id;
                $playlist_items_filtered[] = $playlist_item;
            }
        }

        if (empty($playlist_items_filtered)) {
            return [];
        }

        $filesets_validated = BibleFilesetService::getValidFilesets(collect($fileset_ids), ['opus', 'webm']);

        if (empty($filesets_validated)) {
            return [];
        }

        return array_filter($playlist_items_filtered, function ($playlist_item) use ($filesets_validated) {
            return isset($filesets_validated[$playlist_item->fileset_id]);
        });
    }

    /**
     * Create play list items according given playlist ID and the playlist items Data
     *
     * @param int $playlist_id
     * @param Array $playlist_items
     * @return Collection
     */
    public function createPlaylistItems(int $playlist_id, array $playlist_items) : ?Collection
    {
        $playlist_items_to_create = [];
        $order = 1;

        $valid_playlist_items = $this->getValidPlaylistItems($playlist_items);

        foreach ($valid_playlist_items as $playlist_item) {
            $verse_start = $playlist_item->verse_start ?? null;
            $verse_sequence = $playlist_item->verse_sequence ?? null;

            if (!$verse_sequence && $verse_start) {
                $verse_sequence = (int) $verse_start;
            }

            $playlist_items_to_create[] = [
                'playlist_id'       => $playlist_id,
                'fileset_id'        => $playlist_item->fileset_id,
                'book_id'           => $playlist_item->book_id,
                'chapter_start'     => $playlist_item->chapter_start,
                'chapter_end'       => $playlist_item->chapter_end,
                'verse_start'       => $verse_start,
                'verse_end'         => $playlist_item->verse_end ?? null,
                'verse_sequence'    => $verse_sequence,
                'verses'            => $playlist_item->verses ?? 0,
                'order_column'      => $order
            ];
            $order += 1;
        }

        if (empty($playlist_items_to_create)) {
            return null;
        }

        $created_playlist_items = \DB::transaction(function () use ($playlist_items_to_create, $playlist_id) {
            PlaylistItems::insert($playlist_items_to_create);

            return PlaylistItems::getLastItemsByPlaylistId(
                $playlist_id,
                sizeof($playlist_items_to_create)
            );
        });

        $this->calculateDuration($created_playlist_items);
        $this->calculateVerses($created_playlist_items);

        foreach ($created_playlist_items as $playlist_item) {
            $playlist_item->save();
        }

        return $created_playlist_items;
    }

    /*
     * Get the playlist records with the duration field for each playlist given a list of ids.
     * Records will be indexed by playlist_id
     *
     * @param Array $playlist_ids
     * @return Collection
     */
    public function getDurationByIds(Array $playlist_ids) : ?Collection
    {
        return PlaylistItems::select('playlist_id', \DB::raw('SUM(duration) as duration'))
            ->whereIn('playlist_id', $playlist_ids)
            ->groupBy('playlist_id')
            ->get()
            ->keyBy('playlist_id');
    }
}
