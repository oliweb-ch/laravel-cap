<?php
namespace LaravelCap\Tests;
use LaravelCap\CapServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CapServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cap.endpoint', 'https://cap.test/site-key/');
        $app['config']->set('cap.secret', 'test-secret');
        $app['config']->set('cap.token_field', 'cap-token');
        $app['config']->set('cap.timeout', 5);
        $app['config']->set('cap.fail_open', false);
        $app['config']->set('cap.alerts.mail', null);
        $app['config']->set('cap.alerts.throttle_minutes', 15);
        $app['config']->set('cache.default', 'array');
    }
}