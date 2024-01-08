<?php

namespace STS\LaravelUppyCompanion\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use STS\LaravelUppyCompanion\LaravelUppyCompanion;

class UploadSigningController extends Controller
{
    public static function routes(?LaravelUppyCompanion $companion = null)
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
