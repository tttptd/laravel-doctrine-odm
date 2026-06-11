<?php
declare(strict_types=1);

namespace Ys\LaravelOdm\ODM;

/**
 * Собирает ODM document paths из host config и package providers.
 *
 * Registry нужен как public extension point: Laravel package может владеть
 * собственными Doctrine documents и не требовать от host ручного vendor path
 * в config/mongodb.php.
 */
final class DocumentPathRegistry
{
    /** @var array<string, string> */
    private array $documentPaths = [];

    /** @var array<string, string> */
    private array $excludePaths = [];

    public function addDocumentPath(string $path): void
    {
        $normalized = $this->normalizePath($path);
        $this->documentPaths[$normalized] = $normalized;
    }

    public function addExcludePath(string $path): void
    {
        $normalized = $this->normalizePath($path);
        $this->excludePaths[$normalized] = $normalized;
    }

    /**
     * @return list<string>
     */
    public function documentPaths(): array
    {
        return array_values($this->documentPaths);
    }

    /**
     * @return list<string>
     */
    public function excludePaths(): array
    {
        return array_values($this->excludePaths);
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);

        return $realPath !== false ? $realPath : $path;
    }
}
