<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;

/**
 * Native implementation of {@see FilesystemFunctionsInterface}.
 *
 * This class provides a thin abstraction layer over PHP's built-in
 * filesystem functions. It exists to:
 * - Decouple domain/service logic from global PHP functions
 * - Improve testability (by allowing alternative implementations)
 * - Centralize filesystem-related behavior
 */
final class NativeFilesystemFunctions implements FilesystemFunctionsInterface
{
    /**
     * {@inheritDoc}
     */
    public function fileExists(string $path): bool
    {
        return \file_exists($path);
    }

    /**
     * {@inheritDoc}
     */
    public function isFile(string $path): bool
    {
        return \is_file($path);
    }

    /**
     * {@inheritDoc}
     */
    public function isDir(string $path): bool
    {
        return \is_dir($path);
    }

    /**
     * {@inheritDoc}
     */
    public function isLink(string $path): bool
    {
        return \is_link($path);
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(string $path): bool
    {
        return \is_readable($path);
    }

    /**
     * {@inheritDoc}
     *
     * Creates a directory.
     *
     * @param string $directory Directory path.
     * @param int $permissions Directory permissions (default: 0775).
     * @param bool $recursive Whether to create parent directories.
     */
    public function mkdir(string $directory, int $permissions = 0775, bool $recursive = true): bool
    {
        return \mkdir($directory, $permissions, $recursive);
    }

    /**
     * {@inheritDoc}
     */
    public function unlink(string $path): bool
    {
        return \unlink($path);
    }

    /**
     * {@inheritDoc}
     */
    public function rmdir(string $directory): bool
    {
        return \rmdir($directory);
    }

    /**
     * {@inheritDoc}
     */
    public function dirname(string $path): string
    {
        return \dirname($path);
    }

    /**
     * {@inheritDoc}
     */
    public function basename(string $path): string
    {
        return \basename($path);
    }

    /**
     * Join multiple path segments using the system directory separator.
     *
     * Empty segments are ignored.
     *
     * @param string ...$parts Path segments.
     *
     * @return string Joined filesystem path.
     */
    public function joinPath(string ...$parts): string
    {
        $parts = \array_values(\array_filter($parts, static fn ($p) => $p !== ''));

        if ($parts === []) {
            return '';
        }

        $path = \array_shift($parts);

        foreach ($parts as $p) {
            $path = \rtrim((string) $path, \DIRECTORY_SEPARATOR)
                . \DIRECTORY_SEPARATOR
                . \ltrim($p, \DIRECTORY_SEPARATOR);
        }

        return (string) $path;
    }

    /**
     * {@inheritDoc}
     */
    public function sysGetTempDir(): string
    {
        return \sys_get_temp_dir();
    }
}
