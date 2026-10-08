<?php

namespace Sereny\PostgREST\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sereny\PostgREST\PostgrestServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PostgrestServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.connections.postgrest', [
            'driver' => 'postgrest',
            'url' => 'http://localhost:3000',
            'schema' => 'public',
            'prefix' => '',
            'token' => null,
            'headers' => [],
        ]);
    }
}
