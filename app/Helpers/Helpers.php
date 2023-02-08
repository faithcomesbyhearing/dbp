<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

function getAccessGroups() : \Illuminate\Support\Collection
{
    $group_ids = request()->input('middleware_access_group_ids');

    if (empty($group_ids)) {
        \Log::channel('errorlog')->error(["Missing access group ids", Response::HTTP_UNPROCESSABLE_ENTITY]);
        abort(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "Missing parameter access group ids."
        );
    }

    return $group_ids;
}

/**
 * Get param from request object and check that the param is set if param is required
 *
 * @param string $param_name
 * @param bool $required
 * @param Array|string|int|null|bool $init_value
 *
 * @return array|bool|null|string
 */
function getAndCheckParam(
    string $param_name,
    bool $required = false,
    Array|string|int|null|bool $init_value = null
) : Array|string|int|null {
    $parameter_value = $init_value;
    $parameter_value = removeSpaceAndCntrlParameter($parameter_value);

    if (!is_null($init_value) && $parameter_value !== '') {
        return $init_value;
    }

    foreach (explode('|', $param_name) as $current_param) {
        if (request()->has($current_param)) {
            $parameter_value = request()->input($current_param);
        } elseif (session()->has($current_param)) {
            $parameter_value = request()->get($current_param);
        } elseif (request()->hasHeader($current_param)) {
            $parameter_value = request()->header($current_param);
        }
    }

    if ($required && empty($parameter_value)) {
        Log::channel('errorlog')->error(["Missing Param '$param_name", 422]);
        abort(
            422,
            "You need to provide the missing parameter '$param_name'. Please append it to the url or the request Header."
        );
    }

    return $parameter_value;
}

/**
 * Check query parameters for a given parameter name, and check the headers for the same parameter name;
 * also allow for two or more parameter names to match to the same $paramName using pipes to separate them.
 * Also check specially for the "key" param to come from the Authorization header.
 * Finally, allows for values set in paths to override all other values.
 *
 * @param string $paramName
 * @param bool $required
 * @param null|string $inPathValue
 *
 * @return array|bool|null|string
 */
function checkParam(string $paramName, $required = false, $inPathValue = null)
{
    // Path params
    if ($inPathValue) {
        return $inPathValue;
    }

    foreach (explode('|', $paramName) as $current_param) {
        // Header params
        if ($url_header = request()->header($current_param)) {
            return $url_header;
            break;
        }

        // GET/JSON/POST body params
        if ($queryParam = request()->input($current_param)) {
            return $queryParam;
            break;
        }

        if ($session_param = session()->get($current_param)) {
            return $session_param;
            break;
        }
    }

    if ($required) {
        Log::channel('errorlog')->error(["Missing Param '$paramName", 422]);
        abort(
            422,
            "You need to provide the missing parameter '$paramName'. Please append it to the url or the request Header."
        );
    }
}

function checkBoolean(string $paramName, $required = false, $inPathValue = null)
{
    $param = checkParam($paramName, $required, $inPathValue);
    $param = (bool) $param &&  strtolower($param) !== 'false';
    return $param;
}

function cacheAdd($cache_key, $value, $duration)
{
    return Cache::add($cache_key, $value, $duration);
}

function cacheForget($cache_key)
{
    return Cache::forget($cache_key);
}

function cacheFlush()
{
    return Cache::flush();
}

function cacheGet($cache_key)
{
    return Cache::get($cache_key);
}

function createCacheLock($cache_key, $lock_timeout = 10)
{
    return Cache::lock($cache_key . '_lock', $lock_timeout);
}

/**
 * Set and get the result of callback into cache storage. The key will create from the cache_args array
 *
 * @param string $key
 * @param Array $cache_args
 * @param Carbon $ttl
 * @param Closure $callback
 * @return mixed
 */
function cacheRemember($cache_key, $cache_args, $ttl, $callback)
{
    $key = generateCacheString($cache_key, $cache_args);
    return cacheRememberByKey($key, $ttl, $callback);
}

/**
 * Set and get the result of callback into cache storage usign a given key
 *
 * @param string $key
 * @param Carbon $ttl
 * @param Closure $callback
 * @return mixed
 */
