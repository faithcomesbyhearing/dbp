<?php

namespace App\Services\Arclight;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ArclightService
{
    protected $client;

    public function __construct()
    {
        $this->client = HttpClient::create();
    }

    /**
     * Do a get request to the arclight API
     *
     * @param string $path
     * @param string $language_id
     * @param bool $include_refs
     * @param string $parameters
     *
     * @return ResponseInterface
     */
    public function doRequest(
        string $path,
        string $language_id = null,
        bool $include_refs = false,
        string $parameters = ''
    ) : ResponseInterface {
        $base_url = config('services.arclight.url');
        $key      = config('services.arclight.key');

        $new_path = $base_url.$path.'?_format=json&apiKey='.$key.'&limit=3000&platform=ios';

        if ($language_id) {
            $new_path .= '&languageIds='.$language_id;
        }

        if ($include_refs) {
            $refs = implode(',', array_keys($this->getIdReferences()));
            $new_path .= '&ids='.$refs;
        }

        $new_path .= '&'.$parameters;

        return $this->client->request(
            'GET',
            $new_path,
            ['timeout' => (int) config('services.arclight.service_timeout')]
        );
    }

    /**
     * Do a get request to the arclight API
     *
     * @param ResponseInterface $response
     */
    public function getContent(ResponseInterface $response)
    {
        $media_component = json_decode($response->getContent());

        if (isset($media_component->_embedded)) {
            return $media_component->_embedded;
        }

        return $media_component;
    }
}
