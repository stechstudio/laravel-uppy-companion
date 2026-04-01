<?php

use Aws\S3\S3ClientInterface;
use STS\LaravelUppyCompanion\LaravelUppyCompanion;

it('accepts string bucket and client via constructor', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion('my-bucket', $client);

    expect($companion->getBucket())->toBe('my-bucket');
    expect($companion->getClient())->toBe($client);
});

it('accepts closure bucket and closure client via constructor', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion(
        fn () => 'closure-bucket',
        fn () => $client,
    );

    expect($companion->getBucket())->toBe('closure-bucket');
    expect($companion->getClient())->toBe($client);
});

it('does not call configure when constructed with null args', function () {
    $companion = new LaravelUppyCompanion();

    expect(fn () => $companion->getBucket())->toThrow(Exception::class);
});

it('configures with a string bucket', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion();
    $companion->configure('configured-bucket', $client);

    expect($companion->getBucket())->toBe('configured-bucket');
});

it('configures with a closure bucket', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion();
    $companion->configure(fn () => 'lazy-bucket', $client);

    expect($companion->getBucket())->toBe('lazy-bucket');
});

it('throws when getClient is called without configuration', function () {
    $companion = new LaravelUppyCompanion();

    expect(fn () => $companion->getClient())->toThrow(Exception::class, 'No client or client callback defined');
});

it('throws when getBucket is called without configuration', function () {
    $companion = new LaravelUppyCompanion();

    expect(fn () => $companion->getBucket())->toThrow(Exception::class, 'No bucket or bucket callback defined');
});

it('caches the resolved client from callback', function () {
    $callCount = 0;
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion('bucket', function () use ($client, &$callCount) {
        $callCount++;
        return $client;
    });

    $companion->getClient();
    $companion->getClient();

    expect($callCount)->toBe(1);
});

it('caches the resolved bucket from callback', function () {
    $callCount = 0;
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion(function () use (&$callCount) {
        $callCount++;
        return 'resolved-bucket';
    }, $client);

    $companion->getBucket();
    $companion->getBucket();

    expect($callCount)->toBe(1);
});

it('uses a custom key callback', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion('bucket', $client, fn ($f) => 'uploads/' . $f);

    expect($companion->getKey('photo.jpg'))->toBe('uploads/photo.jpg');
});

it('uses UUID key callback by default', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion('bucket', $client);

    $key = $companion->getKey('photo.jpg');

    expect($key)->toMatch('/^[0-9a-f\-]{36}\.jpg$/');
});

it('generates UUID with file extension', function () {
    $uuid = LaravelUppyCompanion::getUUID('photo.jpg');

    expect($uuid)->toMatch('/^[0-9a-f\-]{36}\.jpg$/');
});

it('generates UUID without extension for extensionless files', function () {
    $uuid = LaravelUppyCompanion::getUUID('README');

    expect($uuid)->toMatch('/^[0-9a-f\-]{36}$/');
});

it('uses last extension for files with multiple dots', function () {
    $uuid = LaravelUppyCompanion::getUUID('archive.tar.gz');

    expect($uuid)->toMatch('/^[0-9a-f\-]{36}\.gz$/');
});

it('returns empty array when extra params are null', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion('bucket', $client);

    expect($companion->getExtraParams())->toBe([]);
});

it('returns static extra params array', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion('bucket', $client, null, ['StorageClass' => 'STANDARD_IA']);

    expect($companion->getExtraParams())->toBe(['StorageClass' => 'STANDARD_IA']);
});

it('resolves extra params from closure', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $companion = new LaravelUppyCompanion('bucket', $client, null, fn () => ['ACL' => 'private']);

    expect($companion->getExtraParams())->toBe(['ACL' => 'private']);
});
