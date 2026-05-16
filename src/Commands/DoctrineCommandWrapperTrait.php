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

    private function removeCommandFromInputArgs(InputInterface $input): InputInterface
    {
        // Убираем саму команду из аргументов
        $inputArgs = $input->getArguments();
        unset($inputArgs['command']);

        return new ArrayInput($inputArgs);
    }
}
