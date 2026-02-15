<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Port;

/**
 * Contract for filesystem-related operations.
 *
 * This interface abstracts common filesystem functions to:
 * - Decouple application logic from PHP global functions
 * - Improve testability (mockable filesystem layer)
 * - Centralize filesystem interactions
 */
interface FilesystemFunctionsInterface
{
    /**
     * Determine whether a file or directory exists.
     */
    public function fileExists(string $path): bool;

    /**
     * Determine whether the given path is a regular file.
     */
    public function isFile(string $path): bool;

    /**
     * Determine whether the given path is a directory.
     */
    public function isDir(string $path): bool;

    /**
     * Determine whether the given path is a symbolic link.
     */
    public function isLink(string $path): bool;

    /**
     * Determine whether the given path is readable.
     */
    public function isReadable(string $path): bool;

    /**
     * Create a directory.
     *
     * @param string $directory   Directory path.
     * @param int    $permissions Directory permissions (default: 0775).
     * @param bool   $recursive   Whether to create parent directories.
     *
     * @return bool True on success, false on failure.
     */
    public function mkdir(string $directory, int $permissions = 0775, bool $recursive = true): bool;

    /**
     * Delete a file.
     */
    public function unlink(string $path): bool;

    /**
     * Remove a directory.
     */
    public function rmdir(string $directory): bool;

    /**
     * Return the directory name component of a path.
     */
    public function dirname(string $path): string;

    /**
     * Return the base name component of a path.
     */
    public function basename(string $path): string;

    /**
     * Join multiple path segments into a single filesystem path.
     *
     * @param string ...$parts Path segments.
     *
     * @return string Combined path.
     */
    public function joinPath(string ...$parts): string;

    /**
     * Get the system temporary directory.
     */
    public function sysGetTempDir(): string;
}
