<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\ODM;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use InvalidArgumentException;
use RuntimeException;

/**
 * Объявляет владельцев изменений схемы без изменения обнаружения документов.
 *
 * Provider регистрирует владение до выполнения общей schema-команды. Физическое
 * хранение разрешается по текущим metadata, чтобы configured names и другой
 * mapping той же коллекции не обходили защиту (docs/adr/0001-schema-ownership.md).
 */
final class SchemaOwnershipRegistry
{
    /** @var array<class-string, SchemaOwner> */
    private array $documents = [];

    /** @var array<string, array<string, SchemaOwner>> */
    private array $collections = [];

    /** @param class-string $documentClass */
    public function registerDocument(string $owner, string $documentClass, string $preparationCommand): void
    {
        $claim = $this->newOwner($owner, $preparationCommand);
        if ($documentClass === '') {
            throw new InvalidArgumentException('Класс документа не может быть пустым.');
        }
        $this->assertCompatible($this->documents[$documentClass] ?? null, $claim, $documentClass);
        $this->documents[$documentClass] = $claim;
    }

    public function registerCollection(string $owner, string $database, string $collection, string $preparationCommand): void
    {
        $claim = $this->newOwner($owner, $preparationCommand);
        if (trim($database) === '' || trim($collection) === '') {
            throw new InvalidArgumentException('База и коллекция не могут быть пустыми.');
        }
        $this->claimCollection($this->collections, $database, $collection, $claim);
    }

    /**
     * @internal Читающая предварительная проверка всех регистраций, до schema apply.
     * @return array<string, array<string, SchemaOwner>>
     */
    public function resolveOwners(DocumentManager $documentManager): array
    {
        $owners = $this->collections;
        foreach ($this->documents as $documentClass => $claim) {
            $metadata = $documentManager->getClassMetadata($documentClass);
            if ($metadata->isEmbeddedDocument || $metadata->isMappedSuperclass || $metadata->isQueryResultDocument) {
                throw new RuntimeException(sprintf('Документ %s не имеет собственной коллекции.', $documentClass));
            }
            $database = $documentManager->getDocumentDatabase($metadata->name)->getDatabaseName();
            foreach (self::collectionNames($metadata) as $collection) {
                $this->claimCollection($owners, $database, $collection, $claim);
            }
        }

        return $owners;
    }

    /** @internal @return list<string> */
    public static function collectionNames(ClassMetadata $metadata): array
    {
        // Операции над GridFS меняют обе физические коллекции одного bucket.
        return $metadata->isFile
            ? [$metadata->getBucketName() . '.files', $metadata->getBucketName() . '.chunks']
            : [$metadata->getCollection()];
    }

    private function newOwner(string $owner, string $preparationCommand): SchemaOwner
    {
        if (trim($owner) === '' || trim($preparationCommand) === '') {
            throw new InvalidArgumentException('Владелец и команда подготовки не могут быть пустыми.');
        }

        return new SchemaOwner($owner, $preparationCommand);
    }

    /** @param array<string, array<string, SchemaOwner>> $owners */
    private function claimCollection(array &$owners, string $database, string $collection, SchemaOwner $claim): void
    {
        $this->assertCompatible($owners[$database][$collection] ?? null, $claim, $database . '.' . $collection);
        $owners[$database][$collection] = $claim;
    }

    private function assertCompatible(?SchemaOwner $existing, SchemaOwner $claim, string $target): void
    {
        if ($existing !== null && ($existing->owner !== $claim->owner || $existing->preparationCommand !== $claim->preparationCommand)) {
            throw new RuntimeException(sprintf(
                'Конфликт владения %s: %s (%s) и %s (%s).',
                $target, $existing->owner, $existing->preparationCommand, $claim->owner, $claim->preparationCommand,
            ));
        }
    }
}
