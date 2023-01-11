<?php

namespace App\Transformers;

use OpenApi\Attributes as OA;

class AudioTransformer extends BaseTransformer
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($audio)
    {
        switch ($this->version) {
            case 2:
            case 3:
                return $this->transformForV2($audio);
            case 4:
            default:
                return $this->transformForV4($audio);
        }
    }

    /**
     * @OA\Schema (
     *   type="array",
     *   schema="v2_audio_timestamps",
     *   description="The v2_audio_timestamps response",
     *   title="v2_audio_timestamps",
     *   @OA\Xml(name="v2_audio_timestamps"),
     *   @OA\Items(
     *              @OA\Property(property="verse_start", ref="#/components/schemas/BibleFile/properties/verse_start"),
     *              @OA\Property(property="timestamp", ref="#/components/schemas/BibleFileTimestamp/properties/timestamp")
     *   )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v2_audio_path",
     *   description="The audio_path",
     *   title="v2_audio_path",
     *   @OA\Xml(name="v2_audio_path"),
     *   @OA\Items(
     *              @OA\Property(property="book_id",       ref="#/components/schemas/Book/properties/id_osis"),
     *              @OA\Property(property="chapter_id",    ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *              @OA\Property(property="path",          type="string")
     *   )
     * )
     */
    public function transformForV2($audio)
    {
        switch ($this->route) {
            /**
             * schema="v2_audio_timestamps"
             */
            case 'v2_audio_timestamps':
                return [
                    'verse_id'    => (int) $audio->verse_sequence,
                    'verse_id_alt' => $audio->verse_start,
                    'verse_start' => $audio->timestamp,
                ];

            /**
             * schema="v2_audio_path"
             */
            case 'v2_audio_path':
                return [
                    'book_id'    => $audio->book ? $audio->book->id_osis : $audio->book_id,
                    'chapter_id' => (string) $audio->chapter_start,
                    'path'       => preg_replace("/https:\/\/.*?\/.*?\//", '', $audio->file_name)
                ];
            default:
                return [];
        }
    }

    /**
     * @OA\Schema (
     *   type="object",
     *   schema="v4_audio_timestamps",
     *   description="The v4_audio_timestamps response",
     *   title="v4_audio_timestamps",
     *   @OA\Xml(name="v4_audio_timestamps"),
     *   @OA\Property(
     *      property="data",
     *      type="array",
     *      @OA\Items(
     *              @OA\Property(property="book", ref="#/components/schemas/BibleFile/properties/book_id"),
     *              @OA\Property(property="chapter", ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *              @OA\Property(property="verse_start", ref="#/components/schemas/BibleFile/properties/verse_start"),
     *              @OA\Property(property="timestamp", ref="#/components/schemas/BibleFileTimestamp/properties/timestamp")
     *      )
     *   )
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_timestamps_tag",
     *   description="The v4 timestamps tag",
     *   title="v4_timestamps_tag",
     *   @OA\Xml(name="v4_timestamps_tag"),
     *   @OA\Property(
     *      property="data",
     *      type="array",
     *      @OA\Items(
     *       @OA\Property(property="book_id",       ref="#/components/schemas/Book/properties/id"),
     *       @OA\Property(property="book_name",     ref="#/components/schemas/Book/properties/name"),
     *       @OA\Property(property="chapter_start", ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *       @OA\Property(property="chapter_end",   ref="#/components/schemas/BibleFile/properties/chapter_end"),
     *       @OA\Property(property="verse_start",   ref="#/components/schemas/BibleFile/properties/verse_start"),
     *       @OA\Property(property="verse_end",     ref="#/components/schemas/BibleFile/properties/verse_end"),
     *       @OA\Property(property="timestamp",     ref="#/components/schemas/BibleFileTimestamp/properties/timestamp"),
     *       @OA\Property(property="path",          ref="#/components/schemas/BibleFile/properties/file_name")
     *      )
     *   )
     * )
     */
    public function transformForV4($audio)
    {
        switch ($this->route) {
            /**
             * schema="v4_audio_timestamps",
             */
            case 'v4_timestamps.verse':
                return [
                    'book'           => (string) $audio->bibleFile->book_id,
                    'chapter'        => (string) $audio->bibleFile->chapter_start,
                    'verse_start'    => (int) $audio->verse_sequence,
                    'verse_start_alt'=> (string) $audio->verse_start,
                    'timestamp'      => $audio->timestamp
                ];
            case 'v4_internal_bible.chapter':
                return [
                    'verse_start'    => (string) $audio->verse_start,
                    'timestamp'      => $audio->timestamp
                ];
            /**
             * schema="v4_timestamps_tag",
             */
            default:
                return [
                    'book_id'       => $audio->book_id,
                    'book_name'     => $audio->book->currentTranslation->name ?? $audio->book->name,
                    'chapter_start' => (string)$audio->chapter_start,
                    'chapter_end'   => (string)$audio->chapter_end,
                    'verse_start'   => (string)$audio->verse_start,
                    'verse_start'   => (int)$audio->verse_sequence,
                    'verse_start_alt' => (int)$audio->verse_start,
                    'verse_end'     => (string)$audio->verse_end,
                    'timestamp'     => $audio->timestamps,
                    'path'          => $audio->file_name
                ];
        }
    }
}
