<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Util;

/**
 * Path utilities for building remote (Unix-like) paths.
 *
 * This helper focuses on *remote* paths where the separator is always "/"
 * (e.g. FTP/SFTP server paths), regardless of the local OS.
 */
final class Path
{
    /**
     * Join a base remote path with a child path segment.
     *
     * Rules:
     * - The returned path uses "/" as separator.
     * - An empty base path is treated as "/" (remote root).
     * - Trailing slashes are removed from the base path.
     * - Leading slashes are removed from the child path.
     * - If the child path is empty after trimming, the base path is returned.
     *
     * Examples:
     * - joinRemote('/var/www', 'file.txt') => '/var/www/file.txt'
     * - joinRemote('/var/www/', '/file.txt') => '/var/www/file.txt'
     * - joinRemote('', 'dir') => '/dir'
     * - joinRemote('/base', '') => '/base'
     *
     * @param string $basePath  Base remote path (absolute or relative). Empty string is treated as "/".
     * @param string $childPath Child remote path/segment to append.
     *
     * @return string Joined remote path.
     */
    public static function joinRemote(string $basePath, string $childPath): string
    {
        $base = \rtrim($basePath === '' ? '/' : $basePath, '/');

        $child = \ltrim($childPath, '/');

        return $child === '' ? $base : $base . '/' . $child;
    }
}
