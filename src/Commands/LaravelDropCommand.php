<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Commands;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\DropCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class LaravelDropCommand extends DropCommand
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
        $question = new ConfirmationQuestion(
            '<error>This command will drop all collections. Are you sure you want to proceed? (type "yes" to confirm): </error>',
            false // Default answer is "no"
        );

        $helper = $this->getHelper('question');
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Command aborted by user.</comment>');
            return self::FAILURE;
        }
        
        $doctrineCommand = new DropCommand();

        $this->initHelper($doctrineCommand);
        $filteredInput = $this->removeCommandFromInputArgs($input);

        return $doctrineCommand->run($filteredInput, $output);
    }
}
