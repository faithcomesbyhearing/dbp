<?php

namespace App\Transformers;

class FileSetTransformer extends BaseTransformer
{

    /**
     * A Fractal transformer.
     *
     * @param $audio
     *
     * @return array
     */
    public function transform($audio)
    {
        switch ((int) $this->version) {
            case 2:
            case 3:
                return $this->transformForV2($audio);
            case 4:
            default:
                return $this->transformForV4($audio);
        }
    }

    public function transformForV2($audio)
    {
        switch ($this->route) {
            case 'v2_audio_timestamps':
                return [
                    'verse_id'    => (string) $audio->verse_start,
                    'verse_start' => $audio->timestamp
                ];

            default:
            case 'v2_audio_path':
                return [
                    'book_id'    => ucfirst(strtolower($audio->book->id_osis)),
                    'chapter_id' => (string) $audio->chapter_start,
                    'path'       => $audio->file_name
                ];
        }
    }

    /**
     * @OA\Schema (
     *  type="object",
     *  schema="v4_bible_filesets.show",
     *  description="The minimized alphabet return for the all alphabets route",
     *  title="v4_bible_filesets.show",
     *  @OA\Xml(name="v4_bible_filesets.show"),
     *  @OA\Property(
     *   property="data",
     *   type="array",
     *    @OA\Items(
     *          @OA\Property(property="book_id",        ref="#/components/schemas/BibleFile/properties/book_id"),
     *          @OA\Property(property="book_name",      ref="#/components/schemas/BookTranslation/properties/name"),
     *          @OA\Property(property="chapter_start",  ref="#/components/schemas/BibleFile/properties/chapter_start"),
     *          @OA\Property(property="chapter_end",    ref="#/components/schemas/BibleFile/properties/chapter_end"),
     *          @OA\Property(property="verse_start",    ref="#/components/schemas/BibleFile/properties/verse_start"),
     *          @OA\Property(property="verse_start_alt", ref="#/components/schemas/BibleFile/properties/verse_start"),
     *          @OA\Property(property="verse_end",      ref="#/components/schemas/BibleFile/properties/verse_end"),
     *          @OA\Property(property="verse_end_alt",  ref="#/components/schemas/BibleFile/properties/verse_end"),
     *          @OA\Property(property="thumbnail",      type="string", description="The image url", maxLength=191),
     *          @OA\Property(property="timestamp",      ref="#/components/schemas/BibleFileTimestamp/properties/timestamp"),
     *          @OA\Property(property="path",           ref="#/components/schemas/BibleFile/properties/file_name"),
     *          @OA\Property(property="duration",       ref="#/components/schemas/BibleFile/properties/duration"),
     *     )
     *    )
     *   )
     * )
     */
    public function transformForV4($fileset)
    {
        switch ($this->route) {
            case 'v4_filesets.bulk':
                // is_video is used while secondary files for video is loaded and addressed
                $is_video = $fileset->thumbnail && strpos($fileset->thumbnail, 'video') !== false;
                $schema = [
                    'book_id'       => $fileset->book_id,
                    'book_name'     => $fileset->book_name,
                    'chapter_start' => $fileset->chapter_start,
                    'chapter_end'   => $fileset->chapter_end,
                    'verse_start'   => $fileset->verse_sequence,
                    'verse_start_alt'=> $fileset->verse_start,
                    'verse_end'     => $fileset->verse_end ? (int) $fileset->verse_end : null,
                    'verse_end_alt' => $fileset->verse_end,
                    'timestamp'     => $fileset->timestamp,
                    'path'          => $fileset->file_name,
                    'duration'      => $fileset->duration
                ];
                if ($is_video) {
                    $schema['thumbnail'] = $fileset->thumbnail;
                }
                if ($fileset->multiple_mp3) {
                    $schema['multiple_mp3'] = true;
                }

                return $schema;

            default:
                /**
                 * schema=v4_bible_filesets.show
                 */
                $schema = [
                    'book_id'           => $fileset->book_id,
                    'book_name'         => $fileset->book_name,
                    'chapter_start'     => $fileset->chapter_start,
                    'chapter_end'       => $fileset->chapter_end,
                    'verse_start'       => $fileset->verse_sequence,
                    'verse_start_alt'   => $fileset->verse_start,
                    'verse_end'         => $fileset->verse_end ? (int) $fileset->verse_end : null,
                    'verse_end_alt'     => $fileset->verse_end,
                    'timestamp'         => $fileset->timestamp,
                    'path'              => $fileset->file_name,
                    'duration'          => $fileset->duration,
                    'thumbnail'         => $fileset->thumbnail,
                    'filesize_in_bytes' => $fileset->file_size,
                    'youtube_url'       => $fileset->bible_tag_value
                        ? getYoutubePlaylistURL($fileset->bible_tag_value, $fileset->bible_fileset_tag_value)
                        : null,
                ];

                if ($fileset->multiple_mp3) {
                    $schema['multiple_mp3'] = true;
                }

                return $schema;
        }
    }
}
