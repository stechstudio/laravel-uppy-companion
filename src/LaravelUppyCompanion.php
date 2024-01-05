<?php

namespace STS\LaravelUppyCompanion;

use Aws\S3\S3ClientInterface;
use Illuminate\Support\Str;

class LaravelUppyCompanion
{
    private \Closure $clientCallback;
    private S3ClientInterface $client;

    private \Closure $bucketCallback;
    private string $bucket;

    private ?\Closure $keyCallback;

    public function __construct()
    {}

    public function configure(\Closure|string $bucket, \Closure|S3ClientInterface $client, ?\Closure $key = null)
    {
        if ($bucket instanceof \Closure) {
            $this->bucketCallback = $bucket;
        } else {
            $this->bucket = $bucket;
        }

        if ($client instanceof \Closure) {
            $this->clientCallback = $client;
        } else {
            $this->client = $client;
        }

        $this->keyCallback = $key ?? fn ($filename) => static::getUUID($filename);
    }

    public function getClient(): S3ClientInterface
    {
        if (empty($this->client)) {
            if (empty($this->clientCallback)) {
                throw new \Exception('No client or client callback defined');
            }

            $this->client = call_user_func($this->clientCallback);
        }

        return $this->client;
    }

    public function getBucket(): string
    {
        if (empty($this->bucket)) {
            if (empty($this->bucketCallback)) {
                throw new \Exception('No bucket or bucket callback defined');
            }

            $this->bucket = call_user_func($this->bucketCallback);
        }

        return $this->bucket;
    }

    /**
     * Gets a key for the given filename according to the key callback.
     *
     * @param string $filename
     * @return string
     */
    public function getKey(string $filename): string
    {
        return call_user_func($this->keyCallback, $filename);
    }

    /**
     * Return a UUID or a UUID with the extension of the filename.
     *
     * @param string $filename
     * @return string
     */
    public static function getUUID(string $filename): string
    {
        $split = explode('.', $filename);
        return count($split) > 1 ? Str::uuid() . '.' . $split[count($split) - 1] : Str::uuid();
    }
}
