<?php

namespace App\Transformers\Traits;

use App\Models\Organization\Organization;
use Illuminate\Support\Collection;

trait OrganizationFilterTrait
{
    /**
     * Filter organizations to exclude specific ones or modify them.
     *
     * @param Collection $organizations
     * @return \Illuminate\Support\Collection
     */
    protected function filterOrganizations(Collection $organizations) : Collection
    {
        return collect($organizations)->map(function (Organization $org): object {
            return $this->filterOrganization($org);
        });
    }

    protected function filterOrganization(Organization|\stdClass $organization): ?object
    {
        if ($organization->id == Organization::SIL_LICENSOR_ID) {
            // If the organization is SIL, we return an empty Organization object with a specific translation
            return (object) [
                'slug' => Organization::USED_WITH_PERMISSION_SLUG,
                'translations' => [(object) ['name' => Organization::USED_WITH_PERMISSION]]
            ];
        }
        return $organization;
    }
}
