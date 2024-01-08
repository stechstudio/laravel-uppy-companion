<?php

namespace STS\LaravelUppyCompanion;

use Illuminate\Support\ServiceProvider;

class LaravelUppyCompanionServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(LaravelUppyCompanion::class, fn () => new LaravelUppyCompanion());
    }

    public function provides()
    {
        return [LaravelUppyCompanion::class];
    }
}
