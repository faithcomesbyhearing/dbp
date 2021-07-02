<?php

use Illuminate\Support\Facades\Cache;

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
        abort(422, "You need to provide the missing parameter '$paramName'. Please append it to the url or the request Header.");
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

function cacheRemember($cache_key, $cache_args = [], $duration, $callback)
{
    $cache_string = generateCacheString($cache_key, $cache_args);
    return Cache::remember($cache_string, $duration, $callback);
}

function cacheRememberForever($cache_key, $callback)
{
    return Cache::rememberForever($cache_key, $callback);
}

function apiLogs($request, $status_code, $s3_string = false, $ip_address = null)
{
    $log_string = time() . '∞' . config('app.server_name') . '∞' . $status_code . '∞' . $request->path() . '∞';
    $log_string .= '"' . $request->header('User-Agent') . '"' . '∞';
    foreach ($_GET as $header => $value) {
        $log_string .= ($value !== '') ? $header . '=' . $value . '|' : $header . '|';
    }
    $log_string = rtrim($log_string, '|');
    $log_string .= '∞' . $ip_address . '∞';
    if ($s3_string) {
        $log_string .= $s3_string;
    }

    if (config('app.env') !== 'local') {
        App\Jobs\SendApiLogs::dispatch($log_string);
    }
}

function generateCacheString($key, $args = [])
{
    return strtolower(array_reduce($args, function ($carry, $item) {
        return $carry .= ':' . $item;
    }, $key));
}

function isBibleisOrGideon($key)
{
    $compat_api_keys = config('auth.compat_users.api_keys');
    $compat_api_keys = explode(',', $compat_api_keys);    
    if (in_array($key, $compat_api_keys)) {
        return true;
    }
    return false;
}

function forceBibleisGideonsPagination($key, $limit_param)
{
    // remove pagination for bibleis and gideons (temporal fix)
    $limit = min($limit_param, 50);
    $is_bibleis_gideons = null;

    if (shouldUseBibleisBackwardCompat($key)) {
        $limit = PHP_INT_MAX;
        $is_bibleis_gideons = 'bibleis-gideons';
    } 
    return [$limit, $is_bibleis_gideons];
}

function storagePath(
  $bible, 
  $fileset, 
  $fileset_chapter, 
  $secondary_file_name = null
)
{
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

function shouldUseBibleisBackwardCompat($key)
{
    // endpoints/functions using this should already be deprecated for all other users
    // different from bibleis
    $should_use_backward_compat = false;
    if (isBibleisOrGideon($key)) {
        $deprecation_version = config('settings.bibleis_deprecate_from_version.ios');
        $user_ag = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_ag, 'BibleIs/') !== false) {
            $app_version = explode("BibleIs/", $user_ag)[1];
            $app_version = explode(" ", $app_version)[0];
            $formatted_version = preg_split("/( |\-)/", $app_version)[0];
            if ($formatted_version < $deprecation_version) {
                $should_use_backward_compat = true;
            }
        }  
    }
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
    function convertCsvToArrayMap($syncFile) {
        $file = fopen($syncFile, 'r');
        $mapped_csv = [];
    
        while (!feof($file)) {
            $line = fgetcsv($file);
            if ($line && $line[0] && $line[1] && $line[0] !== " " && $line[1] !== " ") {
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
            } else if (array_key_exists($dam_id, array_flip($transition_bibles))) {
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

if (!function_exists('formatFilesetMeta')) {
    function formatFilesetMeta($fileset) {
        if (isset($fileset->meta)) {
            foreach ($fileset->meta as $metadata) {
                if (isset($metadata['name'], $metadata['description'])) {
                    $fileset[$metadata['name']] = $metadata['description'];
                }
            }
            unset($fileset->meta);
        }
        return $fileset;
    }
}
