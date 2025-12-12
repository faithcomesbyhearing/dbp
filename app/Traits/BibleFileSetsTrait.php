<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use Aws\CloudFront\CloudFrontClient;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileSecondary;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\BibleFileTag;
use App\Models\Bible\BibleBook;
use App\Models\Organization\Asset;
use App\Models\Bible\BibleFileset;
use App\Transformers\FileSetTransformer;
use App\Transformers\TextTransformer;
use DB;

trait BibleFileSetsTrait
{
    /**
     * Build common query for audio/video filesets
     */
    private function buildAudioVideoFilesetQuery(
        $fileset,
        ?string $book_id = null,
        $chapter_id = null
    ) {
        $bible = optional($fileset->bible)->first();

        $query = BibleFile::byHashIdJoinBooks(
            $fileset->hash_id,
            $bible->id,
            $bible->versification,
            $chapter_id,
            $book_id
        // We need to ensure we pull the correct files. The video_stream fileset can have multiple associated files,
        // but it specifically requires the m3u8 format. Meanwhile, the audio fileset works differently because m3u8
        // Bible files are not stored in the database, so the audio fileset needs mp3 Bible files.
        )->filterBySetTypeCode(
            $fileset->set_type_code
        )->orderBySetTypeCode(
            $fileset->set_type_code
        );

        if ($fileset->set_type_code === BibleFileset::TYPE_VIDEO_STREAM) {
            $query->prioritizeNewVideoFormat();
        }

        return $query;
    }

    /**
     * Execute query with optional pagination
     */
    private function executeQuery($query, $limit)
    {
        if ($limit !== null) {
            $fileset_chapters_paginated = $query->paginate($limit);
            $filesets_pagination = new IlluminatePaginatorAdapter($fileset_chapters_paginated);
            $fileset_chapters = $fileset_chapters_paginated->getCollection();
        } else {
            $fileset_chapters = $query->get();
            $filesets_pagination = null;
        }

        if ($fileset_chapters->count() === 0) {
            return [
                'error' => $this->setStatusCode(HttpResponse::HTTP_NOT_FOUND)->replyWithError(
                    'No Fileset Chapters Found for the provided params'
                )
            ];
        }

        return [
            'fileset_chapters' => $fileset_chapters,
            'filesets_pagination' => $filesets_pagination
        ];
    }

    /**
     * Prepare AWS client for signing URLs
     */
    private function prepareClient($fileset)
    {
        $asset = Asset::where('id', $fileset->asset_id)->first();
        $client = null;

        if ($asset) {
            $client = $this->authorizeAWS($asset->asset_type);
        }

        if (!$client) {
            return [
                'error' => $this->setStatusCode(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)->replyWithError(
                    'No AWS Client available for signing URLs'
                )
            ];
        }

        return ['client' => $client];
    }

    /**
     * Common processing for audio/video filesets
     */
    private function processAudioVideoFilesets(
        $fileset,
        ?string $book_id = null,
        $chapter_id = null,
        bool $is_download = false,
        ?int $limit = null
    ) {
        $query = $this->buildAudioVideoFilesetQuery($fileset, $book_id, $chapter_id);

        $queryResult = $this->executeQuery($query, $limit);
        if (isset($queryResult['error'])) {
            return $queryResult['error'];
        }

        $clientResult = $this->prepareClient($fileset);
        if (isset($clientResult['error'])) {
            return $clientResult['error'];
        }

        $fileset_chapters = $queryResult['fileset_chapters'];
        $filesets_pagination = $queryResult['filesets_pagination'];
        $client = $clientResult['client'];

        $bible = optional($fileset->bible)->first();

        $fileset_chapters = $this->generateSecondaryFiles(
            $fileset,
            $fileset_chapters,
            $bible->id,
            $client
        );

        if ($is_download) {
            $fileset_chapters_processed = $this->generateFilesetChaptersToDownload(
                $fileset,
                $fileset_chapters,
                $bible->id,
                $client
            );
        } else {
            $fileset_chapters_processed = $this->generateFilesetChapters(
                $fileset,
                $fileset_chapters,
                $bible->id,
                $client
            );
        }

        $fileset_return = fractal(
            $fileset_chapters_processed,
            new FileSetTransformer(),
            $this->serializer
        );

        if (isset($fileset_chapters->metadata)) {
            $fileset_return->addMeta($fileset_chapters->metadata);
        }

        return $limit !== null ?
            $fileset_return->paginateWith($filesets_pagination) :
            $fileset_return;
    }

