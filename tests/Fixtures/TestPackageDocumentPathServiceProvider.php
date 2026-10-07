<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;
use Ys\LaravelOdm\ODM\DocumentPathRegistry;
use Ys\LaravelOdm\ODM\SchemaOwnershipRegistry;
use Ys\LaravelOdm\Tests\Fixtures\PackageDocuments\TestPackagePage;

final class TestPackageDocumentPathServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            DocumentPathRegistry::class,
            static fn(DocumentPathRegistry $registry) => $registry->addDocumentPath(__DIR__ . '/PackageDocuments'),
        );
        $this->callAfterResolving(
            SchemaOwnershipRegistry::class,
            static fn(SchemaOwnershipRegistry $registry) => $registry->registerDocument(
                'fixture/pages', TestPackagePage::class, 'fixture:pages:prepare',
            ),
        );
    }
}
