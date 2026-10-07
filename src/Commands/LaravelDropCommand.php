<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Commands;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\DropCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Ys\LaravelOdm\ODM\SchemaOwnershipRegistry;

class LaravelDropCommand extends DropCommand
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
        $dropsDatabase = $input->getOption('db') || (
            ! $input->getOption('collection') && ! $input->getOption('index') && ! $input->getOption('search-index')
        );
        if (! $this->prepareSchemaCommand($input, $output, $dropsDatabase)) {
            return self::FAILURE;
        }
        $question = new ConfirmationQuestion(
            '<error>This command will drop all collections. Are you sure you want to proceed? (type "yes" to confirm): </error>',
            false // Default answer is "no"
        );

        $helper = $this->getHelper('question');
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Command aborted by user.</comment>');
            return self::FAILURE;
        }
        
        $this->initHelper($this);

        return parent::execute($input, $output);
    }
}
