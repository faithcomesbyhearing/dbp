<?php

namespace App\Services\Bibles;

use Carbon\Carbon;
use Closure;

use function cacheRemember;

class ShowVerifyContentResponseCache
{
    public static function remember(array $cache_params, Carbon $ttl, Closure $callback) : array
    {
        return cacheRemember('bibles_show_verify_content_response', $cache_params, $ttl, $callback);
    }
}