function cacheRememberByKey(string $key, Carbon $ttl, Closure $callback)
{
    // if something fails on the callback, release the lock
    // 20 seconds was selected to allow for the longest query to complete.
    // This is not based on any empirical evidence.
    $lock_timeout = 20;
    $value = Cache::get($key);

    if (!is_null($value)) {
        // got the cached value, return it
        return $value;
    }

    // cache not set. try to acquire lock to gain access to the callback
    $lock = createCacheLock($key);
    if ($lock->acquire()) {
        try {
            // lock acquired. access resource via callback
            $value = $callback();
            if (!is_null($value)) {
                Cache::put($key, $value, $ttl);
            } else {
                Log::error("CacheRemember. callback returned null for key: " . $key);
            }
            $lock->release();
            return $value;
        } catch (Exception $exception) {
            $lock->release();
            Log::error("Exception trying to acquire lock for key [".$key."]");
            Log::error($exception);
            throw $exception;
        }
    } else {
        try {
            // couldn't get the lock, another is executing the callback. block waiting for lock
            // or until the lock is released by the lock timeout
            $lock->block($lock_timeout + 1);
            // Lock acquired, which should mean the cache is set
            $value = Cache::get($key);
            if (is_null($value)) {
                // !!! **** my assumption about when the cache value will be available is not valid
                throw new Exception("Exception when the cache value is null for key [".$key."]");
            }
            return $value ;
        } catch (LockTimeoutException $e) {
            // Unable to acquire lock...
            Log::error("Lock Timeout Exception for key [".$key."]");
            Log::error($e);
        } finally {
            optional($lock)->release();
        }

        throw new \UnexpectedValueException("Undefined Error for key [".$key."]");
    }
}

/**
 * Remove special characters and all spaces. If the phrase has two or more words,
 * it will leave a space between each word.
 *
 * @param string $search_text
 *
 * @return string
 */
function transformQuerySearchText(string $search_text): string
{
    $formatted_search = urldecode($search_text);
    $formatted_search = preg_replace('/[+\-><\(\)~*\"@%]+/', ' ', $formatted_search);
    $formatted_search = preg_replace('!\s+!', ' ', $formatted_search);
    return trim($formatted_search);
}

function cacheRememberForever($cache_key, $callback)
{
    return Cache::rememberForever($cache_key, $callback);
}

/**
 * Get a valid formed cache key
 *
 * @param string $key
 * @param string $args
 *
 * @return string
 */
function generateCacheSafeKey(string $key, Array $args = []) : string
{
    $new_args = removeSpaceAndCntrlFromCacheParameters($args);
    $cache_string =  strtolower(array_reduce($new_args, function ($carry, $item) {
        return $carry .= ':' . $item;
    }, $key));

    $key_cache_string = base64_encode($cache_string);

    $key_string_length = strlen($key_cache_string);

    // cache key max out at 230 bytes, so we use sha512 to avoid it from maxing out
    // in lock we add 5-10 more characters so we max out at 230
    if ($key_string_length > 230) {
        // we try to avoid hash collisions using sha512 hash algorithm and additionally using the key length
        return hash("sha512", $key_cache_string).$key_string_length;
    }

    return $key_cache_string;
}

function generateCacheString($key, $args = [])
{
    $new_args = removeSpaceAndCntrlFromCacheParameters($args);
    $cache_string =  strtolower(array_reduce($new_args, function ($carry, $item) {
        return $carry .= ':' . $item;
    }, $key));

    // cache key max out at 230 bytes, so we use sha512 to avoid it from maxing out
    // in lock we add 5-10 more characters so we max out at 230
    if (strlen($cache_string) > 230) {
        $cache_string = md5($cache_string);
    }

    return $cache_string;
}

function getBackwardCompatibilityInfo($key)
{
    $bibleis_compat_keys = config('auth.compat_users.api_keys.bibleis');
    $bibleis_compat_keys = explode(',', $bibleis_compat_keys);
    $gid_compat_keys = config('auth.compat_users.api_keys.gideons');
    $gid_compat_keys = explode(',', $gid_compat_keys);
    $compat_keys_response = [
      'isBibleis' => false,
      'isGideons' => false,
    ];
    if (in_array($key, $bibleis_compat_keys)) {
        $compat_keys_response['isBibleis'] = true;
    } elseif (in_array($key, $gid_compat_keys)) {
        $compat_keys_response['isGideons'] = true;
    }
    return $compat_keys_response;
}

