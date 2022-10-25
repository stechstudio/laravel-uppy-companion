<?php

namespace STS\LaravelUppyCompanion;

use Illuminate\Support\ServiceProvider;

class LaravelUppyCompanionServiceProvider extends ServiceProvider
{
    public function provides()
    {
        return [LaravelUppyCompanion::class];
    }
}
