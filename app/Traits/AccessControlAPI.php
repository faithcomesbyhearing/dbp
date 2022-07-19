<?php

namespace App\Traits;

use App\Models\User\AccessGroupKey;
use App\Models\User\AccessGroupFileset;
use App\Models\User\AccessType;
use App\Models\User\Key;
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
    public function accessControl($api_key, $control_table = 'filesets')
    {
        return cacheRemember('access_control:', [$control_table, $api_key], now()->addDay(), function () use ($api_key, $control_table) {
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
                return (object) ['identifiers' => [], 'string' => ''];
            }

            $key = Key::select('id')->where('key', $api_key)->first();
            $accessGroups = AccessGroupKey::where('key_id', $key->id)
                ->with([
                    'access' => function ($query) {
                        $query->where('name', '!=', 'RESTRICTED');
                    }
                ])
                ->get()
                ->pluck('access')
                ->map(function ($access) {
                    return !is_null($access)
                        ? collect($access->toArray())
                            ->only(['id', 'name'])
                            ->all()
                        : collect([]);
                })->filter(function ($access) {
                    return !empty($access) && isset($access['id']);
                });

            // Access Control has historically been tied to fileset hashes.
            // As the number of filesets grows, this has been affected query
            // performance for endpoints such as Languages (and similarly for  Bibles),
            // which return a list of Languages associated with any fileset
            // content associated with the API key. For this case, the query has been optimized,
            // and returns a list of language ids instead of a list of fileset hashes
            $dbp_users = config('database.connections.dbp_users.database');
            $dbp_prod = config('database.connections.dbp.database');
            switch ($control_table) {
                case 'languages':
                    $identifiers = DB::select(
                        DB::raw(
                            'select distinct l.id identifier
                            from ' . $dbp_users . '.user_keys uk
                            join ' . $dbp_users . '.access_group_api_keys agak on agak.key_id = uk.id
                            join ' . $dbp_prod . '.access_group_filesets agf on agf.access_group_id = agak.access_group_id
                            join ' . $dbp_prod . '.bible_fileset_connections bfc on agf.hash_id = bfc.hash_id
                            join ' . $dbp_prod . '.bibles b on bfc.bible_id = b.id
                            join ' . $dbp_prod . '.languages l on l.id = b.language_id
                            where uk.id = ?'
                        ),
                        [$key->id]
                    );
                    break;
                case 'bibles':
                    $identifiers = DB::select(
                        DB::raw(
                            'select distinct bfc.bible_id identifier
                            from ' . $dbp_users . '.user_keys uk
                            join ' . $dbp_users . '.access_group_api_keys agak on agak.key_id = uk.id
                            join ' . $dbp_prod . '.access_group_filesets agf on agf.access_group_id = agak.access_group_id
                            join ' . $dbp_prod . '.bible_fileset_connections bfc on agf.hash_id = bfc.hash_id
                            where uk.id = ?'
                        ),
                        [$key->id]
                    );
                    break;
                default:
                    // Use Eloquent everywhere except for this giant request
                    $identifiers = AccessGroupFileset::select('hash_id as identifier')
                        ->whereIn('access_group_id', $accessGroups->pluck('id'))->distinct()->get();
                    break;
            }

            return (object) [
                'identifiers' => collect($identifiers)->pluck('identifier')->toArray(),
                'string' => $accessGroups->pluck('name')->implode('-'),
            ];
        });
    }

    private function genericAccessControl($api_key, $fileset_hash, $access_group_ids = [])
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
                $access_type = AccessType::where('name', 'api')
                    ->where(function ($query) use ($country_code) {
                        $query->where('country_id', $country_code);
                    })
                    ->where(function ($query) use ($continent) {
                        $query->where('continent_id', $continent);
                    })
                    ->select('id', 'name')
                    ->first();

                if (!$access_type) {
                    return (object) ['identifiers' => [], 'string' => ''];
                }

                $dbp_database = config('database.connections.dbp.database');

                return AccessGroupKey::join('user_keys', function ($join) use ($api_key) {
                    $join->on('user_keys.id', '=', 'access_group_api_keys.key_id')
                        ->where('user_keys.key', $api_key);
                })->join(
                    $dbp_database . '.access_group_filesets as acc_filesets',
                    function ($join) use ($fileset_hash, $access_group_ids) {
                        $join->on('access_group_api_keys.access_group_id', '=', 'acc_filesets.access_group_id')
                            ->where('hash_id', $fileset_hash);

                        if (!empty($access_group_ids)) {
                            $join->whereIn('acc_filesets.access_group_id', $access_group_ids);
                        }
                    }
                )->select('user_keys.id')->get();
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

    public function blockedByAccessControl($fileset)
    {
        $access_control = $this->accessControl($this->key);
        if (!\in_array($fileset->hash_id, $access_control->identifiers, true)) {
            return $this->setStatusCode(403)->replyWithError(trans('api.bible_fileset_errors_401'));
        }
        return false;
    }

    public function allowedByBulkAccessControl($fileset)
    {
        $allowed_fileset = $this->genericAccessControl($this->key, $fileset->hash_id);
        if (sizeof($allowed_fileset) === 0) {
            return $this->setStatusCode(404)->replyWithError('Not found');
        }
        return true;
    }

    public function allowedForDownload($fileset)
    {
        if ($this->doesApiKeyBelongToBibleis($this->key)) {
            $download_access_group_array_ids = AccessGroupKey::getAccessGroupIdsByApiKey($this->key)->toArray();
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
    }
}
