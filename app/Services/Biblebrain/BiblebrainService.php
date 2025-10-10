<?php

namespace App\Services\Biblebrain;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BiblebrainService
{
    protected $client;
    protected $baseUrl;
    protected $timeout;
    protected $is_stream_enabled;

    public function __construct()
    {
        $this->client = HttpClient::create();
        $this->baseUrl = config('services.biblebrain_services.url');
        $this->timeout = (int) config('services.biblebrain_services.service_timeout');
        $this->is_stream_enabled = (bool) config('services.biblebrain_services.enabled', false);
    }

    /**
     * Check if Biblebrain service is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->is_stream_enabled;
    }

    /**
     * Get master playlist (parent M3U8) for a specific fileset, book, and chapter
     *
     * @param string $fileset_id - The Bible fileset identifier (e.g., "ENGESV")
     * @param string $book - Book ID (e.g., "MAT", "GEN", "JHN")
     * @param int $chapter - Chapter number
     * @param int|null $verse_start - Starting verse number (optional)
     * @param int|null $verse_end - Ending verse number (optional)
     * @param string|null $key - API key for authentication (optional)
     *
     * @return ResponseInterface
     */
    public function getMasterPlaylist(
        string $fileset_id,
        string $book,
        int $chapter,
        ?int $verse_start = null,
        ?int $verse_end = null,
    ): ResponseInterface {
        $path = "/api/media/stream/playlist/{$fileset_id}/{$book}/{$chapter}/master.m3u8";

        $queryParams = [];
        if ($verse_start !== null) {
            $queryParams['verse_start'] = $verse_start;
        }
        if ($verse_end !== null) {
            $queryParams['verse_end'] = $verse_end;
        }

        return $this->doRequest($path, $queryParams);
    }

    /**
     * Get media playlist (child M3U8) for a specific bandwidth/resolution
     *
     * @param string $fileset_id - The Bible fileset identifier
     * @param string $book - Book ID
     * @param int $chapter - Chapter number
     * @param string $file_name - Bandwidth-specific filename (e.g., "64kbps.m3u8")
     * @param int|null $verse_start - Starting verse number (optional)
     * @param int|null $verse_end - Ending verse number (optional)
     *
     * @return ResponseInterface
     */
    public function getMediaPlaylist(
        string $fileset_id,
        string $book,
        int $chapter,
        string $file_name,
        ?int $verse_start = null,
        ?int $verse_end = null
    ): ResponseInterface {
        $path = "/api/media/stream/playlist/{$fileset_id}/{$book}/{$chapter}/{$file_name}";

        $queryParams = [];
        if ($verse_start !== null) {
            $queryParams['verse_start'] = $verse_start;
        }
        if ($verse_end !== null) {
            $queryParams['verse_end'] = $verse_end;
        }

        return $this->doRequest($path, $queryParams);
    }

    /**
     * Get auto media playlist (child M3U8 with auto quality selection)
     *
     * @param string $fileset_id - The Bible fileset identifier
     * @param string $book - Book ID
     * @param int $chapter - Chapter number
     * @param int|null $verse_start - Starting verse number (optional)
     * @param int|null $verse_end - Ending verse number (optional)
     *
     * @return ResponseInterface
     */
    public function getAutoMediaPlaylist(
        string $fileset_id,
        string $book,
        int $chapter,
        ?int $verse_start = null,
        ?int $verse_end = null
    ): ResponseInterface {
        $path = "/api/media/stream/playlist-auto/{$fileset_id}/{$book}/{$chapter}";

        $queryParams = [];
        if ($verse_start !== null) {
            $queryParams['verse_start'] = $verse_start;
        }
        if ($verse_end !== null) {
            $queryParams['verse_end'] = $verse_end;
        }

        return $this->doRequest($path, $queryParams);
    }

    /**
     * Do a GET request to the biblebrain services API
     *
     * @param string $path - API endpoint path
     * @param array $queryParams - Query parameters (optional)
     *
     * @return ResponseInterface
     */
    protected function doRequest(string $path, array $queryParams = []): ResponseInterface
    {
        $url = $this->baseUrl . $path;

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $this->client->request(
            'GET',
            $url,
            ['timeout' => $this->timeout]
        );
    }

    /**
     * Get content from the response
     *
     * @param ResponseInterface $response
     * @param bool $throw - Whether to throw exception on error
     *
     * @return string
     */
    public function getContent(ResponseInterface $response, bool $throw = true): string
    {
        if (!$this->isSuccessful($response)) {
            Log::channel('errorlog')->error(
                "BiblebrainService - Error URL: {$response->getInfo('url')} Error Code: {$response->getStatusCode()}"
            );
        }

        return $response->getContent($throw);
    }

    /**
     * Check if the response is successful
     *
     * @param ResponseInterface $response
     *
     * @return bool
     */
    public function isSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= HttpResponse::HTTP_OK &&
            $response->getStatusCode() < HttpResponse::HTTP_MULTIPLE_CHOICES;
    }

    /**
     * Get response headers
     *
     * @param ResponseInterface $response
     *
     * @return array
     */
    public function getHeaders(ResponseInterface $response): array
    {
        return $response->getHeaders();
    }

    /**
     * Get response status code
     *
     * @param ResponseInterface $response
     *
     * @return int
     */
    public function getStatusCode(ResponseInterface $response): int
    {
        return $response->getStatusCode();
    }

    /**
     * Get error message from response
     *
     * @param ResponseInterface $response
     *
     * @return string
     */
    public function getErrorMessage(ResponseInterface $response): string
    {
        $statusCode = $response->getStatusCode();
        $url = $response->getInfo('url');

        try {
            $content = $response->getContent(false);
            $errorDetails = !empty($content) ? " - Response: {$content}" : '';
        } catch (\Exception $e) {
            $errorDetails = '';
        }

        return "BiblebrainService - Error URL: {$url} - Status Code: {$statusCode}{$errorDetails}";
    }
}
