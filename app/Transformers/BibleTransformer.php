<?php

namespace App\Transformers;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleFileset;
use App\Transformers\Traits\OrganizationFilterTrait;

use Illuminate\Support\Arr;

class BibleTransformer extends BaseTransformer
{

    use OrganizationFilterTrait;
    /**
     * A Fractal transformer.
     *
     * @param Bible $bible
     *
     * @return array
     */
    public function transform($bible)
    {
        switch ((int) $this->version) {
            case 2:
                return $this->transformForV2($bible);
            case 3:
                return $this->transformForV2($bible);
            case 4:
                return $this->transformForV4($bible);
            default:
                return $this->transformForV4($bible);
        }
    }

    public function transformForV2($bible)
    {

        // Compute v2 ID
        if (isset($bible->bible)) {
            $iso = $bible->bible->first()->language->iso;
            $v2id = $iso . substr($bible->first()->id, 3, 3);
        } elseif (isset($bible->id)) {
            $iso = $bible->language->iso ?? null;
            $v2id = $iso . substr($bible->id, 3, 3);
        }

        $parent = optional($bible->language->parent);
        
        $name = isset($bible->currentTranslation->name) ? $bible->currentTranslation->name : 'Wycliffe Bible Translators, Inc.';

        return [
            [
              'dam_id'                    => $bible->id,
              'fcbh_id'                   => $bible->id,
              'volume_name'               => optional($bible->currentTranslation)->name ?? '',
              'status'                    => 'live', // for the moment these default to Live
              'dbp_agreement'             => 'true', // for the moment these default to True
              'expiration'                => '0000-00-00',
              'language_code'             => strtoupper($bible->iso) ?? '',
              'language_name'             => optional($bible->language)->autonym ?? optional($bible->language)->name,
              'language_english'          => optional($bible->language)->name ?? '',
              'language_iso'              => $bible->iso ?? '',
              'language_iso_2B'           => optional($bible->language)->iso2B ?? '',
              'language_iso_2T'           => optional($bible->language)->iso2T ?? '',
              'language_iso_1'            => optional($bible->language)->iso1 ?? '',
              'language_iso_name'         => optional($bible->language)->name ?? '',
              'language_family_code'      => strtoupper($parent->iso) ?? strtoupper($bible->iso),
              'language_family_name'      => $parent->autonym ?? $bible->language->name,
              'language_family_english'   => $parent->name ?? $bible->language->name,
              'language_family_iso'       => $iso ?? null,
              'language_family_iso_2B'    => $parent->iso2B ?? $bible->language->iso2B,
              'language_family_iso_2T'    => $parent->iso2T ?? $bible->language->iso2T,
              'language_family_iso_1'     => $parent->iso1 ?? $bible->language->iso1,
              'version_code'              => substr($bible->id, 3) ?? '',
              'version_name'              => $name,
              'version_english'           => optional($bible->currentTranslation)->name,
              'collection_code'           => ($bible->name === 'Old Testament') ? 'OT' : 'NT',
              'rich'                      => '0',
              'collection_name'           => $bible->name,
              'updated_on'                => (string) $bible->updated_at,
              'created_on'                => (string) $bible->created_at,
              'right_to_left'             => optional($bible->alphabet)->direction == 'rtl' ? 'true' : 'false',
              'num_art'                   => '0',
              'num_sample_audio'          => '0',
              'sku'                       => '',
              'audio_zip_path'            => '',
              'font'                      => null,
              'arclight_language_id'      => '',
              'media'                     => (strpos($bible->set_type_code, 'audio') !== false) ? 'Audio' : 'Text',
              'media_type'                => 'Drama',
              'delivery'                  => [
                  'mobile',
                  'web',
                  'local_bundled',
                  'subsplash'
              ],
              'resolution'                => []
            ]
        ];
    }

