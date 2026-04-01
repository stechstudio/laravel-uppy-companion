<?php

use STS\LaravelUppyCompanion\LaravelUppyCompanion;
use STS\LaravelUppyCompanion\LaravelUppyCompanionServiceProvider;

it('registers the singleton in the container', function () {
    $instance = app(LaravelUppyCompanion::class);

    expect($instance)->toBeInstanceOf(LaravelUppyCompanion::class);
});

it('returns the same instance on repeated resolve', function () {
    $first = app(LaravelUppyCompanion::class);
    $second = app(LaravelUppyCompanion::class);

    expect($first)->toBe($second);
});

it('declares provided services', function () {
    $provider = new LaravelUppyCompanionServiceProvider(app());

    expect($provider->provides())->toBe([LaravelUppyCompanion::class]);
});
