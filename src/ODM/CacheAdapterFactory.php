<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\ODM;

use Illuminate\Support\Facades\Redis;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Exception\CacheException;

class CacheAdapterFactory
{
    /**
     * @throws CacheException
     */
    public static function create(array $config): CacheItemPoolInterface
    {
        $driver = $config['driver'] ?? 'array';
        $prefix = $config['prefix'] ?? 'doctrine_';
        $ttl = $config['ttl'] ?? 0;

        return match ($driver) {
            'redis' => self::createRedisAdapter($config, $prefix, $ttl),
            'memcached' => self::createMemcachedAdapter($config, $prefix, $ttl),
            'file' => self::createFilesystemAdapter($prefix, $ttl, $config['path'] ?? null),
            'phpfile' => self::createPhpFilesAdapter($prefix, $ttl, $config['path'] ?? null),
            'array' => new ArrayAdapter($ttl),
            default => throw new \InvalidArgumentException("Unsupported cache driver: {$driver}"),
        };
    }

    private static function createRedisAdapter(array $config, string $prefix, int $ttl): RedisAdapter
    {
        $connection = $config['connection'] ?? 'default';

        $redisClient = Redis::connection($connection)->client();
        // $redis = app('redis')->connection($connection);
        // $redisClient = $redis->client();

        return new RedisAdapter(
            redis: $redisClient,
            namespace: $prefix,
            defaultLifetime: $ttl,
        );
    }

    /**
     * @throws CacheException
     */
    private static function createMemcachedAdapter(array $config, string $prefix, int $ttl): MemcachedAdapter
    {
        $connection = $config['connection'] ?? 'default';
        $store = app('cache')->store('memcached');

        $memcached = $store->getMemcached();

        return new MemcachedAdapter(
            client: $memcached,
            namespace: $prefix,
            defaultLifetime: $ttl,
        );
    }

    private static function createFilesystemAdapter(string $prefix, int $ttl, ?string $path): FilesystemAdapter
    {
        $directory = $path ?? storage_path('framework/cache/doctrine');

        return new FilesystemAdapter(
            namespace: $prefix,
            defaultLifetime: $ttl,
            directory: $directory,
        );
    }

    /**
     * @throws CacheException
     */
    private static function createPhpFilesAdapter(string $prefix, int $ttl, ?string $path): PhpFilesAdapter
    {
        $directory = $path ?? storage_path('framework/cache/doctrine');

        return new PhpFilesAdapter(
            namespace: $prefix,
            defaultLifetime: $ttl,
            directory: $directory,
        );
    }
}
