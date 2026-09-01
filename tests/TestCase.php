<?php

namespace Lumi\NativePush\Tests;

use Lumi\NativePush\PushServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PushServiceProvider::class];
    }
}
