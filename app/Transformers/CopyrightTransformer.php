<?php

namespace App\Transformers;

use App\Models\Bible\BibleFileset;
use App\Transformers\Traits\OrganizationFilterTrait;

class CopyrightTransformer extends BaseTransformer
{
    use OrganizationFilterTrait;

    /**
     * Transform copyright fileset data, filtering organizations as needed.
     *
     * @param BibleFileset $fileset
     * @return array
     */
    public function transform(BibleFileset $fileset)
    {
        $transformed = [
            'id' => $fileset->id,
            'type' => $fileset->type,
            'size' => $fileset->size,
        ];

        if (isset($fileset->asset_id)) {
            $transformed['asset_id'] = $fileset->asset_id;
        }

        // Transform copyright and filter organizations if they exist
        if ($fileset->relationLoaded('copyright') && $fileset->copyright) {
            $transformed['copyright'] = $this->transformCopyright($fileset->copyright);
        }

        return $transformed;
    }

    /**
     * Transform copyright data and filter organizations.
     *
     * @param mixed $copyright
     * @return array
     */
    private function transformCopyright($copyright)
    {
        $copyrightData = [
            "copyright_date" => $copyright->copyright_date ?? null,
            "copyright" => $copyright->copyright ?? null,
            "created_at" => $copyright->created_at ?? null,
            "updated_at" => $copyright->updated_at ?? null,
            "open_access" => $copyright->open_access ?? false,
            "is_combined" => $copyright->is_combined ?? false,
        ];

        // Filter organizations if they exist
        if (isset($copyright->organizations) && $copyright->organizations->isNotEmpty()) {
            $copyrightData['organizations'] = $this->filterOrganizations($copyright->organizations)->map(function ($org) {
                return $this->transformOrganizationForCopyright($org);
            })->toArray();
        }

        return $copyrightData;
    }

    /**
     * Transform individual organization for copyright context.
     *
     * @param mixed $organization
     * @return array
     */
    private function transformOrganizationForCopyright($organization)
    {
        return [
            'id'                => $organization->id ?? null,
            'slug'              => $organization->slug ?? null,
            'abbreviation'      => $organization->abbreviation ?? null,
            'description'       => $organization->description ?? null,
            'description_short' => $organization->tagline ?? null,
            'phone'             => $organization->phone ?? null,
            'email'             => $organization->email ?? null,
            'email_director'    => $organization->email_director ?? null,
            'logos'             => $organization->logos ?? [],
            'primaryColor'      => $organization->primaryColor ?? null,
            'secondaryColor'    => $organization->secondaryColor ?? null,
            'inactive'          => $organization->inactive ?? false,
            'url_site'          => $organization->url_website ?? '',
            'url_donate'        => isset($organization->url_donate) ? $organization->url_donate : '',
            'url_twitter'       => isset($organization->url_twitter) ? $organization->url_twitter : '',
            'url_facebook'      => isset($organization->url_facebook) ? $organization->url_facebook : '',
            'address'           => $organization->address ?? null,
            'address2'          => $organization->address2 ?? null,
            'city'              => $organization->city ?? null,
            'state'             => $organization->state ?? null,
            'country'           => $organization->country ?? null,
            'zip'               => $organization->zip ?? null,
            'latitude'          => $organization->latitude ?? null,
            'longitude'         => $organization->longitude ?? null,
            'translations'      => $organization->translations ?? [],
        ];
    }
}
