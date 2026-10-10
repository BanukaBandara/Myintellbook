<?php

namespace App\Support;

use Throwable;

/**
 * Decides which exception messages are safe to show to API clients.
 *
 * Application code throws plain \Exception / \RuntimeException with messages written for users
 * (e.g. "Profile not found. Complete your basic profile before uploading a photo."). Everything
 * else (database errors with SQL and table names, PHP errors, framework exceptions such as
 * "No query results for model [App\Models\X]") is internal and must only reach the logs.
 * Matching the exact class (not subclasses) keeps those internal exceptions out.
 */
class SafeError
{
    public const GENERIC = 'Something went wrong. Please try again.';

    public static function message(Throwable $e, string $fallback = self::GENERIC): string
    {
        $isAppMessage = in_array(get_class($e), [\Exception::class, \RuntimeException::class], true);

        if (!$isAppMessage || trim($e->getMessage()) === '') {
            return $fallback;
        }

        return $e->getMessage();
    }
}
