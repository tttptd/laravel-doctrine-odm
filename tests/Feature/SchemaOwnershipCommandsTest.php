<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Feature;

use Doctrine\ODM\MongoDB\DocumentManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Ys\LaravelOdm\Tests\Fixtures\TestPackageDocumentPathServiceProvider;
use Ys\LaravelOdm\Tests\Fixtures\PackageDocuments\TestPackagePage;
use Ys\LaravelOdm\ODM\SchemaOwnershipRegistry;
use Ys\LaravelOdm\Tests\Fixtures\Documents\TestArticle;
use Ys\LaravelOdm\Tests\TestCase;

final class SchemaOwnershipCommandsTest extends TestCase
{
    public function testTargetedUpdateRefusesOwnedCollectionBeforeConnectingToMongoDb(): void
    {
        $this->app->make(SchemaOwnershipRegistry::class)->registerDocument(
            'fixture/articles', TestArticle::class, 'fixture:schema:prepare',
        );

        $this->artisan('odm:schema:update', ['--class' => TestArticle::class])
            ->expectsOutputToContain('fixture/articles; используйте fixture:schema:prepare')
            ->assertFailed();

        self::assertSame('test_articles', $this->app->make(DocumentManager::class)
            ->getClassMetadata(TestArticle::class)->getCollection());
    }

    #[DataProvider('targetedCommands')]
    public function testExplicitPhysicalCollectionProtectsConfiguredMapping(string $command, array $options): void
    {
        $dm = $this->app->make(DocumentManager::class);
        $dm->getClassMetadata(TestArticle::class)->setCollection('configured_articles');
        $this->app->make(SchemaOwnershipRegistry::class)->registerCollection(
            'fixture/native', 'laravel_odm_test', 'configured_articles', 'fixture:native:prepare',
        );
        $this->artisan($command, ['--class' => TestArticle::class] + $options)
            ->expectsOutputToContain('fixture/native; используйте fixture:native:prepare')
            ->assertFailed();
    }

    public static function targetedCommands(): array
    {
        return [
            'create' => ['odm:schema:create', []],
            'update' => ['odm:schema:update', []],
            'drop' => ['odm:schema:drop', ['--collection' => true]],
        ];
    }

    public function testProviderRegistrationWorksBeforeFirstDocumentManagerResolution(): void
    {
        self::assertFalse($this->app->resolved(DocumentManager::class));
        $this->app->register(TestPackageDocumentPathServiceProvider::class);
        self::assertFalse($this->app->resolved(DocumentManager::class));
        $this->artisan('odm:schema:update', ['--class' => TestPackagePage::class])
            ->expectsOutputToContain('fixture/pages; используйте fixture:pages:prepare')
            ->assertFailed();
    }

    public function testEmptyOwnerCannotBeRegistered(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->app->make(SchemaOwnershipRegistry::class)->registerDocument('', TestArticle::class, 'fixture:prepare');
    }

    public function testRepeatedCompatibleRegistrationDoesNotConflict(): void
    {
        $registry = $this->app->make(SchemaOwnershipRegistry::class);
        for ($i = 0; $i < 2; $i++) {
            $registry->registerDocument('fixture/articles', TestArticle::class, 'fixture:prepare');
            $registry->registerCollection('fixture/articles', 'laravel_odm_test', 'test_articles', 'fixture:prepare');
        }
        $this->artisan('odm:schema:update', ['--class' => TestArticle::class])
            ->expectsOutputToContain('fixture/articles; используйте fixture:prepare')
            ->assertFailed();
    }

    public function testSameOwnerWithDifferentPreparationCommandsConflicts(): void
    {
        $registry = $this->app->make(SchemaOwnershipRegistry::class);
        $registry->registerDocument('fixture/articles', TestArticle::class, 'fixture:prepare');
        $registry->registerCollection('fixture/articles', 'laravel_odm_test', 'test_articles', 'fixture:other:prepare');
        $this->artisan('odm:schema:update')
            ->expectsOutputToContain('Конфликт владения laravel_odm_test.test_articles')
            ->assertFailed();
    }
}
