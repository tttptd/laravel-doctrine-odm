<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Unit;

use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\UpdateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Ys\LaravelOdm\Commands\DoctrineCommandWrapperTrait;
use Ys\LaravelOdm\Tests\TestCase;

final class DoctrineCommandWrapperTraitTest extends TestCase
{
    public function testForwardsOptionsSupportedByTheNativeDoctrineCommand(): void
    {
        $definition = new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED),
            new InputOption('skip-search-indexes'),
            new InputOption('env', null, InputOption::VALUE_REQUIRED),
        ]);
        $input = new ArrayInput([
            'command' => 'odm:schema:update',
            '--skip-search-indexes' => true,
            '--env' => 'testing',
        ], $definition);
        $input->bind($definition);

        $filteredInput = $this->wrapper()->filter($input, new UpdateCommand());
        $filteredInput->bind((new UpdateCommand())->getDefinition());

        self::assertTrue($filteredInput->getOption('skip-search-indexes'));
        self::assertSame('odm:schema:update', $input->getArgument('command'));
    }

    private function wrapper(): object
    {
        return new class {
            use DoctrineCommandWrapperTrait;

            public function filter(InputInterface $input, Command $doctrineCommand): InputInterface
            {
                return $this->removeCommandFromInputArgs($input, $doctrineCommand);
            }
        };
    }
}
