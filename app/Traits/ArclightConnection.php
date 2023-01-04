<?php

namespace App\Traits;

use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Services\Arclight\ArclightService;

use Exception;

trait ArclightConnection
{
    private function fetchArclight($path, $language_id = null, $include_refs = false, $parameters = '')
    {
        $new_path = ArclightService::createPath($path, $language_id, $include_refs, $parameters);

        try {
            $ctx = stream_context_create(array('http'=>
                array('timeout' =>  (int) config('services.arclight.service_timeout'))
            ));
            $results = json_decode(file_get_contents($new_path, false, $ctx));
        } catch (Exception $e) {
            \Log::channel('errorlog')->error(["Arclight Connection Error: '{$e->getMessage()}" ]);
            $results = [];
        }

        if (isset($results->_embedded)) {
            return $results->_embedded;
        }

        return $results;
    }

    private function fetchLocal($path)
    {
        return json_decode(file_get_contents(storage_path("/data/jfm/$path")), true);
    }

    public function sync()
    {
        if (!file_exists(storage_path('data/jfm/languages'))) {
            mkdir(storage_path('data/jfm/languages'), 0777, true);
        }
        if (!file_exists(storage_path('data/jfm/feature-films'))) {
            mkdir(storage_path('data/jfm/feature-films'), 0777, true);
        }

        $this->syncLanguages();
        $this->syncTypes();
    }

    private function syncTypes()
    {
        $media_components = $this->fetch('media-components');
        foreach ($media_components->mediaComponents as $component) {
            $output[$component->subType][$component->mediaComponentId] = $component->title;
        }
        file_put_contents(storage_path('/data/jfm/types.json'), json_encode(collect($output), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function syncLanguages()
    {
        $languages = collect($this->fetch('media-languages')->mediaLanguages)->pluck('languageId', 'iso3');
        file_put_contents(storage_path('/data/jfm/languages.json'), json_encode(collect($languages), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function getIdReferences() : array
    {
        return ArclightService::getIdReferences();
    }
}