    private function getAudioVideoFilesetsToDownload(
        $fileset,
        ?string $book_id = null,
        $chapter_id = null,
        ?int $limit = null
    ) {
        return $this->processAudioVideoFilesets($fileset, $book_id, $chapter_id, true, $limit);
    }

    private function showAudioVideoFilesets(
        $fileset,
        ?string $book_id = null,
        $chapter_id = null,
        $limit = null
    ) {
        return $this->processAudioVideoFilesets($fileset, $book_id, $chapter_id, false, $limit);
    }

    private function showTextFilesetChapter(
        $limit,
        $fileset,
        ?string $book_id = null,
        ?int $chapter_id = null,
        ?string $verse_start = null,
        ?string $verse_end = null
    ) {
        $bible = optional($fileset->bible)->first();

        $select_columns = [
            'bible_verses.book_id as book_id',
            'books.name as book_name',
            BibleBook::getBookOrderSelectColumnExpressionRaw($bible->versification, 'book_order'),
            'bible_books.name as book_vernacular_name',
            'bible_verses.chapter',
            'bible_verses.verse_start',
            'bible_verses.verse_end',
            'bible_verses.verse_sequence',
            'bible_verses.verse_text',
        ];
        $text_query = BibleVerse::withVernacularMetaData($bible)
        ->where('hash_id', $fileset->hash_id)
        ->when($book_id, function ($query) use ($book_id) {
            return $query->where('bible_verses.book_id', $book_id);
        })
        ->when(!is_null($chapter_id), function ($query) use ($chapter_id) {
            return $query->where('chapter', (int) $chapter_id);
        })
        ->when($verse_start, function ($query) use ($verse_start) {
            return $query->where('verse_sequence', '>=', (int) $verse_start);
        })
        ->when($verse_end, function ($query) use ($verse_end) {
            return $query->where('verse_sequence', '<=', (int) $verse_end);
        })
        ->orderBy('verse_sequence')
        ->orderBy('books.name', 'ASC')
        ->orderBy('bible_verses.chapter');

        if ($bible && $bible->numeral_system_id) {
            $select_columns_extra = array_merge(
                $select_columns,
                [
                    'glyph_chapter.glyph as chapter_vernacular',
                    'glyph_start.glyph as verse_start_vernacular',
                    'glyph_end.glyph as verse_end_vernacular',
                ]
            );
            $text_query->select($select_columns_extra);
        } else {
            $text_query->select($select_columns);
        }

        if ($limit !== null) {
            $fileset_chapters = $text_query->paginate($limit);
            $filesets_pagination = new IlluminatePaginatorAdapter($fileset_chapters);
        } else {
            $fileset_chapters = $text_query->get();
        }

        if ($fileset_chapters->count() === 0) {
            return $this->setStatusCode(404)->replyWithError(
                'No Fileset Chapters Found for the provided params'
            );
        }

        $fileset_return = fractal(
            $fileset_chapters,
            new TextTransformer(),
            $this->serializer
        );

        return $limit !== null ?
            $fileset_return->paginateWith($filesets_pagination) :
            $fileset_return
        ;
    }

    private function generateSecondaryFiles(
        $fileset,
        $fileset_chapters,
        $bible_id,
        $client
    ) {
        $secondary_files = BibleFileSecondary::where(
            'hash_id',
            $fileset->hash_id
        )
        // this MIN is used to only pick one file name for each type
        // TODO: discuss and apply  a different way of selecting secondary files (specially for thumbnails)
        ->select(\DB::raw('MIN(file_name) as file_name,  file_type'))
        ->groupBy('file_type')->get();

        $secondary_file_paths = ['thumbnail' => null, 'zip_file' => null,];
        foreach ($secondary_files as $secondary_file) {
            $secondary_file_url = $this->signedUrlUsingClient(
                $client,
                storagePath($bible_id, $fileset, null, $secondary_file->file_name),
                random_int(0, 10000000)
            );
            if ($secondary_file->file_type === 'art') {
                $secondary_file_paths['thumbnail'] = $secondary_file_url;
            } elseif ($secondary_file->file_type === 'zip') {
                $secondary_file_paths['zip_file'] = $secondary_file_url;
            }
        }

        if ($fileset_chapters->count() === 1) {
            $fileset_chapters[0]->thumbnail = $secondary_file_paths['thumbnail'];
            $fileset_chapters[0]->zip_file = $secondary_file_paths['zip_file'];
        } else {
            $fileset_chapters->metadata = $secondary_file_paths;
        }
        return $fileset_chapters;
    }

