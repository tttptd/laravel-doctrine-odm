<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Commands;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\UpdateCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LaravelUpdateCommand extends UpdateCommand
{
    use DoctrineCommandWrapperTrait;

    private DocumentManager $documentManager;

    public function __construct(DocumentManager $documentManager)
    {
        parent::__construct();
        $this->documentManager = $documentManager;
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $doctrineCommand = new UpdateCommand();

        $this->initHelper($doctrineCommand);
        $filteredInput = $this->removeCommandFromInputArgs($input);

        return $doctrineCommand->run($filteredInput, $output);
    }
}
