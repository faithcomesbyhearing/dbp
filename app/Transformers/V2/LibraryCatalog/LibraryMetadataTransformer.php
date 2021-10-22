<?php

namespace App\Transformers\V2\LibraryCatalog;

use League\Fractal\TransformerAbstract;

/**
 * Class LibraryMetadataTransformer
 *
 *
 * @package App\Transformers\V2\LibraryCatalog
 */
class LibraryMetadataTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @OA\Schema (
     *     type="array",
     *     schema="v2_library_metadata",
     *     description="The various version ids in the old version 2 style",
     *     title="v2_library_metadata",
     *     @OA\Xml(name="v2_library_metadata"),
     *     @OA\Items(
     *          @OA\Property(property="dam_id",            ref="#/components/schemas/BibleFileset/properties/id"),
     *          @OA\Property(property="mark",              ref="#/components/schemas/BibleFilesetCopyright/properties/copyright"),
     *          @OA\Property(property="volume_summary",    ref="#/components/schemas/BibleFilesetCopyright/properties/copyright_description"),
     *          @OA\Property(property="font_copyright",    ref="#/components/schemas/AlphabetFont/properties/copyright"),
     *          @OA\Property(property="font_url",          ref="#/components/schemas/AlphabetFont/properties/url"),
     *          @OA\Property(property="organization",
     *              type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="organization_id",      ref="#/components/schemas/Organization/properties/id"),
     *                      @OA\Property(property="organization",         ref="#/components/schemas/OrganizationTranslation/properties/name"),
     *                      @OA\Property(property="organization_english", ref="#/components/schemas/OrganizationTranslation/properties/name"),
     *                      @OA\Property(property="organization_role",    ref="#/components/schemas/BibleOrganization/properties/relationship_type"),
     *                      @OA\Property(property="organization_url",     ref="#/components/schemas/Organization/properties/url_website"),
     *                      @OA\Property(property="organization_donation",ref="#/components/schemas/Organization/properties/url_donate"),
     *                      @OA\Property(property="organization_address", ref="#/components/schemas/Organization/properties/address"),
     *                      @OA\Property(property="organization_address2",ref="#/components/schemas/Organization/properties/address2"),
     *                      @OA\Property(property="organization_city",    ref="#/components/schemas/Organization/properties/city"),
     *                      @OA\Property(property="organization_state",   ref="#/components/schemas/Organization/properties/state"),
     *                      @OA\Property(property="organization_country", ref="#/components/schemas/Organization/properties/country"),
     *                      @OA\Property(property="organization_zip",     ref="#/components/schemas/Organization/properties/zip"),
     *                      @OA\Property(property="organization_phone",   ref="#/components/schemas/Organization/properties/phone"),
     *                   )
     *
     *              )
     *          )
     *     )
     * )
     * @param $bible_fileset
     *
     * @return array
     */
    public function transform($bible_fileset)
    {
        $copyright = $bible_fileset->copyright;
        $mark = $this->formatCopyrightMark($copyright);

        $output = [
            'dam_id'         => isset($bible_fileset->dam_id) ? $bible_fileset->dam_id : $bible_fileset->id,
            'mark'           => $mark,
            'volume_summary' => $bible_fileset->copyright_description,
            'font_copyright' => null,
            'font_url'       => null
        ];

        if ($bible_fileset->organization) {
            $output['organization'][] = [
                'organization_id'       => (string) $bible_fileset->organization->id,
                'organization'          => isset($bible_fileset->organization->name)
                    ? $bible_fileset->organization->name
                    : '',
                'organization_english'  => isset($bible_fileset->organization->slug)
                    ? $bible_fileset->organization->slug
                    : '',
                'organization_role'     => isset($bible_fileset->organization->role_name)
                    ? $bible_fileset->organization->role_name
                    : '',
                'organization_url'      => isset($bible_fileset->organization->url_website)
                    ? $bible_fileset->organization->url_website
                    : '',
                'organization_donation' => isset($bible_fileset->organization->url_donate)
                    ? $bible_fileset->organization->url_donate
                    : '',
                'organization_address'  => isset($bible_fileset->organization->address)
                    ? $bible_fileset->organization->address
                    : '',
                'organization_address2' => isset($bible_fileset->organization->address2)
                    ? $bible_fileset->organization->address2
                    : '',
                'organization_city'     => isset($bible_fileset->organization->city)
                    ? $bible_fileset->organization->city
                    : '',
                'organization_state'    => isset($bible_fileset->organization->state)
                    ? $bible_fileset->organization->state
                    : '',
                'organization_country'  => isset($bible_fileset->organization->country)
                    ? $bible_fileset->organization->country
                    : '',
                'organization_zip'      => isset($bible_fileset->organization->zip)
                    ? $bible_fileset->organization->zip
                    : '',
                'organization_phone'    => isset($bible_fileset->organization->phone)
                    ? $bible_fileset->organization->phone
                    : '',
            ];
        }
        return $output;
    }

    private function formatCopyrightMark($copyright)
    {
        $mark = null;
        $mark_types = ['Audio', 'Text', 'Video'];
        foreach ($mark_types as $type) {
            if (!$mark) {
                $mark = optional(explode($type.':', $copyright))[1];
            }
        }
        return $mark ? $mark : $copyright;
    }
}
