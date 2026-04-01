<?php

use Aws\CommandInterface;
use Aws\S3\S3ClientInterface;
use Illuminate\Support\Facades\Route;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use STS\LaravelUppyCompanion\LaravelUppyCompanion;

function setupCompanionRoutes(?array $extraParams = null): \Mockery\MockInterface
{
    $s3 = Mockery::mock(S3ClientInterface::class);
    $companion = app(LaravelUppyCompanion::class);
    $companion->configure('test-bucket', $s3, null, $extraParams);

    Route::group([], function () use ($companion) {
        LaravelUppyCompanion::routes($companion);
    });

    return $s3;
}

function mockPresignedRequest(string $url = 'https://s3.amazonaws.com/test-bucket/test-key'): RequestInterface
{
    $uri = Mockery::mock(UriInterface::class);
    $uri->shouldReceive('__toString')->andReturn($url);

    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('getMethod')->andReturn('PUT');
    $request->shouldReceive('getUri')->andReturn($uri);

    return $request;
}

it('registers all six routes with correct methods', function () {
    setupCompanionRoutes();

    $routes = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->methods()[0] . ' ' . $route->uri())
        ->toArray();

    expect($routes)->toContain('GET sign/s3/params');
    expect($routes)->toContain('POST sign/s3/multipart');
    expect($routes)->toContain('GET sign/s3/multipart/{uploadId}');
    expect($routes)->toContain('DELETE sign/s3/multipart/{uploadId}');
    expect($routes)->toContain('POST sign/s3/multipart/{uploadId}/complete');
    expect($routes)->toContain('GET sign/s3/multipart/{uploadId}/{partNumber}');
});

it('signs a single-part upload', function () {
    $s3 = setupCompanionRoutes();
    $cmd = Mockery::mock(CommandInterface::class);

    $s3->shouldReceive('getCommand')
        ->with('putObject', Mockery::type('array'))
        ->once()
        ->andReturn($cmd);

    $s3->shouldReceive('createPresignedRequest')
        ->with($cmd, '+24 hours')
        ->once()
        ->andReturn(mockPresignedRequest());

    $response = $this->get('/sign/s3/params?filename=test.jpg&type=image/jpeg');

    $response->assertOk();
    $response->assertJsonStructure(['method', 'url']);
    $response->assertJson(['method' => 'PUT']);
});

it('creates a multipart upload', function () {
    $s3 = setupCompanionRoutes();

    $s3->shouldReceive('createMultipartUpload')
        ->with(Mockery::on(fn (array $args) =>
            $args['Bucket'] === 'test-bucket'
            && $args['ACL'] === 'private'
            && $args['ContentType'] === 'image/jpeg'
            && isset($args['Key'])
        ))
        ->once()
        ->andReturn(['Key' => 'some-key', 'UploadId' => 'upload-123']);

    $response = $this->postJson('/sign/s3/multipart', [
        'filename' => 'test.jpg',
        'type' => 'image/jpeg',
        'metadata' => [],
    ]);

    $response->assertOk();
    $response->assertJson(['key' => 'some-key', 'uploadId' => 'upload-123']);
});

it('lists uploaded parts', function () {
    $s3 = setupCompanionRoutes();

    $s3->shouldReceive('listParts')
        ->with(Mockery::type('array'))
        ->once()
        ->andReturn([
            'Parts' => [['PartNumber' => 1, 'ETag' => '"abc123"']],
            'IsTruncated' => false,
            'NextPartNumberMarker' => 0,
        ]);

    $response = $this->get('/sign/s3/multipart/upload-123?key=some-key&uploadId=upload-123');

    $response->assertOk();
    $response->assertJson([['PartNumber' => 1, 'ETag' => '"abc123"']]);
});

it('signs a part upload', function () {
    $s3 = setupCompanionRoutes();
    $cmd = Mockery::mock(CommandInterface::class);

    $s3->shouldReceive('getCommand')
        ->with('uploadPart', Mockery::type('array'))
        ->once()
        ->andReturn($cmd);

    $s3->shouldReceive('createPresignedRequest')
        ->with($cmd, '+24 hours')
        ->once()
        ->andReturn(mockPresignedRequest());

    $response = $this->get('/sign/s3/multipart/upload-123/1?key=some-key&uploadId=upload-123&partNumber=1');

    $response->assertOk();
    $response->assertJsonStructure(['url']);
});