function isBackwardCompatible($key)
{
    $app_compat_keys = getBackwardCompatibilityInfo($key);
    return $app_compat_keys['isBibleis'] === true || $app_compat_keys['isGideons'] === true;
}

function forceBibleisGideonsPagination($key, $limit_param)
{
    // remove pagination for bibleis and gideons (temporal fix)
    $limit = $limit_param;
    $is_bibleis_gideons = null;

    if (shouldUseBibleisBackwardCompat($key)) {
        $limit = PHP_INT_MAX - 10;
        $is_bibleis_gideons = 'b-g';
    }
    return [$limit, $is_bibleis_gideons];
}

function storagePath(
    $bible,
    $fileset,
    $fileset_chapter,
    $secondary_file_name = null
) {
    switch ($fileset->set_type_code) {
        case 'audio_drama':
        case 'audio':
            $fileset_type = 'audio';
            break;
        case 'text_plain':
        case 'text_format':
            $fileset_type = 'text';
            break;
        case 'video_stream':
        case 'video':
            $fileset_type = 'video';
            break;
        case 'app':
            $fileset_type = 'app';
            break;
        default:
            $fileset_type = 'text';
            break;
    }
    return $fileset_type .
      '/' .
      ($bible ? $bible . '/' : '') .
      $fileset->id .
      '/' .
      ($secondary_file_name ?? $fileset_chapter['file_name']);
}

function formatAppVersion($app_version)
{
    $formatted_version = preg_split("/( |\-)/", $app_version)[0];
    $separated_versions = explode('.', $formatted_version);
    $major_version = isset($separated_versions[0]) ? $separated_versions[0] : 0;
    $minor_version = isset($separated_versions[1]) ? $separated_versions[1] : 0;
    $patch_version = isset($separated_versions[2]) ? $separated_versions[2] : 0;
    return [
        'major_version' => (int) $major_version . $minor_version,
        'minor_version' => (int) $patch_version
    ];
}

function logDeprecationInfo(
    $key,
    $app_name,
    $should_use_backward_compat,
    $app_version = null,
    $deprecation_version = null
) {
    // log data to be sure this deprecation method is working correctly
    $log_data = [
        'key' => $key,
        'app_name' => $app_name,
        'app_version' => $app_version,
        'deprecation_version' => $deprecation_version,
        'backward_compatibility_mode_active' => $should_use_backward_compat,
    ];
    $backward_compat_message = 'shouldUseBibleisBackwardCompat: ' . json_encode($log_data);
    Log::error($backward_compat_message);
}

function shouldUseBibleisBackwardCompat($key)
{
    // endpoints/functions using this should already be deprecated for all other users
    // different from bibleis
    $should_use_backward_compat = false;
    $app_name = '';
    $app_version = '';
    $app_compat_keys = getBackwardCompatibilityInfo($key);
    $deprecation_version = null;

    $backward_compatibility = config('settings.backward_compatibility.app_name');

    if ($app_compat_keys['isBibleis']) {
        $app_name = $backward_compatibility['bibleis'];
        $deprecation_version = config('settings.deprecate_from_version.bibleis');
    } elseif ($app_compat_keys['isGideons']) {
        $app_name = $backward_compatibility['gideons'];
        $deprecation_version = config('settings.deprecate_from_version.gideons');
    }

    if ($deprecation_version && isset($_SERVER['HTTP_USER_AGENT'])) {
        $deprecation_version = formatAppVersion($deprecation_version);
        $user_ag = $_SERVER['HTTP_USER_AGENT'];
        $has_new_user_agent = strpos($user_ag, $app_name . '/') !== false;
        // case for older bibleis/gideons apps with different user agent that we don't recognize
        if (!$has_new_user_agent) {
            // logDeprecationInfo($key, $app_name, true);
            return true;
        }
        // case for newer app veresions with updated user agent
        if ($app_name && $has_new_user_agent) {
            $app_version = explode($app_name . '/', $user_ag)[1];
            $app_version = explode(' ', $app_version)[0];
            $app_version = formatAppVersion($app_version);
            if ($app_version['major_version'] < $deprecation_version['major_version']) {
                $should_use_backward_compat = true;
            } elseif ($app_version['major_version'] === $deprecation_version['major_version']) {
                if ($app_version['minor_version'] < $deprecation_version['minor_version']) {
                    $should_use_backward_compat = true;
                }
            }
        }
    }
    // logDeprecationInfo($key, $app_name, $should_use_backward_compat, $app_version, $deprecation_version);
    return $should_use_backward_compat;
}

