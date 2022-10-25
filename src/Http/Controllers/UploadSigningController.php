<?php

namespace STS\LaravelUppyCompanion\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use STS\LaravelUppyCompanion\LaravelUppyCompanion;

class UploadSigningController extends Controller
{
    public static function routes()
    {
        Route::group(['prefix' => 'sign/s3/multipart'], function () {
            Route::post('/', ['uses' => '\\' . self::class . '@createMultipartUpload']);
            Route::get('/{uploadId}', ['uses' => '\\' . self::class . '@getUploadedParts']);
            Route::delete('/{uploadId}', ['uses' => '\\' . self::class . '@abortMultipartUpload']);
            Route::post('/{uploadId}/complete', ['uses' => '\\' . self::class . '@completeMultipartUpload']);
            Route::get('/{uploadId}/{partNumber}', ['uses' => '\\' . self::class . '@signPartUpload']);
        });
    }

    public function __construct(public LaravelUppyCompanion $companion)
    {
    }

    /**
     * @param Request $request
     * @param SigningClientProvider $signer
     * @return \Illuminate\Http\JsonResponse
     */
    public function createMultipartUpload(Request $request)
    {
        $split = explode('.', $request->filename);
        $result = $this->companion->getClient()->createMultipartUpload([
            'Bucket' => $this->companion->getBucket(),
            'Key' => count($split) > 1 ? Str::uuid() . '.' . $split[count($split) - 1] : Str::uuid(),
            'ACL' => 'private',
            'ContentType' => $request->type,
            'Metadata' => $request->metadata,
            'Expires' => '+24 hours',
        ]);

        return response()->json(['key' => $result['Key'], 'uploadId' => $result['UploadId']]);
    }

    /**
     * @param Request $request
     * @param SigningClientProvider $signer
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUploadedParts(Request $request)
    {
        $parts = [];
        $next = 0;

        do {
            $result = $this->companion->getClient()->listParts([
                'Bucket' => $this->companion->getBucket(),
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
     * @param SigningClientProvider $signer
     * @return \Illuminate\Http\JsonResponse
     */
    public function signPartUpload(Request $request)
    {
        $cmd = $this->companion->getClient()->getCommand('uploadPart', [
            'Bucket' => $this->companion->getBucket(),
            'Key' => $request->key,
            'UploadId' => $request->uploadId,
            'PartNumber' => $request->partNumber,
            'Body' => '',
            'Expires' => '+24 hours',
        ]);

        $signedRequest = $this->companion->getClient()->createPresignedRequest($cmd, '+24 hours');

        return response()->json(['url' => (string)$signedRequest->getUri()]);
    }

    /**
     * @param Request $request
     * @param SigningClientProvider $signer
     * @return \Illuminate\Http\JsonResponse
     */
    public function abortMultipartUpload(Request $request)
    {
        $this->companion->getClient()->abortMultipartUpload([
            'Bucket' => $this->companion->getBucket(),
            'Key' => $request->key,
            'UploadId' => $request->uploadId,
        ]);

        return response()->json([]);
    }

    /**
     * @param Request $request
     * @param SigningClientProvider $signer
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeMultipartUpload(Request $request)
    {
        $result = $this->companion->getClient()->completeMultipartUpload([
            'Bucket' => $this->companion->getBucket(),
            'Key' => $request->key,
            'UploadId' => $request->uploadId,
            'MultipartUpload' => ['Parts' => $request->parts],
        ]);

        return response()->json(['location' => $result['Location']]);
    }
}
