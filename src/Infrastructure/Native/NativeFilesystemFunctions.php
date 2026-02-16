<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;

/**
 * Native implementation of {@see FilesystemFunctionsInterface}.
 *
 * This class provides a thin abstraction layer over PHP's built-in filesystem
 * functions (file_exists, is_file, mkdir, etc.).
 *
 * The indirection through {@see NativeFunctionInvokerInterface} exists to:
 * - keep production behavior identical (real global calls),
 * - make unit tests deterministic and hermetic,
 * - avoid relying on filesystem side effects to assert delegation.
 *
 * Notes:
 * - Pure string manipulation helpers (e.g. {@see joinPath()}) do not require the
 *   invoker and are implemented directly.
 */
final class NativeFilesystemFunctions implements FilesystemFunctionsInterface
{
    /**
     * Invoker used for calling native/global functions.
     */
    private readonly NativeFunctionInvokerInterface $invoke;

    /**
     * @param NativeFunctionInvokerInterface|null $invoke
     *        Invoker used to call native filesystem functions. If null, a default
     *        {@see NativeFunctionInvoker} is used.
     */
    public function __construct(?NativeFunctionInvokerInterface $invoke = null)
    {
        $this->invoke = $invoke ?? new NativeFunctionInvoker();
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \file_exists()}.
     */
    public function fileExists(string $path): bool
    {
        return (bool) ($this->invoke)('file_exists', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \is_file()}.
     */
    public function isFile(string $path): bool
    {
        return (bool) ($this->invoke)('is_file', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \is_dir()}.
     */
    public function isDir(string $path): bool
    {
        return (bool) ($this->invoke)('is_dir', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \is_link()}.
     */
    public function isLink(string $path): bool
    {
        return (bool) ($this->invoke)('is_link', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \is_readable()}.
     */
    public function isReadable(string $path): bool
    {
        return (bool) ($this->invoke)('is_readable', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Creates a directory.
     *
     * Delegates to {@see \mkdir()}.
     *
     * @param string $directory Directory path.
     * @param int $permissions Directory permissions (default: 0775).
     * @param bool $recursive Whether to create parent directories (default: true).
     */
    public function mkdir(string $directory, int $permissions = 0775, bool $recursive = true): bool
    {
        return (bool) ($this->invoke)('mkdir', [$directory, $permissions, $recursive]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \unlink()}.
     */
    public function unlink(string $path): bool
    {
        return (bool) ($this->invoke)('unlink', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \rmdir()}.
     */
    public function rmdir(string $directory): bool
    {
        return (bool) ($this->invoke)('rmdir', [$directory]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \dirname()}.
     */
    public function dirname(string $path): string
    {
        return (string) ($this->invoke)('dirname', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \basename()}.
     */
    public function basename(string $path): string
    {
        return (string) ($this->invoke)('basename', [$path]);
    }

    /**
     * Join multiple path segments using the system directory separator.
     *
     * Empty segments are ignored.
     *
     * This helper is pure and does not require invoking native functions.
     *
     * @param string ...$parts Path segments.
     *
     * @return string Joined filesystem path. Returns an empty string when all parts are empty.
     */
    public function joinPath(string ...$parts): string
    {
        $parts = \array_values(\array_filter($parts, static fn ($p) => $p !== ''));

        if ($parts === []) {
            return '';
        }

        $path = \array_shift($parts);

        foreach ($parts as $p) {
            $path = \rtrim($path, \DIRECTORY_SEPARATOR)
                . \DIRECTORY_SEPARATOR
                . \ltrim($p, \DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \sys_get_temp_dir()}.
     */
    public function sysGetTempDir(): string
    {
        return (string) ($this->invoke)('sys_get_temp_dir', []);
    }
}