if (!function_exists('csvToArray')) {
    function csvToArray($csvfile)
    {
        $csv      = [];
        $rowcount = 0;
        if (($handle = fopen($csvfile, 'r')) !== false) {
            $max_line_length = defined('MAX_LINE_LENGTH') ? MAX_LINE_LENGTH : 10000;
            $header          = fgetcsv($handle, $max_line_length);
            $header_colcount = count($header);
            while (($row = fgetcsv($handle, $max_line_length)) !== false) {
                $row_colcount = count($row);
                if ($row_colcount == $header_colcount) {
                    $entry = array_combine($header, $row);
                    $csv[] = $entry;
                } else {
                    error_log('csvreader: Invalid number of columns at line ' . ($rowcount + 2) . ' (row ' . ($rowcount + 1) . "). Expected=$header_colcount Got=$row_colcount");
                    return null;
                }
                $rowcount++;
            }
            //echo "Totally $rowcount rows found\n";
            fclose($handle);
        } else {
            error_log("csvreader: Could not read CSV \"$csvfile\"");
            return null;
        }
        return $csv;
    }
}

if (!function_exists('unique_random')) {
    /**
     *
     * Generate a unique random string of characters
     *
     * @param      $table - name of the table
     * @param      $col   - name of the column that needs to be tested
     * @param int  $chars - length of the random string
     *
     * @return string
     */
    function unique_random($table, $col, $chars = 16)
    {
        $unique = false;

        // Store tested results in array to not test them again
        $tested = [];

        do {
            // Generate random string of characters
            $random = Illuminate\Support\Str::random($chars);

            // Check if it's already testing
            // If so, don't query the database again
            if (in_array($random, $tested)) {
                continue;
            }

            // Check if it is unique in the database
            $count = DB::table($table)->where($col, '=', $random)->count();

            // Store the random character in the tested array
            // To keep track which ones are already tested
            $tested[] = $random;

            // String appears to be unique
            if ($count === 0) {
                // Set unique to true to break the loop
                $unique = true;
            }

            // If unique is still false at this point
            // it will just repeat all the steps until
            // it has generated a random string of characters
        } while (!$unique);


        return $random;
    }
}

if (!function_exists('convertCsvToArrayMap')) {
    function convertCsvToArrayMap($syncFile)
    {
        $file = fopen($syncFile, 'r');
        $mapped_csv = [];
    
        while (!feof($file)) {
            $line = fgetcsv($file);
            if ($line && $line[0] && $line[1] && $line[0] !== ' ' && $line[1] !== ' ') {
                $mapped_csv[$line[0]] = $line[1];
            }
        }
        fclose($file);
        return $mapped_csv;
    }
}

if (!function_exists('getFilesetFromDamId')) {
    function getFilesetFromDamId($dam_id, $use_sync_file = false, $filesets = [])
    {
        if ($use_sync_file === true) {
            $syncFile = config('settings.bibleSyncFilePath');
            $transition_bibles = convertCsvToArrayMap($syncFile);
            
            if (array_key_exists($dam_id, $transition_bibles)) {
                $dam_id = $transition_bibles[$dam_id];
            } elseif (array_key_exists($dam_id, array_flip($transition_bibles))) {
                $dam_id = array_flip($transition_bibles)[$dam_id];
            }
        }

        $fileset = $filesets->where('id', $dam_id)->first();

        if (!$fileset) {
            $fileset = $filesets->where('id', substr($dam_id, 0, -4))->first();
        }
        if (!$fileset) {
            $fileset = $filesets->where('id', substr($dam_id, 0, -2))->first();
        }

        if (!$fileset) {
            // echo "\n Error!! Could not find FILESET_ID: " . substr($dam_id, 0, 6);
            return false;
        }
        return $fileset;
    }
}

