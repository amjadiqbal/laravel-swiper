<?php

namespace AmjadIqbal\LaravelSwiper\Tests;

use AmjadIqbal\LaravelSwiper\LaravelSwiperServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelSwiperServiceProvider::class,
        ];
    }
}
