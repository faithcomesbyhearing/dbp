<?php

namespace App\Traits;

use App\Models\Organization\Asset;
use Aws\CloudFront\CloudFrontClient;
use Carbon\Carbon;

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
            \Log::channel('cloudfront_api_key')->notice($key . ' ' . $signature);
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
}
