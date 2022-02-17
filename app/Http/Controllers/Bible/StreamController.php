<?php

namespace App\Http\Controllers\Bible;

use App\Http\Controllers\APIController;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Models\Organization\Asset;
use App\Traits\CallsBucketsTrait;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StreamController extends APIController
{
    use CallsBucketsTrait;

    /**
     *
     * Generate the parent m3u8 file which contains the various resolution m3u8 files
     *
     * @param null $id
     * @param null $file_id
     *
     * @return $this
     */
    public function index($id = null, $file_id_location = null)
    {
        $cache_params = $this->removeSpaceFromCacheParameters(
            [$id, $file_id_location]
        );

        $current_file = cacheRemember('stream_master_index', $cache_params, now()->addHours(12), function () use ($id, $file_id_location) {
            $fileset = BibleFileset::uniqueFileset($id)->select('hash_id', 'id', 'asset_id')->first();
            if (!$fileset) {
                return $this->setStatusCode(404)->replyWithError('No fileset found for the provided params');
            }

            $is_multiple_mp3 = $this->isMultipleMp3($file_id_location);

            if ($is_multiple_mp3) {
                return $this->generateMultipleMp3HLS($id, $file_id_location);
            }

            $file = $this->getFileFromLocation($fileset, $file_id_location);

            if (!$file) {
                return $this->replyWithError(trans('api.bible_file_errors_404', ['id' => $file_id_location]));
            }
            $asset_id = $fileset->asset_id;
            $current_file = '#EXTM3U';
            foreach ($file->streamBandwidth as $bandwidth) {
                $current_file .= "\n#EXT-X-STREAM-INF:PROGRAM-ID=1,BANDWIDTH=$bandwidth->bandwidth";

                $transportStream = sizeof($bandwidth->transportStreamBytes)
                    ? $bandwidth->transportStreamBytes
                    : $bandwidth->transportStreamTS;

                $extra_args = '';
                if (sizeof($transportStream) &&
                    isset($transportStream[0]->timestamp) &&
                    $transportStream[0]->timestamp->verse_start === 0
                ) {
                    $extra_args = '&v0=0';
                }
                if ($bandwidth->resolution_width) {
                    $current_file .= ',RESOLUTION=' . $bandwidth->resolution_width . "x$bandwidth->resolution_height";
                }
                if ($bandwidth->codec) {
                    $current_file .= ",CODECS=\"$bandwidth->codec\"";
                }
                $current_file .= "\n$bandwidth->file_name" . '?key=' . $this->key . '&v=4&asset_id=' . $asset_id . $extra_args;
            }
            return response($current_file, 200, [
                'Content-Disposition' => 'attachment; filename="' . $file->file_name . '"',
                'Content-Type'        => 'application/x-mpegURL'
            ]);
        });

        return $current_file;
    }

    /**
     *
     * Deliver the ts files referenced by file created by the generated m3u8
     *
     * @param null $fileset_id
     * @param null $file_id
     * @param null $file_name
     *
     * @return $this
     * @throws \Exception
     */
    public function transportStream($fileset_id = null, $file_id_location = null, $file_name = null)
    {
        $cache_params = $this->removeSpaceFromCacheParameters(
            [$fileset_id, $file_id_location, $file_name]
        );

        $current_file = cacheRemember('stream_bandwidth', $cache_params, now()->addHours(12), function () use ($fileset_id, $file_id_location, $file_name) {
            $fileset = BibleFileset::uniqueFileset($fileset_id, 'audio', true)
                ->select('hash_id', 'id', 'asset_id')
                ->first();

            $fileset_type = 'audio';

            if (empty($fileset)) {
                $fileset = BibleFileset::uniqueFileset($fileset_id, 'video_stream')
                    ->select('hash_id', 'id', 'asset_id')
                    ->first();

                if (empty($fileset)) {
                    return $this->setStatusCode(404)->replyWithError('No fileset found for the provided params');
                }

                $fileset_type = 'video';
            }

            $file = $this->getFileFromLocation($fileset, $file_id_location);

            if (!$file) {
                return $this->replyWithError(trans('api.bible_file_errors_404', ['id' => $file_id_location]));
            }

            $bible_path    = $fileset->bible->first() !== null ? $fileset->bible->first()->id . '/' : '';

            $currentBandwidth = $file->streamBandwidth->where('file_name', $file_name)->first();
            if (!$currentBandwidth) {
                return $this->setStatusCode(404)->replyWithError(trans('api.file_errors_404_size'));
            }
            $transaction_id = random_int(0, 10000000);

            $currentBandwidth->transportStream = sizeof($currentBandwidth->transportStreamBytes)
                ? $currentBandwidth->transportStreamBytes
                : $currentBandwidth->transportStreamTS;

            $current_file = "#EXTM3U\n";
            $current_file .= '#EXT-X-TARGETDURATION:' . ceil($currentBandwidth->transportStream->sum('runtime')) . "\n";
            $current_file .= "#EXT-X-VERSION:4\n";
            $current_file .= '#EXT-X-MEDIA-SEQUENCE:0';

            $signed_files = [];

            $client = $this->getCloudFrontClientFromAssetId($fileset->asset_id);

            foreach ($currentBandwidth->transportStream as $stream) {
                $current_file .= "\n#EXTINF:$stream->runtime,";
                if (isset($stream->timestamp)) {
                    $current_file .= "\n#EXT-X-BYTERANGE:$stream->bytes@$stream->offset";
                    $fileset = $stream->timestamp->bibleFile->fileset;
                    $stream->file_name = $stream->timestamp->bibleFile->file_name;
                }
                $file_path = $fileset_type . '/' . $bible_path . $fileset->id . '/' . $stream->file_name;
                if (!isset($signed_files[$file_path])) {
                    $signed_files[$file_path] = $this->signedUrlUsingClient($client, $file_path, $transaction_id);
                }
                $current_file .= "\n" . $signed_files[$file_path];
            }
            $current_file .= "\n#EXT-X-ENDLIST";

            return response($current_file, 200, [
                'Content-Disposition' => 'attachment; filename="' . $file->file_name . '"',
                'Content-Type'        => 'application/x-mpegURL'
            ]);
        });

        return $current_file;
    }

    private function getFileFromLocation($fileset, $file_id_location)
    {
        $parts = explode('-', $file_id_location);
        if (sizeof($parts) === 1) {
            return BibleFile::with([
                    'streamBandwidth' => function ($query_stream) {
                        $query_stream->with(['transportStreamTS', 'transportStreamBytes']);
                    }
                ])
                ->where('hash_id', $fileset->hash_id)
                ->where('id', $parts[0])
                ->first();
        }

        $where = [
            'book_id' => $parts[0],
            'chapter_start' => $parts[1],
            'verse_start' => $parts[2]
        ];

        if ($parts[3] !== '') {
            $where['verse_end'] = $parts[3];
        }

        return BibleFile::with([
                'streamBandwidth' => function ($query_stream) {
                    $query_stream->with(['transportStreamTS', 'transportStreamBytes']);
                }
            ])
            ->where('hash_id', $fileset->hash_id)
            ->where($where)
            ->first();
    }

    private function generateMultipleMp3HLS($fileset_id, $file_id_location)
    {
        $parts = explode('-', $file_id_location);

        $audio_fileset = BibleFileset::uniqueFileset($fileset_id, 'audio', true)
            ->select('hash_id', 'id', 'asset_id')
            ->first();

        if (!$audio_fileset) {
            return $this->setStatusCode(404)->replyWithError('No Audio fileset found for the provided params');
        }

        $bible_files = BibleFile::where([
            'hash_id' => $audio_fileset->hash_id,
            'book_id' => $parts[0],
            'chapter_start' => $parts[1]
        ])->get();

        $transaction_id = random_int(0, 10000000);
        $current_file = "#EXTM3U\n";
        $current_file .= '#EXT-X-TARGETDURATION:' . ceil($bible_files->sum('duration')) . "\n";
        $current_file .= "#EXT-X-VERSION:4\n";
        $current_file .= '#EXT-X-MEDIA-SEQUENCE:0';

        $signed_files = [];
        $bible_path =  $audio_fileset->bible->first()->id . '/';
        $client = $this->getCloudFrontClientFromAssetId($audio_fileset->asset_id);

        foreach ($bible_files as $bible_file) {
            $current_file .= "\n#EXTINF:$bible_file->duration,";

            $file_path = 'audio/' . $bible_path . $audio_fileset->id . '/' . $bible_file->file_name;
            if (!isset($signed_files[$file_path])) {
                $signed_files[$file_path] = $this->signedUrlUsingClient($client, $file_path, $transaction_id);
            }
            $current_file .= "\n" . $signed_files[$file_path];
        }

        $current_file .= "\n#EXT-X-ENDLIST";

        return response($current_file, 200, [
            'Content-Disposition' => 'attachment; filename="' . $fileset_id . $file_id_location . '.m3u8"',
            'Content-Type'        => 'application/x-mpegURL'
        ]);
    }

    private function isMultipleMp3($file_id_location)
    {
        $parts = explode('-', $file_id_location);
        if (sizeof($parts) <= 3) {
            return false;
        }

        return !$parts[2];
    }
}