if (!function_exists('validateV2Annotation')) {
    function validateV2Annotation($annotation, $filesets, $books, $v4_users, $v4_annotations)
    {
        if (isset($v4_annotations[$annotation->id])) {
            // echo "\n Error!! Annotation already inserted: " . $annotation->id;
            return false;
        }

        if (!isset($v4_users[$annotation->user_id])) {
            // echo "\n Error!! Could not find USER_ID: " . $annotation->user_id;
            return false;
        }

        if (!isset($books[$annotation->book_id])) {
            // echo "\n Error!! Could not find BOOK_ID: " . $annotation->book_id;
            return false;
        }

        if (!isset($filesets[$annotation->dam_id])) {
            // echo "\n Error!! Could not find FILESET_ID: " . $annotation->dam_id;
            return false;
        }

        $fileset = $filesets[$annotation->dam_id];

        if ($fileset->bible->first()) {
            if (!isset($fileset->bible->first()->id)) {
                // echo "\n Error!! Could not find BIBLE_ID";
                return false;
            }
        } else {
            // echo "\n Error!! Could not find BIBLE_ID";
            return false;
        }

        return true;
    }
}

if (!function_exists('validateLiveBibleIsAnnotation')) {
    function validateLiveBibleIsAnnotation($annotation, $v4_users, $bibles, $annotation_exists)
    {
        if ($annotation_exists) {
            return false;
        }

        if (!in_array($annotation['user_id'], $v4_users)) {
            echo "\n Error!! Could not find USER_ID: " . $annotation['user_id'] . ' (wont insert this annotation)';
            return false;
        }


        if (!in_array($annotation['bible_id'], $bibles)) {
            echo "\n Error!! Could not find BIBLE_ID". $annotation['bible_id'] . ' (wont insert this annotation)';
            return false;
        }

        return true;
    }
}

if (!function_exists('arrayToCommaSeparatedValues')) {
    function arrayToCommaSeparatedValues($array)
    {
        return  "'" . implode("','", $array) . "'";
    }
}


if (!function_exists('getTestamentString')) {
    function getTestamentString($id)
    {
        $testament_pivot_word = 6;

        $substring = '';
        if (strlen($id) > $testament_pivot_word) {
            $substring = $id[$testament_pivot_word];
        }
        switch ($substring) {
            case 'O':
                $testament = ['OT', 'C'];
                break;

            case 'N':
                $testament = ['NT', 'C'];
                break;

            case 'P':
                $testament = ['NTOTP', 'NTP', 'NTPOTP', 'OTNTP', 'OTP', 'P'];
                break;
            default:
                $testament = [];
        }
        return $testament;
    }
}

/**
 * Remove space and cntrl for each value that belongs to cache params array
 *
 * @param array $cache_params
 *
 * @return array
 */
if (!function_exists('removeSpaceAndCntrlFromCacheParameters')) {
    function removeSpaceAndCntrlFromCacheParameters(array $cache_params): array
    {
        return array_map(
            function ($param) {
                return removeSpaceAndCntrlParameter($param);
            },
            $cache_params
        );
    }
}

/**
 * Remove space and cntrl for a value
 *
 * @param string|int|Array|bool|null $param
 *
 * @return string|int|Array|bool|null
 */
if (!function_exists('removeSpaceAndCntrlParameter')) {
    function removeSpaceAndCntrlParameter(string|int|Array|bool|null $param): string|int|Array|bool|null
    {
        return is_string($param)
            ? preg_replace('/[[:cntrl:]]/', '', str_replace(' ', '', $param))
            : $param;
    }
}

/**
 * Get the full Youtube URL given segment and playlist values
 *
 * @param string $file_tag
 * @param string|null $playlist_id
 *
 * @return string
 */
if (!function_exists('getYoutubePlaylistURL')) {
    function getYoutubePlaylistURL(string $file_tag, ?string $playlist_id): string
    {
        return $playlist_id
            ? sprintf('%swatch?v=%s&list=%s', config('settings.youtube_url'), $file_tag, $playlist_id)
            : sprintf('%swatch?v=%s', config('settings.youtube_url'), $file_tag);
    }
}

/**
 * Get Download AccessGroup list
 *
 * @return array
 */
if (!function_exists('getDownloadAccessGroupList')) {
    function getDownloadAccessGroupList(): array
    {
        return array_map('intval', explode(',', config('settings.download_access_group_list')));
    }
}

/**
 * Retrieve the user object from the request and verify if it has been initialized or set.
 *
 * @return bool
 */
if (!function_exists('isUserLoggedIn')) {
    function isUserLoggedIn() : bool
    {
        $user = request()->user();
        return !empty($user) && optional($user)->id > 0;
    }
}
