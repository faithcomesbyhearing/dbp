<?php

namespace App\Traits;

use App\Models\User\AccessGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Models\User\AccessGroupFileset;
use App\Models\User\AccessType;
use App\Models\User\Key;
use App\Models\Bible\BibleFileset;
use App\Exceptions\ResponseException as Response;
use DB;

trait AccessControlAPI
{
    /**
     * Returns a list of filesets (represented by their hash IDs) and an dash-separated list of access group
     * names for the authenticated user.
     *
     * @param string $api_key - The User's API key
     *
     * @return object
     */
    public function accessControl(?string $api_key, $control_table = 'filesets')
    {
        return cacheRemember(
            'access_control:',
            [$control_table, $api_key],
            now()->addDay(),
            function () use ($api_key) {
                $user_location = geoip(request()->ip());
                $country_code = (!isset($user_location->iso_code)) ? $user_location->iso_code : null;
                $continent = (!isset($user_location->continent)) ? $user_location->continent : null;

                // Defaults to type 'api' because that's the only access type; needs modification once
                // there are multiple
                $access_type = AccessType::findOneByCountryCodeAndContinent($country_code, $continent);

                if (!$access_type) {
                    return (object) ['identifiers' => [], 'string' => ''];
                }

                // Access Control has historically been tied to fileset hashes.
                $access_group_ids = getAccessGroups();

                $accessGroups = AccessGroup::select('id', 'name')
                    ->whereIn('id', $access_group_ids)
                    ->where('name', '!=', 'RESTRICTED')
                    ->get()
                ;
                // Use Eloquent everywhere except for this giant request
                $identifiers = AccessGroupFileset::select('hash_id as identifier')
                    ->whereIn('access_group_id', $access_group_ids)->distinct()->get();

                return (object) [
                    'identifiers' => collect($identifiers)->pluck('identifier')->toArray(),
                    'string' => $accessGroups->pluck('name')->implode('-'),
                ];
            }
        );
    }

    private function genericAccessControl(?string $api_key, ?string $fileset_hash, array $access_group_ids = [])
    {

        $cache_params = [$api_key, $fileset_hash, join('', $access_group_ids)];
        return cacheRemember(
            'bulk_access_control',
            $cache_params,
            now()->addMinutes(40),
            function () use ($api_key, $fileset_hash, $access_group_ids) {
                $user_location = geoip(request()->ip());
                $country_code = (!isset($user_location->iso_code)) ? $user_location->iso_code : null;
                $continent = (!isset($user_location->continent)) ? $user_location->continent : null;

                // Defaults to type 'api' because that's the only access type;
                // needs modification once there are multiple
                $access_type = AccessType::findOneByCountryCodeAndContinent($country_code, $continent);

                if (!$access_type) {
                    return [];
                }

                $access_groups_by_api_key_user = getAccessGroups();

                if (empty($access_groups_by_api_key_user)) {
                    return [];
                }

                return AccessGroupFileset::select('hash_id')
                    ->whereIn('access_group_id', $access_groups_by_api_key_user)
                    ->when(!empty($access_group_ids), function ($query) use ($access_group_ids) {
                        $query->whereIn('access_group_id', $access_group_ids);
                    })
                    ->where('hash_id', $fileset_hash)
                    ->get();
            }
        );
    }

    /**
     * Validate if an api key belongs to the Bible.is client
     *
     * @param string $api_key
     *
     * @return string
     */
    private function doesApiKeyBelongToBibleis(string $api_key) : bool
    {
        return config('auth.compat_users.api_keys.bibleis') === $api_key;
    }

    public function allowedByAccessControl(BibleFileset $fileset)
    {
        $access_control = $this->genericAccessControl($this->key, $fileset->hash_id);
        if (sizeof($access_control) === 0) {
            return $this->setStatusCode(HttpResponse::HTTP_FORBIDDEN)
                ->replyWithError(trans('api.bible_fileset_errors_401'));
        }
        return true;
    }

    public function allowedByBulkAccessControl(BibleFileset $fileset)
    {
        $allowed_fileset = $this->genericAccessControl($this->key, $fileset->hash_id);
        if (sizeof($allowed_fileset) === 0) {
            return $this->setStatusCode(HttpResponse::HTTP_NOT_FOUND)->replyWithError('Not found');
        }
        return true;
    }

    public function allowedForDownload(BibleFileset $fileset)
    {
        // Note that in the future, this constraint should be updated to account for
        // whether the user is logged in or not.
        // if ($this->doesApiKeyBelongToBibleis($this->key) && isUserLoggedIn()) {

        // If the API key belongs to bible.is DBP will utilize the bible.is access group list else,
        // the system will utilize the generic access group list instead.
        if ($this->doesApiKeyBelongToBibleis($this->key)) {
            $download_access_group_array_ids = optional(getAccessGroups())->toArray();
        } else {
            $download_access_group_array_ids = getDownloadAccessGroupList();
        }

        $allowed_fileset_for_download = $this->genericAccessControl(
            $this->key,
            $fileset->hash_id,
            $download_access_group_array_ids
        );

        if (sizeof($allowed_fileset_for_download) === 0) {
            return $this
                ->setStatusCode(Response::HTTP_FORBIDDEN)
                ->replyWithError(Response::getStatusTextByCode(Response::HTTP_FORBIDDEN));
        }
        return true;
    }
}
