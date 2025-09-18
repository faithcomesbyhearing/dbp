<?php

namespace App\Transformers\V2\LibraryCatalog;

use League\Fractal\TransformerAbstract;
use App\Transformers\Traits\OrganizationFilterTrait;
/**
 * Class LibraryMetadataTransformer
 *
 *
 * @package App\Transformers\V2\LibraryCatalog
 */
class LibraryMetadataTransformer extends TransformerAbstract
{
    use OrganizationFilterTrait;
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
     *          @OA\Property(property="mark",              ref="#/components/schemas/LicenseGroup/properties/copyright"),
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
            'volume_summary' => $bible_fileset->copyright,
            'font_copyright' => null,
            'font_url'       => null
        ];
        $organization = $this->filterOrganization($bible_fileset->organization);

        if ($organization) {
            $output['organization'][] = [
                'organization_id'       => isset($organization->id)
                    ? (string) $organization->id
                    : '',
                'organization'          => isset($organization->name)
                    ? $organization->name
                    : '',
                'organization_english'  => isset($organization->slug)
                    ? $organization->slug
                    : '',
                'organization_role'     => isset($organization->role_name)
                    ? $organization->role_name
                    : '',
                'organization_url'      => isset($organization->url_website)
                    ? $organization->url_website
                    : '',
                'organization_donation' => isset($organization->url_donate)
                    ? $organization->url_donate
                    : '',
                'organization_address'  => isset($organization->address)
                    ? $organization->address
                    : '',
                'organization_address2' => isset($organization->address2)
                    ? $organization->address2
                    : '',
                'organization_city'     => isset($organization->city)
                    ? $organization->city
                    : '',
                'organization_state'    => isset($organization->state)
                    ? $organization->state
                    : '',
                'organization_country'  => isset($organization->country)
                    ? $organization->country
                    : '',
                'organization_zip'      => isset($organization->zip)
                    ? $organization->zip
                    : '',
                'organization_phone'    => isset($organization->phone)
                    ? $organization->phone
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
