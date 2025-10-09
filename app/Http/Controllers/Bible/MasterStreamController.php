<?php

namespace App\Http\Controllers\Bible;

use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Http\Controllers\APIController;
use App\Models\Bible\BibleFileset;
use App\Traits\CallsBucketsTrait;
use App\Services\Biblebrain\BiblebrainService;

class MasterStreamController extends APIController
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
     * Generate the parent m3u8 file which contains the various resolution m3u8 files
     *
     * @param null $id
     * @param null $file_id
     *
     * @return $this
     */
    public function index(string $fileset_id, $file_id_location = null)
    {
        $fileset = BibleFileset::select('bible_filesets.id')->find($fileset_id);
        if (!$fileset) {
            return $this
                ->setStatusCode(HttpResponse::HTTP_NOT_FOUND)
                ->replyWithError('Fileset not found');
        }

        $access_group_ids = getAccessGroups();
        $fileset = BibleFileset::select('bible_filesets.id')
            ->IsContentAvailable($access_group_ids)
            ->find($fileset_id);

        if (!$fileset) {
            return $this
                ->setStatusCode(HttpResponse::HTTP_UNAUTHORIZED)
                ->replyWithError('Not authorized to access this fileset');
        }

        // Parse the file_id_location to extract book, chapter, verse_start, and verse_end
        $parts = explode('-', $file_id_location);

        if (count($parts) < 2) {
            return $this
                ->setStatusCode(HttpResponse::HTTP_BAD_REQUEST)
                ->replyWithError('Invalid file location format');
        }

        $book_id = $parts[0];
        $chapter = (int) $parts[1];
        $verse_start = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : null;
        $verse_end = isset($parts[3]) && $parts[3] !== '' ? (int) $parts[3] : null;

        $cache_params = $this->removeSpaceFromCacheParameters(
            [$fileset_id, $book_id, $chapter, $verse_start, $verse_end]
        );

        return cacheRemember(
            'stream_master_index',
            $cache_params,
            now()->addHours(12),
            function () use ($fileset_id, $book_id, $chapter, $verse_start, $verse_end) {
                // Call the new biblebrain service method
                return $this->biblebrainMasterPlaylist(
                    $fileset_id,
                    $book_id,
                    $chapter,
                    $verse_start,
                    $verse_end,
                );
            }
        );
    }
}
