<?php

namespace Khable\KhadsClient\Tests;

use Khable\KhadsClient\KhadsClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [KhadsClientServiceProvider::class];
    }
}
