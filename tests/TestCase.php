<?php

namespace PsobPsay\Client\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PsobPsay\Client\PsobPsayClientServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [PsobPsayClientServiceProvider::class];
    }
}
