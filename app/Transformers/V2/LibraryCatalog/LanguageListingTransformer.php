<?php

namespace App\Transformers\V2\LibraryCatalog;

use App\Models\Language\Language;
use App\Transformers\BaseTransformer;

class LanguageListingTransformer extends BaseTransformer
{
    /**
     * @OA\Schema (
     *    type="array",
     *    schema="v2_library_language",
     *    description="The minimized language return for the all languages v2 route",
     *    title="v2_library_language",
     *  @OA\Xml(name="v2_library_language"),
     *  @OA\Items(
     *          @OA\Property(property="language_code",         ref="#/components/schemas/Language/properties/iso"),
     *          @OA\Property(property="language_name",         ref="#/components/schemas/Language/properties/name"),
     *          @OA\Property(property="english_name",          ref="#/components/schemas/Language/properties/name"),
     *          @OA\Property(property="language_iso",          ref="#/components/schemas/Language/properties/iso"),
     *          @OA\Property(property="language_iso_name",     ref="#/components/schemas/Language/properties/name"),
     *          @OA\Property(property="language_family_code",  ref="#/components/schemas/Language/properties/iso")
     *     )
     *   )
     * )
     *
     * @param Language $language
     * @return array
     */
    private $params = [];

    public function __construct($params = [])
    {
        parent::__construct();
        $this->params = $params;
    }

    public function transform($language)
    {
        $language_v2 = optional($this->params)['language_v2'];
        $code = $language_v2->v2Code ?? strtoupper($language->iso);
        $name = $language_v2->name ?? $language->name;
        $english_name = $language_v2->english_name ?? $language->name;
        
        switch ($this->route) {
            case 'v2_library_volumeLanguage':
                return [
                    'language_name'             => $language->autonym ?? '',
                    'english_name'              => (string) $english_name,
                    'language_code'             => $code,
                    'language_iso'              => (string) $language->iso ?? '',
                    'language_iso_2B'           => (string) $language->iso2B ?? '',
                    'language_iso_2T'           => (string) $language->iso2T ?? '',
                    'language_iso_1'            => (string) $language->iso1 ?? '',
                    'language_iso_name'         => (string) $name ?? '',
                    'language_family_code'      => strtoupper(optional($language->parent)->iso) ?? '',
                    'language_family_name'      => optional($language->parent)->autonym ?? '',
                    'language_family_english'   => optional($language->parent)->name ?? '',
                    'language_family_iso'       => optional($language->parent)->iso ?? '',
                    'language_family_iso_2B'    => optional($language->parent)->iso2B ?? '',
                    'language_family_iso_2T'    => optional($language->parent)->iso2T ?? '',
                    'language_family_iso_1'     => optional($language->parent)->iso1 ?? '',
                    'media'                     => ['text'],
                    'delivery'                  => ['mobile', 'web', 'subsplash'],
                    'resolution'                => []
                ];

            /**
             * @OA\Schema (
             *  type="array",
             *  schema="v2_library_volumeLanguageFamily",
             *  description="",
             *  title="v2_library_volumeLanguageFamily",
             *  @OA\Xml(name="v2_library_volumeLanguageFamily"),
             *  @OA\Items(
             *          @OA\Property(property="language_family_code",      ref="#/components/schemas/Language/properties/iso"),
             *          @OA\Property(property="language_family_name",      ref="#/components/schemas/Language/properties/name"),
             *          @OA\Property(property="language_family_english",   ref="#/components/schemas/Language/properties/name"),
             *          @OA\Property(property="language_family_iso",       ref="#/components/schemas/Language/properties/iso"),
             *          @OA\Property(property="language",                  @OA\Schema(type="array")),
             *          @OA\Property(property="media",                     @OA\Schema(type="array")),
             *          @OA\Property(property="delivery",                  @OA\Schema(type="array")),
             *          @OA\Property(property="resolution",                @OA\Schema(type="array")),
             *     )
             *   )
             * )
             */
            case 'v2_library_volumeLanguageFamily':
                return [
                    'language_family_code'    => $code,
                    'language_family_name'    => (string) $language->autonym,
                    'language_family_english' => (string) $language->name,
                    'language_family_iso'     => (string) $language->iso,
                    'language_family_iso_2B'  => (string) $language->iso2B,
                    'language_family_iso_2T'  => (string) $language->iso2T,
                    'language_family_iso_1'   => (string) $language->iso1,
                    'language'                => [strtoupper($language->iso)],
                    'media'                   => ['video', 'text'],
                    'delivery'                => ['mobile', 'web', 'subsplash'],
                    'resolution'              => ['lo']
                ];

            default:
                return [
                    'language_code'        => strtoupper($language->code),
                    'language_name'        => $language->name_v2 ?? $language->name,
                    'english_name'         => $language->english_name_v2 ?? $language->name,
                    'language_iso'         => (string) $language->iso ?? '',
                    'language_iso_2B'      => $language->iso2B ?? '',
                    'language_iso_2T'      => $language->iso2B ?? '',
                    'language_iso_1'       => $language->iso2B ?? '',
                    'language_iso_name'    => $language->name ?? '',
                    'language_family_code' => (string) $language->iso ?? ''
                ];
        }
    }
}
