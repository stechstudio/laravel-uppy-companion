<?php

namespace STS\LaravelUppyCompanion;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class LaravelUppyCompanionServiceProvider extends ServiceProvider
{
    public function register()
    {
        App::singleton(LaravelUppyCompanion::class, fn () => new LaravelUppyCompanion());
    }

    public function provides()
    {
        return [LaravelUppyCompanion::class];
    }
}
