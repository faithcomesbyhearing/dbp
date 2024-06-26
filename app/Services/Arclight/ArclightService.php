<?php

namespace App\Services\Arclight;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

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
        $new_path = self::createPath($path, $language_id, $include_refs, $parameters);

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
     * @param bool $throw
     */
    public function getContent(ResponseInterface $response, bool $throw = true)
    {
        $media_component = json_decode($response->getContent($throw));

        if (isset($media_component->_embedded)) {
            return $media_component->_embedded;
        }

        if (!$this->isSuccessful($response)) {
            \Log::channel('errorlog')
            ->error([
                "Arclight - Error URL:{$response->getInfo('url')} Error Code: '{$response->getStatusCode()}"
            ]);
        }

        return $media_component;
    }

    /**
     * Is ResponseInterface successful?
     *
     * @return bool
     */
    public function isSuccessful(ResponseInterface $response) : bool
    {
        return $response->getStatusCode() >= HttpResponse::HTTP_OK &&
            $response->getStatusCode() < HttpResponse::HTTP_MULTIPLE_CHOICES;
    }

    /**
     * @param ResponseInterface|iterable<array-key, ResponseInterface> $responses
     * @param float|null $timeout — The idle timeout before yielding timeout chunks
     *
     * @return \Symfony\Contracts\HttpClient\ResponseStreamInterface
     */
    public function stream(ResponseInterface|iterable $responses, float $timeout = null) : ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public static function createPath(
        string $path,
        string $language_id = null,
        bool $include_refs = false,
        string $parameters = ''
    ) : string {
        $base_url = config('services.arclight.url');
        $key      = config('services.arclight.key');

        $new_path = $base_url.$path.'?_format=json&apiKey='.$key.'&limit=3000&platform=ios';

        if ($language_id) {
            $new_path .= '&languageIds='.$language_id;
        }

        if ($include_refs) {
            $refs = implode(',', array_keys(self::getIdReferences()));
            $new_path .= '&ids='.$refs;
        }

        $new_path .= '&'.$parameters;

        return $new_path;
    }

    public static function getIdReferences() : array
    {
        return [
            '1_jf6101-0-0' => ['Gen' => ['1' => ['1']]],
            '1_jf6102-0-0' => ['Luke' => ['1' => ['26','27','28','29','30','31','32','33','34','35']]],
            '1_jf6103-0-0' => ['Luke' => ['2' => ['21']]],
            '1_jf6104-0-0' => ['Luke' => ['3' => ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','21','22','23']]],
            '1_jf6105-0-0' => ['Luke' => ['4' => ['1','2','3','4','5','6','7','8','9','10','11','12','13']]],
            '1_jf6106-0-0' => ['Luke' => ['4' => ['16','17','18','19','21','22','23','24','28','29','30','31']]],
            '1_jf6107-0-0' => ['Luke' => ['18' => ['10','11','12','13','14']]],
            '1_jf6108-0-0' => ['Luke' => ['5' => ['4','5','6','7','8','9','10','11']]],
            '1_jf6109-0-0' => ['Mark' => ['5' => ['22','23']], 'Luke' => ['8' => ['41','42','49','50','52','53','54','55','56']]],
            '1_jf6110-0-0' => ['Luke' => ['5' => ['27','28']]],
            '1_jf6111-0-0' => ['Luke' => ['6' => ['17','18','20','21','22','23']]],
            '1_jf6112-0-0' => ['Luke' => ['6' => ['24','25','26','27']]],
            '1_jf6113-0-0' => ['Luke' => ['11' => ['27','28']]],
            '1_jf6114-0-0' => ['Luke' => ['7' => ['36','37','38','39','40','41','42','43','44','45','46','47','48','49','50']]],
            '1_jf6115-0-0' => ['Luke' => ['8' => ['1','2','3']]],
            '1_jf6116-0-0' => ['Luke' => ['3' => ['19','20']]],
            '1_jf6117-0-0' => ['Luke' => ['8' => ['4','5','6','7','8']]],
            '1_jf6118-0-0' => ['Luke' => ['8' => ['16','17','18']]],
            '1_jf6119-0-0' => ['Luke' => ['8' => ['22','23','24','25','26']]],
            '1_jf6120-0-0' => ['Luke' => ['8' => ['27','28','29','30','31','32','33','34','35','36','37','38','39']]],
            '1_jf6121-0-0' => ['Luke' => ['9' => ['11','12','13','16','17']]],
            '1_jf6122-0-0' => ['Luke' => ['9' => ['18','19','20','21','22']]],
            '1_jf6123-0-0' => ['Luke' => ['9' => ['28','29','30','31','32','33','34','35','36']]],
            '1_jf6124-0-0' => ['Luke' => ['9' => ['37','38','39','40','41','42','43']]],
            '1_jf6125-0-0' => ['Luke' => ['11' => ['1','2','3','4']]],
            '1_jf6126-0-0' => ['Luke' => [
                '11' => ['9','10','11','12','13'],
                '12' => ['22','23','24','25','26','27','28'],
                '17' => ['5','6']]
            ],
            '1_jf6127-0-0' => ['Luke' => ['17' => ['1','2']]],
            '1_jf6128-0-0' => ['Luke' => ['13' => ['18','19']]],
            '1_jf6129-0-0' => ['Luke' => ['5' => ['30','31','32']]],
            '1_jf6130-0-0' => ['Luke' => ['12' => ['32','33','34']]],
            '1_jf6131-0-0' => ['Luke' => ['10' => ['25','26','27','28','29','30','31','32','33','34','35','36','37']]],
            '1_jf6132-0-0' => ['Luke' => ['18' => ['35','36','37','38','39','40','41','42','43']]],
            '1_jf6133-0-0' => ['Luke' => ['19' => ['1','2','3','4','5','6','7','8','9','10']]],
            '1_jf6134-0-0' => ['Luke' => ['18' => ['31','32','33','34']]],
            '1_jf6135-0-0' => [
                'Mark' => ['11' => ['9']],
                'John' => ['12' => ['13']],
                'Luke' => ['19' => ['28','35','36','37','38','39','40','41']],
                'Matt' => ['21' => ['9']]
            ],
            '1_jf6136-0-0' => ['Luke' => ['19' => ['41','42','43','44','45']]],
            '1_jf6137-0-0' => ['Luke' => ['19' => ['45','46','47','48']]],
            '1_jf6138-0-0' => ['Luke' => ['21' => ['1','2','3','4']]],
            '1_jf6139-0-0' => ['Luke' => ['20' => ['1','2','3','4','5','6','7','8']]],
            '1_jf6140-0-0' => ['Luke' => ['20' => ['9','10','11','12','13','14','15','16','17','18']]],
            '1_jf6141-0-0' => ['Luke' => ['20' => ['21','22','23','24','25','26']]],
            '1_jf6142-0-0' => [
                'John' => ['13' => ['24','25'],'21' => ['20']],
                'Mark' => ['14' => ['19','29']],
                'Luke' => ['22' => ['7','8','15','16','17','18','19','20','21','22','23']],
                'Matt' => ['26' => ['22','23']]
            ],
            '1_jf6143-0-0' => ['Luke' => ['22' => ['26','27','28','29','30','31','32','33','34','35','36','37','38']]],
            '1_jf6144-0-0' => [
                'John' => ['13' => ['27']],
                'Mark' => ['14' => ['10','11','33']],
                'Luke' => ['22' => ['40','41','42','43','44','45','46','47','48','49','50','51','52','53','54']],
                'Matt' => ['26' => ['15','37']]
            ],
            '1_jf6145-0-0' => ['Luke' => ['22' => ['55','56','57','58','59','60','61','62','63','64','65']]],
            '1_jf6146-0-0' => [
                'Luke' => ['22' => ['63','64']],
                'Matt' => ['27' => ['29']]
            ],
            '1_jf6147-0-0' => [
                'Mark' => ['15' => ['3']],
                'Luke' => ['23' => ['1','2','3','4','5','6','7','8','9','10','11','15','16']]
            ],
            '1_jf6148-0-0' => ['Luke' => ['23' => ['8','9','10','11']]],
            '1_jf6149-0-0' => ['Luke' => ['23' => ['15','16','17','18','19']]],
            '1_jf6150-0-0' => ['Luke' => ['23' => ['25','26','27','28','31']]],
            '1_jf6151-0-0' => [
                'Luke' => ['23' => ['33','34','35','36','37','38','39']],
                'Matt' => ['27' => ['41','42']],
                'Isa'  => ['53' => ['12']]
            ],
            '1_jf6152-0-0' => ['John' => ['19' => ['23','24']],'Luke' => ['23' => ['34']]],
            '1_jf6153-0-0' => ['Luke' => ['23' => ['38']]],
            '1_jf6154-0-0' => ['Luke' => ['23' => ['39','40','41','42','43']]],
            '1_jf6155-0-0' => ['Luke' => ['23' => ['44','45','46','47']],'Matt' => ['27' => ['54']]],
            '1_jf6156-0-0' => ['Luke' => ['23' => ['49','50','51','52','53','54','55','56']]],
            '1_jf6157-0-0' => [
                'Mark' => ['16' => ['1','2']],
                'Luke' => [
                    '23' => ['56'],
                    '24' => ['1','2','3','5','6','7']
                ],
                'Matt' => ['28' => ['1']]
            ],
            '1_jf6158-0-0' => ['Mark' => ['16' => ['8','10','11']],'Luke' => ['24' => ['8','9','10','11']]],
            '1_jf6159-0-0' => ['Luke' => ['24' => ['13','14','15','16','30','31','33','34','35']]],
            '1_jf6160-0-0' => ['Matt' => ['28' => ['18','19','20']]],
            '1_jf6161-0-0' => null,
        ];
    }
}
