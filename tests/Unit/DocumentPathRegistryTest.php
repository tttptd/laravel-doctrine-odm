<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ys\LaravelOdm\ODM\DocumentPathRegistry;

final class DocumentPathRegistryTest extends TestCase
{
    public function testDeduplicatesDocumentPathsAndKeepsInsertionOrder(): void
    {
        $existingPath = __DIR__ . '/../Fixtures/Documents';
        $missingPath = __DIR__ . '/../Fixtures/MissingDocuments';

        $registry = new DocumentPathRegistry();
        $registry->addDocumentPath($existingPath);
        $registry->addDocumentPath($existingPath);
        $registry->addDocumentPath($missingPath);

        self::assertSame([
            realpath($existingPath),
            $missingPath,
        ], $registry->documentPaths());
    }

    public function testDeduplicatesExcludePathsSeparatelyFromDocumentPaths(): void
    {
        $existingPath = __DIR__ . '/../Fixtures/ExcludedDocuments';

        $registry = new DocumentPathRegistry();
        $registry->addDocumentPath($existingPath);
        $registry->addExcludePath($existingPath);
        $registry->addExcludePath($existingPath);

        self::assertSame([realpath($existingPath)], $registry->documentPaths());
        self::assertSame([realpath($existingPath)], $registry->excludePaths());
    }
}
