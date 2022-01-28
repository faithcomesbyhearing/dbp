<?php

namespace App\Services\Plans;

use App\Models\Plan\Plan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\Bible\Bible;

class PlaylistService
{
    public function translate(int $playlist_id, Bible $bible, int $user_id = 0, $plan_id = 0)
    {
        $playlist = Playlist::findWithBibleRelationByUserAndId($user_id, $playlist_id);

        $audio_fileset_types = collect(['audio_stream', 'audio_drama_stream', 'audio', 'audio_drama']);
        $bible_audio_filesets = $bible->filesets->whereIn('set_type_code', $audio_fileset_types);

        $translated_items = [];
        $metadata_items = [];
        $total_translated_items = 0;
        if (isset($playlist->items)) {
            foreach ($playlist->items as $item) {
                if (isset($item->fileset, $item->fileset->set_type_code)) {
                    $item->fileset = formatFilesetMeta($item->fileset);
                    $ordered_types = $audio_fileset_types->filter(function ($type) use ($item) {
                        return $type !== $item->fileset->set_type_code;
                    })->prepend($item->fileset->set_type_code);

                    $preferred_fileset = $ordered_types->map(function ($type) use ($bible_audio_filesets, $item) {
                        return $this->getFileset($bible_audio_filesets, $type, $item->fileset->set_size_code);
                    })->firstWhere('id');
                    $has_translation = isset($preferred_fileset);
                    $is_streaming = true;

                    if ($has_translation) {
                        $item->fileset_id = $preferred_fileset->id;
                        $is_streaming = $preferred_fileset->set_type_code === 'audio_stream' || $preferred_fileset->set_type_code === 'audio_drama_stream';
                        $translated_items[] = [
                            'translated_id' => $item->id,
                            'fileset_id' => $item->fileset_id,
                            'book_id' => $item->book_id,
                            'chapter_start' => $item->chapter_start,
                            'chapter_end' => $item->chapter_end,
                            'verse_start' => $is_streaming ? $item->verse_start : null,
                            'verse_end' => $is_streaming ? $item->verse_end : null,
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
                'verses'            => $playlist_item['verses'] ?? 0,
                'order_column'      => $order
            ];
            $playlist_items_to_create[] = $playlist_item_data;
            $order += 1;
        }

        PlaylistItems::insert($playlist_items_to_create);
        $new_items = PlaylistItems::findByIdsWithFilesetRelation([$playlist->id], 'order_column');

        $created_playlist_items = [];

        foreach ($new_items as $key => $new_playlist_item) {
            $new_playlist_item->translated_id = $playlist_items[$key]['translated_id'];
            $created_playlist_items[$new_playlist_item->translated_id] = $new_playlist_item;
        }

        return $created_playlist_items;
    }

    public function getFileset($filesets, $type, $size)
    {
        $available_filesets = [];

        // This code avoids using filesets that have audio, but are not usable for translations i.e opus
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
        $valid_filesets = collect($valid_filesets);

        $complete_fileset = $valid_filesets->where('set_type_code', $type)->where('set_size_code', 'C')->first();
        if ($complete_fileset) {
            $available_filesets[] = $complete_fileset;
        }

        $size_filesets = $valid_filesets->where('set_type_code', $type)->where('set_size_code', $size)->first();
        if ($size_filesets) {
            $available_filesets[] = $size_filesets;
        }

        $size__partial_filesets = $valid_filesets->filter(function ($item) use ($type, $size) {
            $valid_item = isset($item->set_type_code) && isset($item->set_size_code);
            return (
                $valid_item &&
                is_string($size) &&
                $item->set_type_code === $type &&
                strpos($item->set_size_code, $size . 'P') !== false
            );
        })->first();
        if ($size__partial_filesets) {
            $available_filesets[] = $size__partial_filesets;
        }

        $partial_fileset = $valid_filesets->where('set_type_code', $type)->where('set_size_code', 'P')->first();
        if ($partial_fileset) {
            $available_filesets[] = $partial_fileset;
        }

        if (!empty($available_filesets)) {
            $available_filesets =
                collect($available_filesets)->sortBy(function ($item) {
                    return strpos($item->id, '16');
                });
            
            return $available_filesets->first();
        }

        return false;
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
}
