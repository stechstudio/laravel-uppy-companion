# Laravel Uppy Companion

This package offers a handful of routes that provide the [Uppy JS uploader](https://github.com/transloadit/uppy) with the endpoints it expects to sign and send multipart uploads directly to an S3 bucket.

## Example Usage
In your `routes.php` file, simply call the static method on the provided controller to register the routes:

```php
Route::group(['prefix' => 'your/upload/prefix'], function () {
    \STS\LaravelUppyCompanion\LaravelUppyCompanion::routes();
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
app(\STS\LaravelUppyCompanion\LaravelUppyCompanion::class)->configure(
    new S3Client(config('aws')),
    'a-known-static-bucket'
);
```

User isn't available in `AppServiceProvider::boot()`, but will return the correct value by the time the callback is called.
```php
app(\STS\LaravelUppyCompanion\LaravelUppyCompanion::class)->configure(
    fn() => user()->s3client(),
    fn() => user()->s3_bucket
);
```

## Advanced
You may create and configure an unlimited number of companions. This is useful if you need your app has multiple buckets
which should be used in different contexts.

In your `AppServiceProvider::register()` method, create and configure new companion singletons.
```php
public function register()
{
    $this->app->singleton('companion.public-media', function ($app) {
        $companion = new \STS\LaravelUppyCompanion\LaravelUppyCompanion();
        $companion->configure(new S3Client(config('aws')), 'my-public-media');
        return $companion;
    });

    $this->app->singleton('companion.archive', function ($app) {
        $companion = new \STS\LaravelUppyCompanion\LaravelUppyCompanion();
        $companion->configure(new S3Client(config('archive-storage')), 'my-archive-bucket');
        return $companion;
    });
}
```

Then, in your `routes.php` file, call the static method on the companion you wish to use:
```php
Route::group(['prefix' => 'public/media/upload/prefix'], function () {
    \STS\LaravelUppyCompanion\LaravelUppyCompanion::routes(app('companion.public-media'));
});

Route::group(['prefix' => 'private/archive/upload/prefix'], function () {
    \STS\LaravelUppyCompanion\LaravelUppyCompanion::routes(app('companion.archive'));
});
```
