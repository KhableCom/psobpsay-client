<?php

namespace Khable\PsobPsayClient\Tests;

use Khable\PsobPsayClient\PsobPsayClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [PsobPsayClientServiceProvider::class];
    }
}
