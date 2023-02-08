<?php

namespace App\Services\IAMAPI;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;

class IAMAPIClientService implements IAMAPIClientInterface
{
    protected string $api_url;
    protected string $is_enabled;
    protected CurlHttpClient $client;

    public function __construct(string $api_url, bool $is_enabled = false, int $service_timeout = 5)
    {
        $this->client = HttpClient::create();
        $this->api_url = $api_url;
        $this->is_enabled = $is_enabled;
        $this->service_timeout = $service_timeout;
    }

    public function isEnabled() : bool
    {
        return $this->is_enabled === true || (int) $this->is_enabled === 1;
    }

    private function createPath(
        string $base_url,
        string $path,
        string $user_key
    ) : string {
        $new_path = $base_url.$path.'?userKey='.$user_key;

        return $new_path;
    }

    public function getAccessGroupIdsByUserKey(string $user_key) : mixed
    {
        try {
            $new_path = $this->createPath($this->api_url, 'access', $user_key);
            $content = $this->client->request(
                'GET',
                $new_path,
                ['timeout' => $this->service_timeout]
            );

            \Log::channel('single')->notice(['GET request', $new_path]);

            $response = $content->getContent();

            return collect(json_decode($response));
        } catch (TransportException $e) {
            \Log::channel('errorlog')->error($e->getMessage());
            throw $e;
        }
    }

    public function getName() : string
    {
        return "IAMAPIClient";
    }
}
