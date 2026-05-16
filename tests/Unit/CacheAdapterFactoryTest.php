<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Ys\LaravelOdm\ODM\CacheAdapterFactory;

final class CacheAdapterFactoryTest extends TestCase
{
    public function testCreatesArrayAdapterByDefault(): void
    {
        $adapter = CacheAdapterFactory::create([]);

        self::assertInstanceOf(ArrayAdapter::class, $adapter);
    }

    public function testCreatesFilesystemAdapterWithConfiguredDirectory(): void
    {
        $adapter = CacheAdapterFactory::create([
            'driver' => 'file',
            'prefix' => 'metadata_',
            'ttl' => 120,
            'path' => sys_get_temp_dir() . '/laravel-odm-cache-test',
        ]);

        self::assertInstanceOf(FilesystemAdapter::class, $adapter);
    }

    public function testCreatesPhpFilesAdapterWithConfiguredDirectory(): void
    {
        $adapter = CacheAdapterFactory::create([
            'driver' => 'phpfile',
            'prefix' => 'metadata_',
            'ttl' => 120,
            'path' => sys_get_temp_dir() . '/laravel-odm-phpfile-cache-test',
        ]);

        self::assertInstanceOf(PhpFilesAdapter::class, $adapter);
    }

    public function testRejectsUnsupportedDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported cache driver: unsupported');

        CacheAdapterFactory::create([
            'driver' => 'unsupported',
        ]);
    }
}

