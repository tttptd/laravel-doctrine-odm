<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Ys\LaravelOdm\DoctrineMongoDBServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DoctrineMongoDBServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mongodb.connection.server', 'mongodb://localhost:27017');
        $app['config']->set('mongodb.connection.options.db', 'laravel_odm_test');
        $app['config']->set('mongodb.cache.metadata.driver', 'array');
        $app['config']->set('mongodb.paths.documents', [
            __DIR__ . '/Fixtures/Documents',
        ]);
        $app['config']->set('mongodb.paths.exclude_documents', [
            __DIR__ . '/Fixtures/ExcludedDocuments',
        ]);
        $app['config']->set('mongodb.paths.proxies.path', sys_get_temp_dir() . '/laravel-odm-test/proxies');
        $app['config']->set('mongodb.paths.proxies.namespace', 'LaravelOdmTestProxies');
        $app['config']->set('mongodb.paths.proxies.auto_generate', 3);
        $app['config']->set('mongodb.paths.hydrators.path', sys_get_temp_dir() . '/laravel-odm-test/hydrators');
        $app['config']->set('mongodb.paths.hydrators.namespace', 'LaravelOdmTestHydrators');
        $app['config']->set('mongodb.use_transactional_flush', true);
    }
}

