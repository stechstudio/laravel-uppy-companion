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
App::make(\STS\LaravelUppyCompanion\LaravelUppyCompanion::class)->configure(
    'a-known-static-bucket',
    new S3Client(config('aws'))
);
```

User isn't available in `AppServiceProvider::boot()`, but will return the correct value by the time the callback is called.
```php
App::make(\STS\LaravelUppyCompanion\LaravelUppyCompanion::class)->configure(
    fn() => user()->s3_bucket,
    fn() => user()->s3client()
);
```

## Advanced
You may create and configure an unlimited number of companions. This is useful if you need your app has multiple buckets
which should be used in different contexts.

In your `AppServiceProvider::register()` method, create and configure new companion singletons.
```php
public function register()
{
    App::singleton('companion.public-media', function ($app) {
        return new \STS\LaravelUppyCompanion\LaravelUppyCompanion(
            'my-public-media',
            new S3Client(config('aws'))
        );
    });

    App::singleton('companion.archive', function ($app) {
        return new \STS\LaravelUppyCompanion\LaravelUppyCompanion(
            'my-archive-bucket',
            new S3Client(config('archive-storage'))
        );
    });
}
```

Then, in your `routes.php` file, call the static method on the companion you wish to use:
```php
Route::group(['prefix' => 'public/media/upload/prefix'], function () {
    \STS\LaravelUppyCompanion\LaravelUppyCompanion::routes(App::make('companion.public-media'));
});

Route::group(['prefix' => 'private/archive/upload/prefix'], function () {
    \STS\LaravelUppyCompanion\LaravelUppyCompanion::routes(App::make('companion.archive'));
});
```

## File Keys
By default, the companion will generate a UUID with a file extension for each file uploaded.
If you wish to use a different key, you may pass a callback function to the `configure()` method.
```php
App::make(\STS\LaravelUppyCompanion\LaravelUppyCompanion::class)->configure(
    'my-bucket',
    new S3Client(config('aws')),
    fn($filename) => $filename
);
```

The default key generator is available as a static method on the companion class:
```php
App::make(\STS\LaravelUppyCompanion\LaravelUppyCompanion::class)->configure(
    'my-bucket',
    new S3Client(config('aws')),
    fn($filename) => \STS\LaravelUppyCompanion\LaravelUppyCompanion::getUUID($filename)
);
```
