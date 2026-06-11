<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;
use Ys\LaravelOdm\ODM\DocumentPathRegistry;

final class TestPackageDocumentPathServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            DocumentPathRegistry::class,
            static fn(DocumentPathRegistry $registry) => $registry->addDocumentPath(__DIR__ . '/PackageDocuments'),
        );
    }
}
