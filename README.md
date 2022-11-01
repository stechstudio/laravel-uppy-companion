# Laravel Uppy Companion

This package offers a handful of routes that provide the [Uppy JS uploader](https://github.com/transloadit/uppy) with the endpoints it expects to sign and send multipart uploads directly to an S3 bucket.

## Example Usage
In your `routes.php` file, simply call the static method on the provided controller to register the routes:
```php
Route::group(['prefix' => 'your/upload/prefix'], function () {
    \STS\LaravelUppyCompanion\Http\Controllers\UploadSigningController::routes();
});
```

Then provide the `AwsS3Multipart` or `AwsS3` Uppy driver with `/sign` as its `companionUrl`:
```js
uppy.use(Uppy.AwsS3Multipart, {
    companionUrl: 'your/upload/prefix/sign',
});
```

## Service Provider Configuration
Because [FileRocket](https://github.com/stechstudio/FileRocket) requires information about the current user and/or the active organization before determining the S3Client and bucket to send uploads to, the `LaravelUppyCompanion` is configured in your `AppServiceProvider`, and will accept either `string` values or callback functions. If a callback function is provided, it won't be called until necessary, and it will only be called once per request.

Simple values known at `boot()`:
```php
LaravelUppyCompanion::client(new S3Client(config('aws')));
LaravelUppyCompanion::bucket('a-known-static-bucket');
```

User isn't available in `AppServiceProvider::boot()`, but will return the correct value by the time the callback is called.
```php
LaravelUppyCompanion::client(fn() => user()->s3client());
LaravelUppyCompanion::bucket(fn() => user()->s3_bucket);
```