    /**
     * Check if fileset is a streaming type
     */
    private function isStreamingFileset($fileset): bool
    {
        return $fileset->set_type_code === BibleFileset::TYPE_VIDEO_STREAM ||
            $fileset->set_type_code === BibleFileset::TYPE_AUDIO_STREAM ||
            $fileset->set_type_code === BibleFileset::TYPE_AUDIO_DRAMA_STREAM;
    }

    /**
     * Handle non-stream fileset chapters by signing URLs
     */
    private function processNonStreamFilesetChapters(
        $fileset_chapters,
        $bible_id,
        $fileset,
        $client
    ) {
        foreach ($fileset_chapters as $key => $fileset_chapter) {
            $fileset_chapters[$key]->file_name = $this->signedUrlUsingClient(
                $client,
                storagePath(
                    $bible_id,
                    $fileset,
                    $fileset_chapter
                ),
                random_int(0, 10000000)
            );
        }
        return $fileset_chapters;
    }

    /**
     * Base method for generating fileset chapters
     *
     * @param      $fileset
     * @param      $fileset_chapters
     * @param      $bible_id
     * @param      $client
     * @param      bool $handle_multi_mp3 Whether to handle multiple MP3 chapters
     *
     * @throws \Exception
     * @return array
     */
    private function generateFilesetChaptersBase(
        $fileset,
        $fileset_chapters,
        $bible_id,
        $client,
        bool $handle_multi_mp3 = false
    ) {
        if ($this->isStreamingFileset($fileset)) {
            foreach ($fileset_chapters as $key => $fileset_chapter) {
                $routeParameters = [
                    'fileset_id' => $fileset->id,
                    'book_id' => $fileset_chapter->book_id,
                    'chapter' => $fileset_chapter->chapter_start,
                    'verse_start' => $fileset_chapter->verse_sequence,
                    'verse_end' => $fileset_chapter->verse_end ? (int) $fileset_chapter->verse_end : null
                ];
                $fileset_chapters[$key]->file_name = route('v4_media_stream', array_filter(
                    $routeParameters,
                    function ($filesetProperty) {
                        return !is_null($filesetProperty) && $filesetProperty !== '';
                    }
                ));
            }
        } else {
            // Check for multiple MP3 files per chapter
            if ($handle_multi_mp3) {
                $hasMultiMp3Chapter = $fileset->isAudio() &&
                    sizeof($fileset_chapters) > 1 &&
                    $this->hasMultipleMp3Chapters($fileset_chapters);

                if ($hasMultiMp3Chapter) {
                    if ($fileset_chapters[0]->chapter_start) {
                        $fileset_chapters[0]->file_name = route(
                            'v4_media_stream',
                            [
                                'fileset_id' => $fileset->id,
                                'book_id' => $fileset_chapters[0]->book_id,
                                'chapter' => $fileset_chapters[0]->chapter_start,
                            ]
                        );
                    } else {
                        $fileset_chapters[0]->file_name = sprintf(
                            '%s/bible/filesets/%s/%s-%s-%s-%s/playlist.m3u8',
                            config('app.api_url'),
                            $fileset->id,
                            $fileset_chapters[0]->book_id,
                            $fileset_chapters[0]->chapter_start,
                            '',
                            ''
                        );
                    }
                    if (!empty($fileset_chapters) > 0 && $fileset_chapters->last() instanceof \App\Models\Bible\BibleFile) {
                        $collection = $fileset_chapters;
                    } else {
                        $collection = collect($fileset_chapters);
                    }
                    $fileset_chapters[0]->duration = $collection->sum('duration');
                    $fileset_chapters[0]->verse_end = optional($collection->last())->verse_end;
                    $fileset_chapters[0]->multiple_mp3 = true;
                    $fileset_chapters = [$fileset_chapters[0]];
                } else {
                    $fileset_chapters = $this->processNonStreamFilesetChapters(
                        $fileset_chapters,
                        $bible_id,
                        $fileset,
                        $client
                    );
                }
            } else {
                $fileset_chapters = $this->processNonStreamFilesetChapters(
                    $fileset_chapters,
                    $bible_id,
                    $fileset,
                    $client
                );
            }
        }

        if ($fileset->isVideo()) {
            $this->setThumbnailForEachFilesetChapter($fileset_chapters, $client);
        }

        return $fileset_chapters;
    }

