<?php

namespace App\Http\Controllers\Bible;

use App\Http\Controllers\APINoKeyController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Traits\CallsBucketsTrait;
use App\Services\Biblebrain\BiblebrainService;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleFile;

class SubStreamController extends APINoKeyController
{
    use CallsBucketsTrait;

    protected $biblebrainService;

    public function __construct(BiblebrainService $biblebrainService)
    {
        parent::__construct();
        $this->biblebrainService = $biblebrainService;
    }

    /**
     *
     * Deliver the mp3 or mp4 files referenced by file created by the generated master m3u8
     *
     * @param null $fileset_id
     * @param null $file_id
     * @param null $file_name
     *
     * @return $this
     * @throws \Exception
     */
    public function index(string $fileset_id, $file_id_location = null, $file_name = null)
    {
        $fileset = BibleFileset::select('bible_filesets.id')->find($fileset_id);
        if (!$fileset) {
            return $this
                ->setStatusCode(HttpResponse::HTTP_NOT_FOUND)
                ->replyWithError('Fileset not found');
        }

        // Parse the file_id_location to extract book, chapter, verse_start, and verse_end
        $parts = explode('-', $file_id_location);

        if (count($parts) < 2) {
            // Special case: If only one part is provided, it might be a bible_file_id
            $bible_file_id = $parts[0];
            $bible_file = BibleFile::select('bible_files.book_id', 'bible_files.chapter_start')
                ->where('id', $bible_file_id)
                ->first();

            if (!$bible_file) {
                return $this
                    ->setStatusCode(HttpResponse::HTTP_BAD_REQUEST)
                    ->replyWithError('Invalid file location format');
            }

            $book_id = $bible_file->book_id;
            $chapter = (int) $bible_file->chapter_start;
            $verse_start = 0;
            $verse_end = 0;
        } else {
            if (count($parts) > 4) {
                return $this
                    ->setStatusCode(HttpResponse::HTTP_BAD_REQUEST)
                    ->replyWithError('Invalid file location format');
            }

            $book_id = $parts[0];
            $chapter = (int) $parts[1];
            $verse_start = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : 0;
            $verse_end = isset($parts[3]) && $parts[3] !== '' ? (int) $parts[3] : 0;
        }

        $cache_params = removeSpaceAndCntrlFromCacheParameters(
            [$fileset_id, $book_id, $chapter, $verse_start, $verse_end, $file_name]
        );

        return cacheRemember(
            'stream_bandwidth',
            $cache_params,
            now()->addHours(12),
            function () use ($fileset_id, $book_id, $chapter, $verse_start, $verse_end, $file_name) {
                // Call the new biblebrain service method
                return $this->biblebrainMediaPlaylist(
                    $fileset_id,
                    $book_id,
                    $chapter,
                    $verse_start,
                    $verse_end,
                    $file_name,
                );
            }
        );
    }
}
