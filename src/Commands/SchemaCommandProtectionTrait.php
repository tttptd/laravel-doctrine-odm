<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Commands;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\SchemaManager;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ys\LaravelOdm\ODM\SchemaOwner;
use Ys\LaravelOdm\ODM\SchemaOwnershipRegistry;

/** @internal Предварительная проверка владения для create/update/drop. */
trait SchemaCommandProtectionTrait
{
    private SchemaManager $commandSchemaManager;

    protected function getSchemaManager(): SchemaManager
    {
        return $this->commandSchemaManager;
    }

    private function prepareSchemaCommand(InputInterface $input, OutputInterface $output, bool $dropsDatabase = false): bool
    {
        try {
            $owners = $this->schemaOwnershipRegistry->resolveOwners($this->documentManager);
            if ($owners === []) {
                $this->commandSchemaManager = $this->documentManager->getSchemaManager();

                return true;
            }
            $factory = $this->documentManager->getMetadataFactory();
            $metadata = $factory->getAllMetadata();
            $class = $input->getOption('class');

            if (is_string($class)) {
                $target = $this->documentManager->getClassMetadata($class);
                $owner = $this->ownerForMetadata($target, $owners);
                if ($owner !== null) {
                    throw new RuntimeException(sprintf(
                        'Схемой %s управляет %s; используйте %s.', $class, $owner->owner, $owner->preparationCommand,
                    ));
                }
            }

            // Отказ проверяется до первой операции, включая индексы перед drop DB.
            if ($dropsDatabase && $owners !== []) {
                $database = is_string($class)
                    ? $this->documentManager->getDocumentDatabase($class)->getDatabaseName()
                    : null;
                foreach ($owners as $ownedDatabase => $collections) {
                    if ($database !== null && $database !== (string) $ownedDatabase) {
                        continue;
                    }
                    $owner = reset($collections);
                    throw new RuntimeException(sprintf(
                        'Удаление базы %s запрещено: схемой управляет %s; используйте %s.',
                        $ownedDatabase, $owner->owner, $owner->preparationCommand,
                    ));
                }
            }

            $allowed = [];
            foreach ($metadata as $item) {
                $owner = $this->ownerForMetadata($item, $owners);
                if ($owner === null) {
                    $allowed[] = $item;
                    continue;
                }
                if (! is_string($class)) {
                    $output->writeln(OutputFormatter::escape(sprintf(
                        'Пропущена схема %s: владелец %s; подготовка: %s.',
                        $item->name, $owner->owner, $owner->preparationCommand,
                    )));
                }
            }

            $this->commandSchemaManager = new SchemaManager(
                $this->documentManager, new SchemaMetadataFactory($factory, $allowed),
            );

            return true;
        } catch (RuntimeException $exception) {
            $output->writeln('<error>' . OutputFormatter::escape($exception->getMessage()) . '</error>');

            return false;
        }
    }

    /** @param array<string, array<string, SchemaOwner>> $owners */
    private function ownerForMetadata(ClassMetadata $metadata, array $owners): ?SchemaOwner
    {
        if ($metadata->isMappedSuperclass || $metadata->isEmbeddedDocument || $metadata->isQueryResultDocument) {
            return null;
        }
        $database = $this->documentManager->getDocumentDatabase($metadata->name)->getDatabaseName();
        foreach (SchemaOwnershipRegistry::collectionNames($metadata) as $collection) {
            if (isset($owners[$database][$collection])) {
                return $owners[$database][$collection];
            }
        }

        return null;
    }
}
