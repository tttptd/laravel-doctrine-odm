<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Commands;

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadataFactoryInterface;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceMetadata;
use Doctrine\Persistence\Mapping\ProxyClassNameResolver;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @internal Сужает только общий обход отдельного SchemaManager команды.
 *
 * DocumentManager продолжает использовать исходную фабрику. Делегирование
 * сохраняет алгоритм и режимы upstream SchemaManager без копирования в bridge.
 */
final readonly class SchemaMetadataFactory implements ClassMetadataFactoryInterface
{
    /** @param list<ClassMetadata> $metadata */
    public function __construct(
        private ClassMetadataFactoryInterface $original,
        private array $metadata,
    ) {
    }

    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataFor(string $className): PersistenceMetadata
    {
        return $this->original->getMetadataFor($className);
    }

    public function hasMetadataFor(string $className): bool
    {
        return $this->original->hasMetadataFor($className);
    }

    public function setMetadataFor(string $className, PersistenceMetadata $class): void
    {
        $this->original->setMetadataFor($className, $class);
    }

    public function isTransient(string $className): bool
    {
        return $this->original->isTransient($className);
    }

    public function setCache(CacheItemPoolInterface $cache): void
    {
        $this->original->setCache($cache);
    }

    public function setConfiguration(Configuration $config): void
    {
        $this->original->setConfiguration($config);
    }

    public function setDocumentManager(DocumentManager $dm): void
    {
        $this->original->setDocumentManager($dm);
    }

    public function setProxyClassNameResolver(ProxyClassNameResolver $resolver): void
    {
        $this->original->setProxyClassNameResolver($resolver);
    }
}
