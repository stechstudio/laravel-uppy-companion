<?php

namespace STS\LaravelUppyCompanion;

use Aws\S3\S3ClientInterface;

class LaravelUppyCompanion
{
    private static \Closure $clientCallback;
    private static S3ClientInterface $client;

    private static \Closure $bucketCallback;
    private static string $bucket;

    public static function client(\Closure|S3ClientInterface $client)
    {
        if ($client instanceof \Closure) {
            static::$clientCallback = $client;
        } elseif ($client instanceof S3ClientInterface) {
            static::$client = $client;
        }
    }

    public static function bucket(\Closure|string $bucket)
    {
        if ($bucket instanceof \Closure) {
            static::$bucketCallback = $bucket;
        } elseif (is_string($bucket)) {
            static::$bucket = $bucket;
        }
    }

    public static function getClient(): S3ClientInterface
    {
        if (empty(static::$client)) {
            if (empty(static::$clientCallback)) {
                throw new \Exception('No client or client callback defined');
            }

            static::$client = call_user_func(static::$clientCallback);
        }

        return static::$client;
    }

    public static function getBucket(): string
    {
        if (empty(static::$bucket)) {
            if (empty(static::$bucketCallback)) {
                throw new \Exception('No bucket or bucket callback defined');
            }

            static::$bucket = call_user_func(static::$bucketCallback);
        }

        return static::$bucket;
    }
}