it('aborts a multipart upload', function () {
    $s3 = setupCompanionRoutes();

    $s3->shouldReceive('abortMultipartUpload')
        ->with(Mockery::type('array'))
        ->once();

    $response = $this->delete('/sign/s3/multipart/upload-123?key=some-key&uploadId=upload-123');

    $response->assertOk();
});

it('completes a multipart upload', function () {
    $s3 = setupCompanionRoutes();

    $parts = [['PartNumber' => 1, 'ETag' => '"abc123"']];

    $s3->shouldReceive('completeMultipartUpload')
        ->with(Mockery::on(fn (array $args) =>
            $args['Bucket'] === 'test-bucket'
            && $args['Key'] === 'some-key'
            && $args['UploadId'] === 'upload-123'
            && $args['MultipartUpload'] === ['Parts' => $parts]
        ))
        ->once()
        ->andReturn(['Location' => 'https://s3.amazonaws.com/test-bucket/some-key']);

    $response = $this->postJson('/sign/s3/multipart/upload-123/complete', [
        'key' => 'some-key',
        'uploadId' => 'upload-123',
        'parts' => $parts,
    ]);

    $response->assertOk();
    $response->assertJson(['location' => 'https://s3.amazonaws.com/test-bucket/some-key']);
});

it('merges extra params into single-part upload', function () {
    $s3 = setupCompanionRoutes(['StorageClass' => 'STANDARD_IA']);
    $cmd = Mockery::mock(CommandInterface::class);

    $s3->shouldReceive('getCommand')
        ->with('putObject', Mockery::on(fn (array $args) => $args['StorageClass'] === 'STANDARD_IA'))
        ->once()
        ->andReturn($cmd);

    $s3->shouldReceive('createPresignedRequest')
        ->with($cmd, '+24 hours')
        ->once()
        ->andReturn(mockPresignedRequest());

    $response = $this->get('/sign/s3/params?filename=test.jpg&type=image/jpeg');

    $response->assertOk();
});

it('merges extra params into multipart upload', function () {
    $s3 = setupCompanionRoutes(['StorageClass' => 'STANDARD_IA']);

    $s3->shouldReceive('createMultipartUpload')
        ->with(Mockery::on(fn (array $args) => $args['StorageClass'] === 'STANDARD_IA'))
        ->once()
        ->andReturn(['Key' => 'some-key', 'UploadId' => 'upload-123']);

    $response = $this->postJson('/sign/s3/multipart', [
        'filename' => 'test.jpg',
        'type' => 'image/jpeg',
        'metadata' => [],
    ]);

    $response->assertOk();
});

it('paginates uploaded parts when response is truncated', function () {
    $s3 = setupCompanionRoutes();

    $s3->shouldReceive('listParts')
        ->with(Mockery::on(fn (array $args) => $args['PartNumberMarker'] === 0))
        ->once()
        ->andReturn([
            'Parts' => [['PartNumber' => 1, 'ETag' => '"aaa"'], ['PartNumber' => 2, 'ETag' => '"bbb"']],
            'IsTruncated' => true,
            'NextPartNumberMarker' => 2,
        ]);

    $s3->shouldReceive('listParts')
        ->with(Mockery::on(fn (array $args) => $args['PartNumberMarker'] === 2))
        ->once()
        ->andReturn([
            'Parts' => [['PartNumber' => 3, 'ETag' => '"ccc"']],
            'IsTruncated' => false,
            'NextPartNumberMarker' => 3,
        ]);

    $response = $this->get('/sign/s3/multipart/upload-123?key=some-key&uploadId=upload-123');

    $response->assertOk();
    $response->assertJson([
        ['PartNumber' => 1, 'ETag' => '"aaa"'],
        ['PartNumber' => 2, 'ETag' => '"bbb"'],
        ['PartNumber' => 3, 'ETag' => '"ccc"'],
    ]);
});

it('resolves companion from container when routes called with no argument', function () {
    $s3 = Mockery::mock(\Aws\S3\S3ClientInterface::class);
    $companion = app(LaravelUppyCompanion::class);
    $companion->configure('test-bucket', $s3);

    Route::group([], function () {
        LaravelUppyCompanion::routes();
    });

    $cmd = Mockery::mock(CommandInterface::class);
    $s3->shouldReceive('getCommand')->with('putObject', Mockery::type('array'))->once()->andReturn($cmd);
    $s3->shouldReceive('createPresignedRequest')->with($cmd, '+24 hours')->once()->andReturn(mockPresignedRequest());

    $response = $this->get('/sign/s3/params?filename=test.jpg&type=image/jpeg');

    $response->assertOk();
    $response->assertJsonStructure(['method', 'url']);
});
