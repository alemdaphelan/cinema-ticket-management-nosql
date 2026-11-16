<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Laravel reads $_SERVER before the environment values set by PHPUnit.
        $_SERVER['APP_ENV'] = 'testing';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_URL'] = '';

        $app = parent::createApplication();
        $config = $app->make('config');

        if (! $app->environment('testing')
            || $config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:'
            || filled($config->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('Laravel tests require an isolated in-memory SQLite database.');
        }

        return $app;
    }
}
