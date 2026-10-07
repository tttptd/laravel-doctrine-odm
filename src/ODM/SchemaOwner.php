<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\ODM;

/** @internal Диагностика владельца для защищаемых общих CLI-команд. */
final readonly class SchemaOwner
{
    public function __construct(
        public string $owner,
        public string $preparationCommand,
    ) {
    }
}
