<?php

namespace App\Services\Plans;

use App\Services\Biblebrain\BiblebrainService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class BiblebrainStreamService
{
    private $biblebrain_service;

    public function __construct()
    {
        $this->biblebrain_service = new BiblebrainService();
    }

    /**
     * Check if Biblebrain service is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->biblebrain_service->isEnabled();
    }
    /**
     * Generate HLS playlist for given items
     *
     * @param array $items
     * @param bool $download
     * @return array
     */
    public function getHlsPlaylist($items, $download)
    {
        $signed_files = [];
        $hls_items = [];
        $durations = [];

        foreach ($items as $item) {
            if (!isset($item->fileset)) {
                continue;
            }

            $fileset = $item->fileset;
            if (!Str::contains($fileset->set_type_code, 'audio')) {
                continue;
            }

            // Process chapters for the item
            for ($chapter = $item->chapter_start; $chapter <= $item->chapter_end; $chapter++) {
                $verse_start = ($chapter === $item->chapter_start) ? $item->verse_start : null;
                $verse_end = ($chapter === $item->chapter_end) ? $item->verse_end : null;

                $response = $this->biblebrain_service->getAutoMediaPlaylist(
                    $fileset->id,
                    $item->book_id,
                    $chapter,
                    $verse_start,
                    $verse_end
                );

                if (!$this->biblebrain_service->isSuccessful($response)) {
                    Log::channel('errorlog')->error(
                        "Exception getting playlist: " . $this->biblebrain_service->getErrorMessage($response)
                        . " Failed to get playlist for fileset: {$fileset->id}, book: {$item->book_id}, chapter: {$chapter}"
                    );
                    throw new \Exception('Failed to get playlist from Biblebrain');
                }

                $playlist_content = $this->biblebrain_service->getContent($response, false);

                // Extract signed files and playlist items
                $result = $this->parsePlaylistContent($playlist_content, $download);
                $signed_files = array_merge($signed_files, $result['signed_files']);
                $hls_items[] = $result['hls_content'];
                $durations[] = $result['duration'];
            }
        }

        $hls_items = join("\n" . '#EXT-X-DISCONTINUITY', $hls_items);
        $current_file = "#EXTM3U\n";
        $current_file .= '#EXT-X-TARGETDURATION:' . ceil(collect($durations)->sum()) . "\n";
        $current_file .= "#EXT-X-VERSION:4\n";
        $current_file .= "#EXT-X-MEDIA-SEQUENCE:0\n";
        $current_file .= $hls_items;
        $current_file .= "\n#EXT-X-ENDLIST";

        return ['signed_files' => $signed_files, 'file_content' => $current_file];
    }

    /**
     * Parse playlist content to extract signed files and HLS content
     *
     * @param string $playlist_content
     * @param bool $download
     * @return array
     */
    private function parsePlaylistContent(string $playlist_content, bool $download): array
    {
        $signed_files = [];
        $hls_lines = [];
        $duration = 0;

        $lines = explode("\n", $playlist_content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and M3U header
            if (empty($line) || $line === '#EXTM3U') {
                continue;
            }

            // Skip these directives as they will be added at the playlist level
            if (strpos($line, '#EXT-X-VERSION') === 0 ||
                strpos($line, '#EXT-X-TARGETDURATION') === 0 ||
                strpos($line, '#EXT-X-MEDIA-SEQUENCE') === 0 ||
                strpos($line, '#EXT-X-ENDLIST') === 0) {
                continue;
            }

            // Extract duration from EXTINF
            if (strpos($line, '#EXTINF') === 0) {
                preg_match('/#EXTINF:([\d.]+)/', $line, $matches);
                if (isset($matches[1])) {
                    $duration += (float) $matches[1];
                }
                $hls_lines[] = $line;
            }
            // Handle URLs (signed files)
            elseif (strpos($line, 'http') === 0) {
                // Extract the file path from the signed URL to use as a key
                $path_parts = parse_url($line, PHP_URL_PATH);
                if ($path_parts) {
                    $signed_files[$path_parts] = $line;
                    $hls_lines[] = $download ? $path_parts : $line;
                }
            }
            // Keep all other directives
            else {
                $hls_lines[] = $line;
            }
        }

        return [
            'signed_files' => $signed_files,
            'hls_content' => implode("\n", $hls_lines),
            'duration' => $duration
        ];
    }
}
