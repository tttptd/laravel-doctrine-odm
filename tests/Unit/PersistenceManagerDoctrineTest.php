<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Unit;

use Doctrine\ODM\MongoDB\DocumentManager;
use PHPUnit\Framework\TestCase;
use Ys\LaravelOdm\ODM\PersistenceManagerDoctrine;

final class PersistenceManagerDoctrineTest extends TestCase
{
    public function testDelegatesPersistenceOperationsToDocumentManager(): void
    {
        $document = new \stdClass();
        $documentManager = $this->createMock(DocumentManager::class);

        $documentManager->expects($this->once())
            ->method('persist')
            ->with($document);
        $documentManager->expects($this->once())
            ->method('detach')
            ->with($document);
        $documentManager->expects($this->once())
            ->method('remove')
            ->with($document);
        $documentManager->expects($this->once())
            ->method('flush');
        $documentManager->expects($this->once())
            ->method('clear')
            ->with($document::class);

        $manager = new PersistenceManagerDoctrine($documentManager);

        $manager->persist($document);
        $manager->detach($document);
        $manager->remove($document);
        $manager->flush();
        $manager->clear($document::class);
    }
}

