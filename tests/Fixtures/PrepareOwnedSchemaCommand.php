<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Fixtures;

use Doctrine\ODM\MongoDB\DocumentManager;
use Illuminate\Console\Command;
use Ys\LaravelOdm\Tests\Fixtures\SchemaDocuments\OwnedRecord;

/** Fixture-владелец готовит схему своим публичным CLI, включая ограничения вне ODM. */
final class PrepareOwnedSchemaCommand extends Command
{
    protected $signature = 'fixture:schema:prepare';

    public function handle(DocumentManager $documentManager): int
    {
        $collection = $documentManager->getDocumentCollection(OwnedRecord::class);
        $documentManager->getDocumentDatabase(OwnedRecord::class)->createCollection(
            $collection->getCollectionName(),
            ['validator' => ['requestKey' => ['$type' => 'string']]],
        );
        $documentManager->getSchemaManager()->ensureDocumentIndexes(OwnedRecord::class);
        $collection->createIndex(['requestKey' => 1], ['name' => 'owner_request', 'unique' => true]);
        $collection->createIndex(['externalField' => 1], ['name' => 'additional_index']);

        return self::SUCCESS;
    }
}
