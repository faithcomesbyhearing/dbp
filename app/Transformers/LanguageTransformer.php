<?php

namespace App\Transformers;

use App\Models\Language\Language;
use Illuminate\Support\Arr;

class LanguageTransformer extends BaseTransformer
{
    public function transform(Language $language)
    {
        switch ((int) $this->version) {
            case 2:
            case 3:
                return $this->transformForV2($language);
            case 4:
            default:
                return $this->transformForV4($language);
        }
    }

    /**
     * @param Language $language
     *
     * @return array
     */
    public function transformForV2(Language $language)
    {
        switch ($this->route) {
            case 'v2_library_volumeLanguage':
                return [
                    'language_name'             => $language->autonym ?? '',
                    'english_name'              => $language->name ?? '',
                    'language_code'             => strtoupper($language->iso),
                    'language_iso'              => $language->iso,
                    'language_iso_2B'           => $language->iso, // $language->iso2B,
                    'language_iso_2T'           => $language->iso, // $language->iso2T,
                    'language_iso_1'            => '', //$language->iso1,
                    'language_iso_name'         => $language->name,
                    'language_family_code'      => $language->parent ? $language->parent->autonym : strtoupper($language->iso),
                    'language_family_name'      => ($language->parent ? $language->parent->autonym : $language->autonym) ?? '',
                    'language_family_english'   => ($language->parent ? $language->parent->name : $language->name) ?? '',
                    'language_family_iso'       => $language->iso ?? '',
                    'language_family_iso_2B'    => ($language->parent ? $language->parent->iso2B : $language->iso2B) ?? '',
                    'language_family_iso_2T'    => ($language->parent ? $language->parent->iso2T : $language->iso2T) ?? '',
                    'language_family_iso_1'     => ($language->parent ? $language->parent->iso1 : $language->iso1) ?? '',
                    'media'                     => ['text'],
                    'delivery'                  => ['mobile','web','subsplash'],
                    'resolution'                => []
                ];

            default:
                return [
                    'language_code'        => strtoupper($language->iso),
                    'language_name'        => $language->autonym,
                    'english_name'         => $language->name,
                    'language_iso'         => $language->iso,
                    'language_iso_2B'      => $language->iso2B,
                    'language_iso_2T'      => $language->iso2T,
                    'language_iso_1'       => $language->iso1,
                    'language_iso_name'    => $language->name,
                    'language_family_code' => $language->iso,
                ];
        }
    }

    /**
     * @param Language $language
     *
     * @return array
     */
    public function transformForV4(Language $language)
    {
        /**
         * @OA\Response(
         *   response="v4_languages.one",
         *   description="The Full alphabet return for the single alphabet route",
         *   @OA\MediaType(
         *     mediaType="application/json",
         *     @OA\Schema(ref="#/components/schemas/Language")
         *   )
         * )
         */
        switch ($this->route) {
            case 'v4_languages.one':
                return [
                    'id'                   => $language->id,
                    'name'                 => $language->name,
                    'description'          => optional($language->translations->where('iso_translation', $this->i10n)->first())->description,
                    'autonym'              => $language->autonym ? $language->autonym->name : '',
                    'glotto_id'            => $language->glotto_id,
                    'iso'                  => $language->iso,
                    'maps'                 => $language->maps,
                    'area'                 => $language->area,
                    'population'           => $language->population,
                    'country_id'           => $language->country_id,
                    'country_name'         => $language->primaryCountry->name ?? '',
                    'codes'                => $language->codes->pluck('code', 'source') ?? '',
                    'alternativeNames'     => array_unique(Arr::flatten($language->translations->pluck('name')->ToArray())) ?? '',
                    'dialects'             => $language->dialects->pluck('name') ?? '',
                    'classifications'      => $language->classifications->pluck('name', 'classification_id') ?? '',
                    'bibles'               => $language->bibles,
                    'resources'            => $language->resources
                ];

            /**
             * @OA\Response(
             *   response="v4_languages.all",
             *   description="The minimized language return for the single language route",
             *   @OA\MediaType(
             *     mediaType="application/json",
             *     @OA\Schema(ref="#/components/schemas/Language")
             *   )
             * )
             */
            default:
            case 'v4_languages.all':
                $output = [
                    'id'         => $language->id,
                    'glotto_id'  => $language->glotto_id,
                    'iso'        => $language->iso,
                    'name'       => $language->name ?? $language->backup_name,
                    'autonym'    => $language->autonym,
                    'bibles'     => $language->bibles_count,
                    'filesets'   => $language->filesets_count,
                ];

                if ($language->country_population) {
                    $output['country_population'] = $language->country_population;
                }
                
                if ($language->relationLoaded('translations')) {
                    $output['translations'] = $language->translations->pluck('name', 'language_translation_id');
                }
                return $output;
        }
    }
}
