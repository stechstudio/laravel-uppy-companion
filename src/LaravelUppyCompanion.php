<?php

namespace STS\LaravelUppyCompanion;

use Aws\S3\S3ClientInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class LaravelUppyCompanion
{
    private \Closure $clientCallback;
    private S3ClientInterface $client;

    private \Closure $bucketCallback;
    private string $bucket;

    private ?\Closure $keyCallback;

    public function __construct(\Closure|string|null $bucket = null, \Closure|S3ClientInterface|null $client = null, ?\Closure $key = null)
    {
        if ($bucket && $client) {
            $this->configure($bucket, $client, $key);
        }
    }

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

    public static function routes(?self $companion = null)
    {
        $companion ??= app(LaravelUppyCompanion::class);

        Route::group(['prefix' => 'sign/s3/multipart'], function () use ($companion) {
            Route::post('/', fn (Request $request) => self::createMultipartUpload($request, $companion));
            Route::get('/{uploadId}', fn (Request $request) => self::getUploadedParts($request, $companion));
            Route::delete('/{uploadId}', fn (Request $request) => self::abortMultipartUpload($request, $companion));
            Route::post('/{uploadId}/complete', fn (Request $request) => self::completeMultipartUpload($request, $companion));
            Route::get('/{uploadId}/{partNumber}', fn (Request $request) => self::signPartUpload($request, $companion));
        });
    }

    /**
     * @param Request $request
     * @param LaravelUppyCompanion $companion
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function createMultipartUpload(Request $request, LaravelUppyCompanion $companion)
    {
        $result = $companion->getClient()->createMultipartUpload([
            'Bucket' => $companion->getBucket(),
            'Key' => $companion->getKey($request->filename),
            'ACL' => 'private',
            'ContentType' => $request->type,
            'Metadata' => $request->metadata,
            'Expires' => '+24 hours',
        ]);

        return response()->json(['key' => $result['Key'], 'uploadId' => $result['UploadId']]);
    }

    /**
     * @param Request $request
     * @param LaravelUppyCompanion $companion
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function getUploadedParts(Request $request, LaravelUppyCompanion $companion)
    {
        $parts = [];
        $next = 0;

        do {
            $result = $companion->getClient()->listParts([
                'Bucket' => $companion->getBucket(),
                'Key' => $request->key,
                'UploadId' => $request->uploadId,
                'PartNumberMarker' => $next,
            ]);

            $parts = array_merge($parts, $result['Parts']);
            $next = $result['NextPartNumberMarker'];
        } while ($result['IsTruncated']);

        return response()->json($parts);
    }

    /**
     * @param Request $request
     * @param LaravelUppyCompanion $companion
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function signPartUpload(Request $request, LaravelUppyCompanion $companion)
    {
        $cmd = $companion->getClient()->getCommand('uploadPart', [
            'Bucket' => $companion->getBucket(),
            'Key' => $request->key,
            'UploadId' => $request->uploadId,
            'PartNumber' => $request->partNumber,
            'Body' => '',
            'Expires' => '+24 hours',
        ]);

        $signedRequest = $companion->getClient()->createPresignedRequest($cmd, '+24 hours');

        return response()->json(['url' => (string)$signedRequest->getUri()]);
    }

    /**
     * @param Request $request
     * @param LaravelUppyCompanion $companion
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function abortMultipartUpload(Request $request, LaravelUppyCompanion $companion)
    {
        $companion->getClient()->abortMultipartUpload([
            'Bucket' => $companion->getBucket(),
            'Key' => $request->key,
            'UploadId' => $request->uploadId,
        ]);

        return response()->json([]);
    }

    /**
     * @param Request $request
     * @param LaravelUppyCompanion $companion
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function completeMultipartUpload(Request $request, LaravelUppyCompanion $companion)
    {
        $result = $companion->getClient()->completeMultipartUpload([
            'Bucket' => $companion->getBucket(),
            'Key' => $request->key,
            'UploadId' => $request->uploadId,
            'MultipartUpload' => ['Parts' => $request->parts],
        ]);

        return response()->json(['location' => $result['Location']]);
    }
}