    /**
     * @param      $fileset
     * @param      $fileset_chapters
     * @param      $bible_id
     * @param      $client
     *
     * @throws \Exception
     * @return array
     */
    private function generateFilesetChapters(
        $fileset,
        $fileset_chapters,
        $bible_id,
        $client
    ) {
        return $this->generateFilesetChaptersBase($fileset, $fileset_chapters, $bible_id, $client, true);
    }

    private function generateFilesetChaptersToDownload(
        $fileset,
        $fileset_chapters,
        $bible_id,
        $client
    ) {
        return $this->generateFilesetChaptersBase($fileset, $fileset_chapters, $bible_id, $client, false);
    }

    /**
     * Update each Fileset and it adds the thumbnail property according values stored into Bible File Tags
     *
     * @param Collection $fileset_chapters
     * @param CloudFrontClient $client
     *
     * @return void
     */
    private function setThumbnailForEachFilesetChapter(Collection $fileset_chapters, CloudFrontClient $client): void
    {
        $file_tags_indexed = $this->getBibleFileTagsFromFilesetChapters($fileset_chapters);

        foreach ($fileset_chapters as $fileset_chapter) {
            $thumbnail_url = 'video/thumbnails/';

            if (isset(
                $file_tags_indexed[$fileset_chapter->hash_id]
                [$fileset_chapter->book_id]
                [$fileset_chapter->chapter_start]
                [$fileset_chapter->verse_start]
            )) {
                $thumbnail_url .= $file_tags_indexed[$fileset_chapter->hash_id][$fileset_chapter->book_id]
                    [$fileset_chapter->chapter_start][$fileset_chapter->verse_start];
            } else {
                $thumbnail_url .= $fileset_chapter->book_id .
                    '_' .
                    str_pad(
                        $fileset_chapter->chapter_start,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ) .
                    '.jpg';
            }

            $fileset_chapter->thumbnail = $this->signedUrlUsingClient(
                $client,
                $thumbnail_url,
                random_int(0, 10000000)
            );
        }
    }

    /**
     * Return an array indexed by hash_id, book, chapter and verse related for each fileset
     * and a thumbnail file if it exists.
     *
     * @param Collection $fileset_chapters
     *
     * @return Array
     */
    private function getBibleFileTagsFromFilesetChapters(Collection $fileset_chapters): Array
    {
        $hash_ids = [];
        $chapters = [];
        $verses = [];

        foreach ($fileset_chapters as $fileset_chapter) {
            $hash_ids[$fileset_chapter->hash_id] = true;
            $chapters[$fileset_chapter->chapter_start] = true;
            $verses[$fileset_chapter->verse_start] = true;
        }

        $file_tags = BibleFileTag::getThumbnailsByHashChapterAnVerse(
            array_keys($hash_ids),
            array_keys($chapters),
            array_keys($verses)
        );

        $file_tags_indexed = [];
        foreach ($file_tags as $tag) {
            if ($tag->hash_id &&
                $tag->chapter_start &&
                $tag->verse_start &&
                $tag->book_id
            ) {
                $file_tags_indexed[$tag->hash_id][$tag->book_id][$tag->chapter_start][$tag->verse_start] = $tag->value;
            }
        }

        return $file_tags_indexed;
    }

    private function hasMultipleMp3Chapters($fileset_chapters)
    {
        foreach ($fileset_chapters as $chapter) {
            if ($chapter['chapter_start'] !== $fileset_chapters[0]['chapter_start']) {
                return false;
            }
        }
        return true;
    }
}
