<?php

namespace App\Traits;

use App\Models\Organization\Asset;
use Aws\CloudFront\CloudFrontClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait CallsBucketsTrait
{
    public function authorizeAWS($source)
    {
        if ($source === 'cloudfront') {
            return new CloudFrontClient([
                'version' => 'latest',
                'region'  => 'us-west-2',
            ]);
        }

        throw new \UnexpectedValueException("The $source source is not supported.");
    }

    /**
     * Get a CloudFrontClient object for a given asset ID
     *
     * @param string $asset_id
     *
     * @return CloudFrontClient client
     */
    public function getCloudFrontClientFromAssetId(string $asset_id) : ?CloudFrontClient
    {
        $asset = Asset::where('id', $asset_id)->first();

        return !empty($asset) ? $this->authorizeAWS($asset->asset_type) : null;
    }

    /**
     * Method to sign a URL but the CloudFrontClient object should be passed by parameter
     * to do an only one call to AWS
     *
     * @param CloudFrontClient $client
     * @param string $file_path
     * @param int $transaction
     *
     * @return string signed url
     */
    public function signedUrlUsingClient(CloudFrontClient $client, string $file_path, int $transaction): string
    {
        if (!$client) {
            return null;
        }

        $cdn_server_url = config('services.cdn.server');
        $request_array = [
            'url'         => 'https://'. $cdn_server_url . '/' . $file_path . '?x-amz-transaction=' . $transaction,
            'key_pair_id' => config('filesystems.disks.cloudfront.key'),
            'private_key' => storage_path('app/' . config('filesystems.disks.cloudfront.secret')),
            'expires'     => Carbon::now()->addDay()->timestamp,
        ];

        $signed_url = $client->getSignedUrl($request_array);
        $query_parameters_string = parse_url($signed_url, PHP_URL_QUERY);
        $query_parameters = [];
        parse_str($query_parameters_string, $query_parameters);
        $key = $this->getKey();

        if (!empty($key)) {
            $signature = isset($query_parameters['Signature']) ? $query_parameters['Signature'] : $signed_url;
            Log::channel('cloudfront_api_key')->notice($key . ' ' . $signature);
        }

        return $signed_url;
    }

    public function signedUrl(string $file_path, $asset_id, int $transaction)
    {
        $asset = cacheRemember('asset_signed_url', [$asset_id], now()->addMinute(), function () use ($asset_id) {
            return Asset::where('id', $asset_id)->first();
        });
        $client = $this->authorizeAWS($asset->asset_type);

        return $this->signedUrlUsingClient($client, $file_path, $transaction);
    }

    /**
     * abstract method to get the API key
     *
     */
    abstract protected function getKey();


    /**
     * Handle Biblebrain response and return formatted response
     *
     * @param ResponseInterface $response
     * @param string $errorContext
     * @param string $errorMessage
     *
     * @return \Illuminate\Http\Response
     */
    private function handleBiblebrainResponse(
        ResponseInterface $response,
        string $errorContext,
        string $errorMessage
    ) {
        if (!$this->biblebrainService->isSuccessful($response)) {
            Log::channel('errorlog')->error(
                "{$errorContext} - " . $this->biblebrainService->getErrorMessage($response)
            );

            $statusCode = $this->biblebrainService->getStatusCode($response);
            return $this
                ->setStatusCode($statusCode)
                ->replyWithError($errorMessage);
        }

        $content = $this->biblebrainService->getContent($response);
        $headers = $this->biblebrainService->getHeaders($response);

        return response($content, HttpResponse::HTTP_OK, [
            'Content-Type' => $headers['content-type'][0] ?? 'application/x-mpegURL',
            'Content-Disposition' => $headers['content-disposition'][0] ?? 'attachment'
        ]);
    }

    /**
     * Get master playlist using Biblebrain Services
     * Replaces the old v4_media_stream endpoint
     *
     * @param string $fileset_id
     * @param string $book_id
     * @param int $chapter
     * @param int|null $verse_start
     * @param int|null $verse_end
     *
     * @return \Illuminate\Http\Response
     */
    public function biblebrainMasterPlaylist(
        string $fileset_id,
        string $book_id,
        int $chapter,
        ?int $verse_start = null,
        ?int $verse_end = null
    ) {
        try {
            $response = $this->biblebrainService->getMasterPlaylist(
                $fileset_id,
                $book_id,
                $chapter,
                $verse_start,
                $verse_end,
            );

            return $this->handleBiblebrainResponse(
                $response,
                'BiblebrainMasterPlaylist',
                'Failed to retrieve playlist from Biblebrain Services'
            );
        } catch (\Exception $e) {
            Log::channel('errorlog')->error("BiblebrainMasterPlaylist - Error: {$e->getMessage()}");

            return $this
                ->setStatusCode(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)
                ->replyWithError('An error occurred while retrieving the playlist');
        }
    }

    /**
     * Get media playlist using Biblebrain Services
     * Replaces the old v4_media_stream_ts endpoint
     *
     * @param string $fileset_id
     * @param string $book_id
     * @param int $chapter
     * @param int $verse_start
     * @param int $verse_end
     * @param string $file_name
     *
     * @return \Illuminate\Http\Response
     */
    public function biblebrainMediaPlaylist(
        ?string $fileset_id,
        string $book_id,
        int $chapter,
        int $verse_start,
        int $verse_end,
        ?string $file_name
    ) {
        try {
            // Convert 0 to null for optional verse parameters
            $verseStart = $verse_start !== 0 ? $verse_start : null;
            $verseEnd = $verse_end !== 0 ? $verse_end : null;

            $response = $this->biblebrainService->getMediaPlaylist(
                $fileset_id,
                $book_id,
                $chapter,
                $file_name,
                $verseStart,
                $verseEnd
            );

            return $this->handleBiblebrainResponse(
                $response,
                'BiblebrainMediaPlaylist',
                'Failed to retrieve media playlist from Biblebrain Services'
            );
        } catch (\Exception $e) {
            Log::channel('errorlog')->error("BiblebrainMediaPlaylist - Error: {$e->getMessage()}");

            return $this
                ->setStatusCode(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)
                ->replyWithError('An error occurred while retrieving the media playlist');
        }
    }
}
