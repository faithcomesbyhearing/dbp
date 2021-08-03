<?php

namespace App\Traits;

use App\Models\User\AccessGroupKey;
use App\Models\User\AccessGroupFileset;
use App\Models\User\AccessType;
use App\Models\User\Key;
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
    public function accessControl($api_key, $control_table = 'filesets')
    {
        return cacheRemember('access_control:', [$api_key, $control_table], now()->addHour(), function () use ($api_key, $control_table) {
            $user_location = geoip(request()->ip());
            $country_code = (!isset($user_location->iso_code)) ? $user_location->iso_code : null;
            $continent = (!isset($user_location->continent)) ? $user_location->continent : null;

            // Defaults to type 'api' because that's the only access type; needs modification once there are multiple
            $access_type = AccessType::where('name', 'api')
                ->where(function ($query) use ($country_code) {
                    $query->where('country_id', $country_code);
                })
                ->where(function ($query) use ($continent) {
                    $query->where('continent_id', $continent);
                })
                ->first();
            if (!$access_type) {
                return (object) ['hashes' => [], 'string' => ''];
            }

            $key = Key::select('id')->where('key', $api_key)->first();
            $accessGroups = AccessGroupKey::where('key_id', $key->id)
            ->get()
            ->pluck('access')
            ->where('name', '!=', 'RESTRICTED')
            ->map(function ($access) {
                return collect($access->toArray())
                    ->only(['id', 'name'])
                    ->all();
            });
            $dbp_users = config('database.connections.dbp_users.database');

            switch ($control_table) {
                case 'languages':
                    $identifiers = DB::select(
                        DB::raw(
                            'select distinct l.id identifier
                            from ' . $dbp_users . '.user_keys uk
                            join ' . $dbp_users . '.access_group_api_keys agak on agak.key_id = uk.id
                            join access_group_filesets agf on agf.access_group_id = agak.access_group_id
                            join bible_fileset_connections bfc on agf.hash_id = bfc.hash_id
                            join bibles b on bfc.bible_id = b.id
                            join languages l on l.id = b.language_id
                            where uk.id = ?'
                        ), [$key->id]
                    );
                    break; 
                case 'bibles':
                    $identifiers = DB::select(
                        DB::raw(
                            'select distinct bfc.bible_id identifier
                            from ' . $dbp_users . '.user_keys uk
                            join ' . $dbp_users . '.access_group_api_keys agak on agak.key_id = uk.id
                            join access_group_filesets agf on agf.access_group_id = agak.access_group_id
                            join bible_fileset_connections bfc on agf.hash_id = bfc.hash_id
                            where uk.id = ?'
                        ), [$key->id]
                    );
                    break;
                default:
                    // Use Eloquent everywhere except for this giant request
                    $identifiers = AccessGroupFileset::select('hash_id as identifier')
                        ->whereIn('access_group_id', $accessGroups->pluck('id'))->distinct()->get();
                    break;
            }
            
            return (object) [
                'hashes' => collect($identifiers)->pluck('identifier'),
                'string' => $accessGroups->pluck('name')->implode('-'),
            ];
        });
    }

    private function bulkAccessControl($api_key, $fileset_hash)
    {
        $cache_params = [$api_key, $fileset_hash];
        return cacheRemember('bulk_access_control', $cache_params, now()->addMinutes(40), function () use ($api_key, $fileset_hash) {
            $user_location = geoip(request()->ip());
            $country_code = (!isset($user_location->iso_code)) ? $user_location->iso_code : null;
            $continent = (!isset($user_location->continent)) ? $user_location->continent : null;

            // Defaults to type 'api' because that's the only access type; needs modification once there are multiple
            $access_type = AccessType::where('name', 'api')
                ->where(function ($query) use ($country_code) {
                    $query->where('country_id', $country_code);
                })
                ->where(function ($query) use ($continent) {
                    $query->where('continent_id', $continent);
                })
                ->first();

            if (!$access_type) {
                return (object) ['hashes' => [], 'string' => ''];
            }

            $dbp_database = config('database.connections.dbp.database');
            $key = Key::select('id')->where('key', $api_key)->first();
            $allowed_fileset =
                AccessGroupKey::join($dbp_database . '.access_group_filesets as acc_filesets', function ($join) use ($key, $fileset_hash) {
                    $join->on('access_group_api_keys.access_group_id', '=', 'acc_filesets.access_group_id')
                        ->where('key_id', $key->id)
                        ->where('hash_id', $fileset_hash);
                })->get();
            
            return $allowed_fileset;
        });
    }

    public function blockedByAccessControl($fileset)
    {
        $access_control = $this->accessControl($this->key);
        if (!\in_array($fileset->hash_id, $access_control->hashes, true)) {
            return $this->setStatusCode(403)->replyWithError(trans('api.bible_fileset_errors_401'));
        }
        return false;
    }

    public function allowedByBulkAccessControl($fileset)
    {
        $allowed_fileset = $this->bulkAccessControl($this->key, $fileset->hash_id);
        if (sizeof($allowed_fileset) === 0) {
            return $this->setStatusCode(404)->replyWithError('Not found');
        }
        return true;
    }
}