    /**
     * @OA\Schema (
     *   type="object",
     *   schema="v4_bible.all",
     *   description="The bibles being returned",
     *   title="v4_bible.all",
     *   @OA\Xml(name="v4_bible.all"),
     *   @OA\Property(
     *    property="data",
     *    type="array",
     *    @OA\Items(
     *              @OA\Property(property="abbr",              ref="#/components/schemas/Bible/properties/id"),
     *              @OA\Property(property="name",              ref="#/components/schemas/BibleTranslation/properties/name"),
     *              @OA\Property(property="vname",             ref="#/components/schemas/BibleTranslation/properties/name"),
     *              @OA\Property(property="language",          ref="#/components/schemas/Language/properties/name"),
     *              @OA\Property(property="language_autonym",  ref="#/components/schemas/LanguageTranslation/properties/name"),
     *              @OA\Property(property="language_altNames", ref="#/components/schemas/LanguageTranslation/properties/name"),
     *              @OA\Property(property="iso",               ref="#/components/schemas/Language/properties/iso"),
     *              @OA\Property(property="date",              ref="#/components/schemas/Bible/properties/date"),
     *              @OA\Property(property="filesets", type="object",
     *                         @OA\Property(property="dbp-prod",type="array", @OA\Items(ref="#/components/schemas/BibleFileset"))
     *              )
     *     )
     *    ),
     *    @OA\Property(property="meta",ref="#/components/schemas/pagination")
     *   )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v4_bible.search",
     *   description="The bible being returned",
     *   title="v4_bible.search",
     *   @OA\Xml(name="v4_bible.search"),
     *   @OA\Items(
     *       @OA\Property(property="abbr",          ref="#/components/schemas/Bible/properties/id"),
     *       @OA\Property(property="name",          ref="#/components/schemas/BibleTranslation/properties/name"),
     *       @OA\Property(property="language_id",   ref="#/components/schemas/Language/properties/id")
     *   )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v4_bible.one",
     *   description="The bible being returned",
     *   title="v4_bible.one",
     *   @OA\Xml(name="v4_bible.one"),
     *   @OA\Items(
     *              @OA\Property(property="abbr",          ref="#/components/schemas/Bible/properties/id"),
     *              @OA\Property(property="alphabet",      ref="#/components/schemas/Alphabet/properties/script"),
     *              @OA\Property(property="mark",          ref="#/components/schemas/Bible/properties/copyright"),
     *              @OA\Property(property="name",          ref="#/components/schemas/BibleTranslation/properties/name"),
     *              @OA\Property(property="description",   ref="#/components/schemas/BibleTranslation/properties/description"),
     *              @OA\Property(property="vname",         ref="#/components/schemas/BibleTranslation/properties/name"),
     *              @OA\Property(property="vdescription",  ref="#/components/schemas/BibleTranslation/properties/description"),
     *              @OA\Property(property="publishers",    ref="#/components/schemas/Organization"),
     *              @OA\Property(property="providers",     ref="#/components/schemas/Organization"),
     *              @OA\Property(property="language",      ref="#/components/schemas/Language/properties/name"),
     *              @OA\Property(property="iso",           ref="#/components/schemas/Language/properties/iso"),
     *              @OA\Property(property="date",          ref="#/components/schemas/Bible/properties/date"),
     *              @OA\Property(property="country",       ref="#/components/schemas/Country/properties/name"),
     *              @OA\Property(property="books",         ref="#/components/schemas/Book/properties/id"),
     *              @OA\Property(property="links",         ref="#/components/schemas/BibleLink"),
     *              @OA\Property(property="filesets",      ref="#/components/schemas/BibleFileset"),
     *              @OA\Property(property="custom_font_required",      ref="#/components/schemas/Bible/properties/custom_font_required"),
     *     )
     *   )
     * )
     */
    public function transformForV4($bible)
    {
        switch ($this->route) {
            case 'v4_bible.archival':
                $name = $bible->translations->where('language_id', $bible->english_language_id)->first();
                $vName = ($bible->iso != 'eng') ? $bible->translations->where('language_id', $bible->language_id)->first() : false;
                $output = [
                    'abbr'              => $bible->id,
                    'script'            => $bible->script,
                    'name'              => $name->name ?? '',
                    'vname'             => $vName->name ?? '',
                    'language'          => optional($bible->language)->name,
                    'autonym'           => optional($bible->language)->autonym,
                    'iso'               => optional($bible->language)->iso,
                    'date'              => $bible->date,
                    'links_count'       => $bible->links_count + $bible->filesets->count(),
                    'organizations'     => '',
                    'types'             => $bible->filesets->pluck('set_type_code')->unique()->implode(',')
                ];
                if ($bible->langauge && $bible->langauge->relationLoaded('translations')) {
                    $output['language_altNames'] = $bible->language->translations->pluck('name');
                }
                if ($bible->relationLoaded('filesets')) {
                    $output_organizations = [];
                    foreach ($bible->filesets as $fileset) {
                        if ($fileset->relationLoaded('copyrightOrganization')) {
                            $output_organizations[] = $fileset->copyrightOrganization->pluck('organization_id')->implode(',');
                        }
                    }
                    $output_organizations = Arr::flatten(array_unique($output_organizations));
                    $output['organizations'] = $output_organizations;
                }
                if ($bible->relationLoaded('country')) {
                    $output['country_id']   = '';
                    $output['country_name'] = '';
                    $output['continent_id'] = '';
                    if (isset($bible->country[0])) {
                        $output['country_name'] = $bible->country[0]->name;
                        $output['country_id']   = $bible->country[0]->country_id;
                        $output['continent_id'] = $bible->country[0]->continent;
                    }
                }
                return $output;
            /**
             * schema="v4_bible.all"
             */
            case 'v4_bible.all':
                $output = [
                    'abbr'              => $bible->id,
                    'name'              => $bible->ctitle,
                    'vname'             => $bible->vtitle,
                    'language'          => $bible->language_current ?? null,
                    'autonym'           => $bible->language_autonym ?? null,
                    'language_id'       => $bible->language_id,
                    'language_rolv_code'=> $bible->language_rolv_code,
                    'iso'               => $bible->iso ?? null,
                    'date'              => $bible->date
                ];

                if ($bible->relationLoaded('filesets')) {
                    $output['filesets'] = $bible->filesets->mapToGroups(function ($item, $key) {
                        return [$item['asset_id'] => $this->filesetWithMeta($item)];
                    });
                }

                return $output;
            /**
             * schema="v4_bible.search"
             */
            case 'v4_bible.search':
            case 'v4_bible_by_id.search':
                return [
                    'abbr'              => $bible->bible_id,
                    'name'              => $bible->name,
                    'language_id'       => $bible->language_id,
                ];
            /**
             * schema="v4_bible.one",
             */
            case 'v4_bible.one':
                $currentTranslation = optional($bible->translations->where('language_id', $GLOBALS['i18n_id']));
                $fonts = $bible->filesets->reduce(function ($carry, $item) {
                    if ($item->relationLoaded('fonts')) {
                        foreach ($item->fonts as $font) {
                            if (!isset($carry[$font->name])) {
                                $carry = ['name' => $font->name, 'data' => $font->data, 'type' => $font->type];
                            }
                        }
                    }
                    return $carry;
                }, null);

                $bible = [
                    'abbr'          => $bible->id,
                    'alphabet'      => $bible->alphabet,
                    'mark'          => $bible->copyright,
                    'name'          => optional($currentTranslation->first())->name,
                    'description'   => optional($currentTranslation->first())->description,
                    'vname'         => optional($bible->vernacularTranslation)->name,
                    'vdescription'  => optional($bible->vernacularTranslation)->description,
                    'publishers'    => optional($this->filterOrganizations($bible->organizations))
                        ->where('pivot.relationship_type', 'publisher')->all(),
                    'providers'     => optional($this->filterOrganizations($bible->organizations))
                        ->where('pivot.relationship_type', 'provider')->all(),
                    'language'      => optional($bible->language)->name,
                    'language_id'   => optional($bible->language)->id,
                    'iso'           => optional($bible->language)->iso,
                    'language_rolv_code' => optional($bible->language)->rolv_code,
                    'date'          => $bible->date,
                    'country'       => $bible->language->primaryCountry->name ?? '',
                    'books'         => $bible->books->sortBy('book.' . $bible->versification . '_order')->each(function ($book) {
                        // convert to integer array
                        $chapters = explode(',', $book->chapters);
                        foreach ($chapters as $key => $chapter) {
                            $chapters[$key] = intval($chapter);
                        }
                        $book->chapters = $chapters;
                        $book->testament = isset($book->book) && isset($book->book['book_testament'])
                            ? $book->book['book_testament']
                            : null;
                        unset($book->book);
                        return $book;
                    })->values(),
                    'links'        => $bible->links,
                    'filesets'     => $bible->filesets->mapToGroups(function ($item) {
                        return [$item['asset_id'] => $this->filesetWithMeta($item)];
                    }),
                    'custom_font_required' => $bible->custom_font_required,
                    'fonts' => $fonts
                ];

                return $bible;

            default:
                return [];
        }
    }

    /**
     * Transform the fileset object to array with the selected values to display
     *
     * @param BibleFileset $fileset
     *
     * @return array
     */
    private function filesetWithMeta(BibleFileset $fileset) : array
    {
        $fileset_data = [
            'id' => $fileset['id'],
            'type' => $fileset->set_type_code,
            'size' => $fileset->set_size_code,
        ];

        $meta_records_indexed = $fileset->getMetaTagsIndexedByName();

        if (!empty($meta_records_indexed)) {
            return $fileset_data + $meta_records_indexed;
        }

        return $fileset_data;
    }
}
