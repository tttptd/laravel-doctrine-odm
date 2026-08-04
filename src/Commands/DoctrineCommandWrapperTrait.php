<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Commands;

use Doctrine\ODM\MongoDB\Tools\Console\Helper\DocumentManagerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;

trait DoctrineCommandWrapperTrait
{
    private function initHelper(Command $doctrineCommand): void
    {
        // Пробрасываем в команду HelperSet с documentManager
        $helperSet = new HelperSet();
        $helperSet->set(
            new DocumentManagerHelper($this->documentManager),
            'documentManager',
        );
        $doctrineCommand->setHelperSet($helperSet);
    }

    private function removeCommandFromInputArgs(InputInterface $input, Command $doctrineCommand): InputInterface
    {
        $inputArgs = $input->getArguments();
        unset($inputArgs['command']);

        foreach ($input->getOptions() as $name => $value) {
            // Laravel добавляет глобальные опции, которых нет у нативной Doctrine-команды.
            if (! $doctrineCommand->getDefinition()->hasOption($name) || $value === false || $value === null) {
                continue;
            }

            $inputArgs['--' . $name] = $value;
        }

        return new ArrayInput($inputArgs, $doctrineCommand->getDefinition());
    }
}
