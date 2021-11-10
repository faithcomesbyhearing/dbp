<?php

namespace App\Traits;

use App\Models\Organization\Asset;
use Aws\CloudFront\CloudFrontClient;
use Carbon\Carbon;
use Curl\Curl;

trait CallsBucketsTrait
{
    public function authorizeAWS($source)
    {
        $security_token = cacheRemember('iam_assumed_role', [], now()->addMinute(), function () {
            $role_call  = $this->assumeRole();
            if ($role_call) {
                $response_xml   = simplexml_load_string($role_call->response, 'SimpleXMLElement', LIBXML_NOCDATA);
                return json_decode(json_encode($response_xml));
            }
        });

        if (!optional($security_token)->AssumeRoleResult) {
            cacheForget('iam_assumed_role');
            throw new \Exception('Iam role denied', 424);
        }

        if ($source === 'cloudfront') {
            return new CloudFrontClient([
                'version' => 'latest',
                'region'  => 'us-west-2',
                'credentials' => [
                    'key' => $security_token->AssumeRoleResult->Credentials->AccessKeyId,
                    'secret' => $security_token->AssumeRoleResult->Credentials->SecretAccessKey,
                    'token' =>  $security_token->AssumeRoleResult->Credentials->SessionToken
                ]
            ]);
        } else {
            return $security_token;
        }
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

    private function assumeRole()
    {
        // Initialize timestamps
        $date        = date('Ymd');
        $timestamp   = str_replace([':', '-'], '', Carbon::now()->toIso8601ZuluString());

        $form_params = [
            'Action'          => 'AssumeRole',
            'Version'         => '2011-06-15',
            'RoleArn'         => config('filesystems.disks.s3.arn'),
            'DurationSeconds' => 43200,
            'RoleSessionName' => config('app.server_name') . $timestamp,
        ];
        $credentials  = $this->generateCreds('/', $timestamp, $form_params);

        $client = new Curl();
        $client->setHeader('Content-Type', 'application/x-www-form-urlencoded; charset=utf-8');
        $client->setHeader('X-Amz-Date', $timestamp);
        $client->setHeader('Authorization', 'AWS4-HMAC-SHA256 Credential=' . config('filesystems.disks.s3.key') . "/$date/us-east-1/sts/aws4_request, SignedHeaders=content-type;host;x-amz-date, Signature=$credentials");
        $client->setHeader('Accept', '');
        $client->setHeader('Accept-Encoding', 'identity');
        $response = $client->post('https://sts.amazonaws.com/', $form_params);

        return $response;
    }

    /*
     * Generate the signature for the assumeRole function
     *
     */
    private function generateCreds($canonical_uri, $current_time, $request_params)
    {
        $region = 'us-east-1';
        $algorithm = 'AWS4-HMAC-SHA256';
        $service = 'sts';

        $scope = date('Ymd') . "/$region/$service/aws4_request";

        $request_body = '';
        foreach ($request_params as $request_key => $request_param) {
            if ($request_key == 'RoleArn') {
                $request_param = urlencode($request_param);
            }
            $request_body .= $request_key . '=' . $request_param . '&';
        }
        $request_body = rtrim($request_body, '&');
        $encrypt_body = hash('sha256', $request_body);

        $request        = "POST\n$canonical_uri\n\ncontent-type:application/x-www-form-urlencoded; charset=utf-8\nhost:sts.amazonaws.com\nx-amz-date:" . $current_time . "\n\ncontent-type;host;x-amz-date\n$encrypt_body";
        $string_to_sign = "$algorithm\n$current_time\n$scope\n" . hash('sha256', $request);
        $signature      = $this->encryptValues($string_to_sign, 'sts');
        return $signature;
    }

    private function encryptValues($string_to_sign, $service, $region = 'us-east-1')
    {
        $layer_1   = hash_hmac('sha256', date('Ymd'), 'AWS4' . config('filesystems.disks.s3.secret'), true);
        $layer_2   = hash_hmac('sha256', $region, $layer_1, true);
        $layer_3   = hash_hmac('sha256', $service, $layer_2, true);
        $layer_4   = hash_hmac('sha256', 'aws4_request', $layer_3, true);
        $signature = hash_hmac('sha256', $string_to_sign, $layer_4);

        return $signature;
    }

    /**
     * abstract method to get the API key
     *
     */
    abstract protected function getKey();
}
