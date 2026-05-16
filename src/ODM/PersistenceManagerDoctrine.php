<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\ODM;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use Throwable;

class PersistenceManagerDoctrine implements PersistenceManager
{
    public function __construct(
        protected DocumentManager $dm,
    ) {
    }

    public function persist($entity): void
    {
        $this->dm->persist($entity);
    }

    public function detach($entity): void
    {
        $this->dm->detach($entity);
    }

    public function remove($entity): void
    {
        $this->dm->remove($entity);
    }

    /**
     * @throws Throwable
     * @throws MongoDBException
     */
    public function flush(): void
    {
        // try {
        $this->dm->flush();
        // }
        // catch (\MongoDB\Driver\Exception\RuntimeException $e) {
        //     if ($e->getCode() === 11000) {
        //         Log::error($e->getMessage(), [
        //             'entity' => get_class($e),
        //             'code' => $e->getCode(),
        //             'trace' => $e->getTraceAsString(),
        //         ]);
        //         throw new DuplicateKeyException(
        //             'Duplicate key error',
        //             $e->getCode(),
        //             $e,
        //         );
        //     }
        //     throw $e;
        // }
    }

    public function clear($entityName = null): void
    {
        $this->dm->clear($entityName);
    }
}
