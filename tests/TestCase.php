<?php

namespace STS\LaravelUppyCompanion\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use STS\LaravelUppyCompanion\LaravelUppyCompanionServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelUppyCompanionServiceProvider::class,
        ];
    }
}
