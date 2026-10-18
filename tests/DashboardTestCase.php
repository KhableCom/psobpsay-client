<?php

namespace PsobPsay\Client\Tests;

abstract class DashboardTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('psobpsay.dashboard.enabled', true);
        $app['config']->set('psobpsay.dashboard.prefix', 'admin/psobpsay');
        $app['config']->set('psobpsay.dashboard.middleware', ['web', 'psobpsay.test-admin']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('psobpsay.test-admin', TestAdminMiddleware::class);
    }
}
