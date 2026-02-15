<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\FtpFunctionsInterface;

/**
 * Native implementation of {@see FtpFunctionsInterface}.
 *
 * This class provides a thin abstraction over PHP's ext-ftp functions.
 * It allows transport services to depend on an interface rather than
 * directly calling global FTP functions, improving testability and decoupling.
 *
 * All methods delegate to the corresponding \ftp_* function.
 */
final class NativeFtpFunctions implements FtpFunctionsInterface
{
    /**
     * {@inheritDoc}
     *
     * Establish a plain FTP connection.
     */
    public function connect(string $host, int $port = 21, ?int $timeout = null): mixed
    {
        return $timeout === null
            ? \ftp_connect($host, $port)
            : \ftp_connect($host, $port, $timeout);
    }

    /**
     * {@inheritDoc}
     *
     * Establish an FTPS (FTP over SSL/TLS) connection.
     */
    public function sslConnect(string $host, int $port = 21, ?int $timeout = null): mixed
    {
        return $timeout === null
            ? \ftp_ssl_connect($host, $port)
            : \ftp_ssl_connect($host, $port, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function login(mixed $connection, string $user, string $pass): bool
    {
        return \ftp_login($connection, $user, $pass);
    }

    /**
     * {@inheritDoc}
     */
    public function close(mixed $connection): bool
    {
        return \ftp_close($connection);
    }

    /**
     * {@inheritDoc}
     */
    public function nlist(mixed $connection, string $dir): array|false
    {
        return \ftp_nlist($connection, $dir);
    }

    /**
     * {@inheritDoc}
     *
     * Downloads a file from the FTP server.
     */
    public function get(mixed $connection, string $localFilePath, string $remoteFilename, int $mode): bool
    {
        $mode = $mode === \FTP_ASCII ? \FTP_ASCII : \FTP_BINARY;

        return \ftp_get($connection, $localFilePath, $remoteFilename, $mode);
    }

    /**
     * {@inheritDoc}
     *
     * Uploads a file to the FTP server.
     */
    public function put(mixed $connection, string $remoteFilename, string $localFilePath, int $mode): bool
    {
        $mode = $mode === \FTP_ASCII ? \FTP_ASCII : \FTP_BINARY;

        return \ftp_put($connection, $remoteFilename, $localFilePath, $mode);
    }

    /**
     * {@inheritDoc}
     */
    public function pwd(mixed $connection): string|false
    {
        return \ftp_pwd($connection);
    }

    /**
     * {@inheritDoc}
     */
    public function chdir(mixed $connection, string $directory): bool
    {
        return \ftp_chdir($connection, $directory);
    }

    /**
     * {@inheritDoc}
     */
    public function pasv(mixed $connection, bool $enabled): bool
    {
        return \ftp_pasv($connection, $enabled);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(mixed $connection, string $path): bool
    {
        return \ftp_delete($connection, $path);
    }

    /**
     * {@inheritDoc}
     */
    public function mkdir(mixed $connection, string $directory): string|false
    {
        return \ftp_mkdir($connection, $directory);
    }

    /**
     * {@inheritDoc}
     */
    public function rmdir(mixed $connection, string $directory): bool
    {
        return \ftp_rmdir($connection, $directory);
    }

    /**
     * {@inheritDoc}
     */
    public function rename(mixed $connection, string $from, string $to): bool
    {
        return \ftp_rename($connection, $from, $to);
    }

    /**
     * {@inheritDoc}
     *
     * Returns file size in bytes, or -1 on error.
     */
    public function size(mixed $connection, string $path): int
    {
        return \ftp_size($connection, $path);
    }

    /**
     * {@inheritDoc}
     *
     * Returns the last modification time as a Unix timestamp,
     * or -1 on error.
     */
    public function mdtm(mixed $connection, string $path): int
    {
        return \ftp_mdtm($connection, $path);
    }

    /**
     * {@inheritDoc}
     *
     * Change permissions of a file or directory.
     */
    public function chmod(mixed $connection, int $mode, string $path): int|false
    {
        return \ftp_chmod($connection, $mode, $path);
    }

    /**
     * {@inheritDoc}
     *
     * Retrieve a raw directory listing.
     */
    public function rawlist(mixed $connection, string $directory, bool $recursive = false): array|false
    {
        return \ftp_rawlist($connection, $directory, $recursive);
    }

    /**
     * {@inheritDoc}
     *
     * Retrieve a machine-readable directory listing (MLSD).
     *
     * Returns false if ftp_mlsd() is not available.
     */
    public function mlsd(mixed $connection, string $directory): array|false
    {
        if (!\function_exists('ftp_mlsd')) {
            return false;
        }

        return \ftp_mlsd($connection, $directory);
    }
}
