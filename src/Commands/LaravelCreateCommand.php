<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Commands;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\CreateCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ys\LaravelOdm\ODM\SchemaOwnershipRegistry;

class LaravelCreateCommand extends CreateCommand
{
    use DoctrineCommandWrapperTrait;
    use SchemaCommandProtectionTrait;

    private DocumentManager $documentManager;
    private SchemaOwnershipRegistry $schemaOwnershipRegistry;

    public function __construct(DocumentManager $documentManager, ?SchemaOwnershipRegistry $schemaOwnershipRegistry = null)
    {
        parent::__construct();
        $this->documentManager = $documentManager;
        $this->schemaOwnershipRegistry = $schemaOwnershipRegistry ?? new SchemaOwnershipRegistry();
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->prepareSchemaCommand($input, $output)) {
            return self::FAILURE;
        }
        $this->initHelper($this);

        return parent::execute($input, $output);
    }
}
