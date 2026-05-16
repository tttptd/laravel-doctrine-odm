<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\ODM;

interface PersistenceManager
{
    public function persist($entity): void;

    public function detach($entity): void;

    public function remove($entity): void;

    public function flush(): void;

    /**
     * @param null $entityName
     */
    public function clear($entityName = null): void;
}
