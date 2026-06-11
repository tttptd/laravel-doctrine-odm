<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Feature;

use Doctrine\ODM\MongoDB\DocumentManager;
use Illuminate\Support\ServiceProvider;
use Ys\LaravelOdm\DoctrineMongoDBServiceProvider;
use Ys\LaravelOdm\ODM\DocumentPathRegistry;
use Ys\LaravelOdm\ODM\DocumentManagerFactory;
use Ys\LaravelOdm\ODM\PersistenceManager;
use Ys\LaravelOdm\Tests\Fixtures\Documents\TestArticle;
use Ys\LaravelOdm\Tests\Fixtures\PackageDocuments\TestPackagePage;
use Ys\LaravelOdm\Tests\Fixtures\TestPackageDocumentPathServiceProvider;
use Ys\LaravelOdm\Tests\TestCase;

final class DoctrineMongoDBServiceProviderTest extends TestCase
{
    public function testPublishesMongoDbConfig(): void
    {
        $paths = ServiceProvider::pathsToPublish(DoctrineMongoDBServiceProvider::class);

        self::assertSame([
            realpath(__DIR__ . '/../../src/config/mongodb.php') => config_path('mongodb.php'),
        ], $paths);
    }

    public function testRegistersDocumentManagerFactoryAsSingleton(): void
    {
        $first = $this->app->make(DocumentManagerFactory::class);
        $second = $this->app->make(DocumentManagerFactory::class);

        self::assertSame($first, $second);
    }

    public function testDocumentManagerIsScopedToCurrentApplicationLifecycle(): void
    {
        $first = $this->app->make(DocumentManager::class);
        $second = $this->app->make(DocumentManager::class);

        self::assertSame($first, $second);

        $this->app->forgetScopedInstances();

        $nextLifecycle = $this->app->make(DocumentManager::class);

        self::assertNotSame($first, $nextLifecycle);
    }

    public function testPersistenceManagerUsesTheCurrentScopedDocumentManager(): void
    {
        $documentManager = $this->app->make(DocumentManager::class);
        $persistenceManager = $this->app->make(PersistenceManager::class);

        self::assertSame($documentManager, $this->readObjectProperty($persistenceManager, 'dm'));
    }

    public function testDocumentPathRegistryIsSeededFromMongoDbConfig(): void
    {
        $registry = $this->app->make(DocumentPathRegistry::class);

        self::assertSame([
            realpath(__DIR__ . '/../Fixtures/Documents'),
        ], $registry->documentPaths());
        self::assertSame([
            realpath(__DIR__ . '/../Fixtures/ExcludedDocuments'),
        ], $registry->excludePaths());
    }

    public function testDocumentManagerUsesConfiguredDoctrinePathsAndDatabase(): void
    {
        $documentManager = $this->app->make(DocumentManager::class);
        $configuration = $documentManager->getConfiguration();

        self::assertSame('laravel_odm_test', $configuration->getDefaultDB());
        self::assertSame(sys_get_temp_dir() . '/laravel-odm-test/proxies', $configuration->getProxyDir());
        self::assertSame('LaravelOdmTestProxies', $configuration->getProxyNamespace());
        self::assertSame(sys_get_temp_dir() . '/laravel-odm-test/hydrators', $configuration->getHydratorDir());
        self::assertSame('LaravelOdmTestHydrators', $configuration->getHydratorNamespace());
        self::assertSame(3, $configuration->getAutoGenerateProxyClasses());
    }

    public function testDocumentMetadataCanBeLoadedFromConfiguredDocumentPaths(): void
    {
        $documentManager = $this->app->make(DocumentManager::class);
        $metadata = $documentManager->getClassMetadata(TestArticle::class);

        self::assertSame('test_articles', $metadata->getCollection());
        self::assertArrayHasKey('title', $metadata->fieldMappings);
    }

    public function testDocumentMetadataCanBeLoadedFromPackageRegisteredDocumentPaths(): void
    {
        $this->app->register(TestPackageDocumentPathServiceProvider::class);

        $registry = $this->app->make(DocumentPathRegistry::class);
        self::assertSame([
            realpath(__DIR__ . '/../Fixtures/Documents'),
            realpath(__DIR__ . '/../Fixtures/PackageDocuments'),
        ], $registry->documentPaths());

        $documentManager = $this->app->make(DocumentManager::class);
        $metadata = $documentManager->getClassMetadata(TestPackagePage::class);

        self::assertSame('test_package_pages', $metadata->getCollection());
        self::assertArrayHasKey('title', $metadata->fieldMappings);
    }

    private function readObjectProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionObject($object);
        $propertyReflection = $reflection->getProperty($property);

        return $propertyReflection->getValue($object);
    }
}
