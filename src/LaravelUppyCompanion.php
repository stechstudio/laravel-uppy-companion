<?php

namespace STS\LaravelUppyCompanion;

use Aws\S3\S3ClientInterface;

class LaravelUppyCompanion
{
    private \Closure $clientCallback;
    private S3ClientInterface $client;

    private \Closure $bucketCallback;
    private string $bucket;

    public function __construct()
    {}

    public function configure(\Closure|string $bucket, \Closure|S3ClientInterface $client)
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
}
