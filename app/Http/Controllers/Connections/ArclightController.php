<?php

namespace App\Http\Controllers\Connections;

use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Http\Controllers\APIController;
use App\Models\Language\Language;
use App\Models\Language\LanguageCode;
use App\Transformers\ArclightTransformer;
use Spatie\Fractalistic\ArraySerializer;

use App\Traits\ArclightConnection;

class ArclightController extends APIController
{
    use ArclightConnection;

    /**
     * Fetches and returns
     *
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $dam_id   = checkParam('dam_id');
        $iso      = substr($dam_id, 0, 3);
        $platform = checkParam('platform') ?? 'ios';

        $chapters = cacheRemember('arclight', [$iso], now()->addDay(), function () use ($iso, $platform) {
            $language_id = optional(LanguageCode::whereHas('language', function ($query) use ($iso) {
                $query->where('iso', $iso);
            })->where('source', 'arclight')->select('code')->first())->code;
            if (!$language_id) {
                return $this
                    ->setStatusCode(HttpResponse::HTTP_NOT_FOUND)
                    ->replyWithError(trans('api.languages_errors_404'));
            }

            $components = $this->fetchArclight('media-components/', $language_id, true);

            $components = optional($components)->mediaComponents;

            if (empty($components)) {
                return [];
            }

            foreach ($components as $component) {
                $component->language_id = $language_id;
                $component->verses = $this->getIdReferences()[$component->mediaComponentId];
                $component->file_name = route('v2_api_jesusFilm_stream', [
                    'id'          => $component->mediaComponentId,
                    'language_id' => $language_id,
                    'v'           => $this->v,
                    'key'         => $this->key
                ]);
            }
            return $components;
        });

        return $this->reply(fractal($chapters, new ArclightTransformer(), new ArraySerializer()));
    }

    public function chapter($chapter_id)
    {
        $language_id  = checkParam('language_id', true);

        $cache_params = [$chapter_id, $language_id];
        $stream_file  = cacheRemember(
            'arclight_media_components',
            $cache_params,
            now()->addDay(),
            function () use ($chapter_id, $language_id) {
                $media_components = $this->fetchArclight(
                    'media-components/' . $chapter_id . '/languages/' . $language_id,
                    $language_id,
                    false
                );

                if (empty($media_components) || !isset($media_components->streamingUrls)) {
                    return [];
                }

                return file_get_contents($media_components->streamingUrls->m3u8[0]->url);
            }
        );

        return response($stream_file, HttpResponse::HTTP_OK, [
            'Content-Disposition' => 'attachment',
            'Content-Type'        => 'application/x-mpegURL'
        ]);
    }

    public function volumes($iso = null)
    {
        $iso    = strtolower($iso);
        $dam_id_param = checkParam('dam_id|fcbh_id');
        $cache_params =   [$iso, $dam_id_param];
        return cacheRemember('media-languages', $cache_params, now()->addWeek(), function () use ($iso, $dam_id_param) {
            $languages = collect(
                optional($this->fetchArclight('media-languages'))->mediaLanguages
            )->pluck('languageId', 'iso3')->toArray();
            if ($iso) {
                if (!isset($languages[$iso])) {
                    return [];
                }
                $languages = [$iso => $languages[$iso]];
            }

            $language_names = Language::whereIn('iso', array_keys($languages))->get()->pluck('name', 'iso');

            $jesusFilms = [];

            foreach ($languages as $iso => $arclight_language_id) {
                $dam_id = strtoupper($iso) . 'JFVS2DV';
                if (isset($dam_id_param) && $dam_id_param != $dam_id) {
                    continue;
                }
                if (!isset($language_names[$iso])) {
                    continue;
                }

                $jesusFilms[] = [
                    'dam_id'                  => $dam_id,
                    'fcbh_id'                 => $dam_id,
                    'volume_name'             => '',
                    'status'                  => 'live',
                    'dbp_agreement'           => 'true',
                    'expiration'              => '0000-00-00',
                    'language_code'           => strtoupper($iso),
                    'language_name'           => $language_names[$iso],
                    'language_english'        => $language_names[$iso],
                    'language_iso'            => $iso,
                    'language_iso_2B'         => '',
                    'language_iso_2T'         => '',
                    'language_iso_1'          => '',
                    'language_iso_name'       => $language_names[$iso],
                    'language_family_code'    => strtoupper($iso),
                    'language_family_name'    => $language_names[$iso],
                    'language_family_english' => $language_names[$iso],
                    'language_family_iso'     => $iso,
                    'language_family_iso_2B'  => '',
                    'language_family_iso_2T'  => '',
                    'language_family_iso_1'   => '',
                    'version_code'            => 'JFV',
                    'version_name'            => 'Jesus Film Video',
                    'version_english'         => 'Jesus Film Video',
                    'collection_code'         => 'AL',
                    'rich'                    => '0',
                    'collection_name'         => '',
                    'updated_on'              => now()->toDateTimeString(),
                    'created_on'              => '2010-01-01 01:01:01',
                    'right_to_left'           => 'false',
                    'num_art'                 => '0',
                    'num_sample_audio'        => '0',
                    'sku'                     => '',
                    'audio_zip_path'          => $dam_id . '/' . $dam_id . '.zip',
                    'font'                    => null,
                    'arclight_language_id'    => $arclight_language_id,
                    'media'                   => 'video',
                    'media_type'              => 'Drama',
                ];
            }
            return $jesusFilms;
        });
    }
}
