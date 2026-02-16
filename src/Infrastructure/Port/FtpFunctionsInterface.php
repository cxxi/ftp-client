<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Port;

/**
 * Contract for FTP/FTPS-related operations.
 *
 * This interface abstracts PHP's ext-ftp functions in order to:
 * - Decouple transport logic from global \ftp_* functions
 * - Improve testability through mock implementations
 * - Centralize FTP-specific behavior
 *
 * Implementations typically delegate to PHP's native FTP functions.
 */
interface FtpFunctionsInterface
{
    /**
     * Establish a plain FTP connection.
     *
     * @param string   $host    Remote host.
     * @param int      $port    Remote port (default: 21).
     * @param int|null $timeout Connection timeout in seconds.
     *
     * @return mixed FTP connection handle (resource) or false on failure.
     * @phpstan-return resource|\FTP\Connection|false
     */
    public function connect(string $host, int $port = 21, ?int $timeout = null);

    /**
     * Establish an FTPS (FTP over SSL/TLS) connection.
     *
     * @return mixed FTP connection handle (resource) or false on failure.
     * @phpstan-return resource|\FTP\Connection|false
     */
    public function sslConnect(string $host, int $port = 21, ?int $timeout = null);

    /**
     * Authenticate using username and password.
     */
    public function login(mixed $connection, string $user, string $pass): bool;

    /**
     * Close an FTP connection.
     */
    public function close(mixed $connection): bool;

    /**
     * Retrieve a simple directory listing.
     *
     * @return array<int, string>|false
     */
    public function nlist(mixed $connection, string $dir): array|false;

    /**
     * Download a file from the server.
     *
     * @param int $mode FTP_ASCII or FTP_BINARY.
     */
    public function get(mixed $connection, string $localFilePath, string $remoteFilename, int $mode): bool;

    /**
     * Upload a file to the server.
     *
     * @param int $mode FTP_ASCII or FTP_BINARY.
     */
    public function put(mixed $connection, string $remoteFilename, string $localFilePath, int $mode): bool;

    /**
     * Get the current working directory.
     */
    public function pwd(mixed $connection): string|false;

    /**
     * Change the current working directory.
     */
    public function chdir(mixed $connection, string $directory): bool;

    /**
     * Enable or disable passive mode.
     */
    public function pasv(mixed $connection, bool $enabled): bool;

    /**
     * Delete a remote file.
     */
    public function delete(mixed $connection, string $path): bool;

    /**
     * Create a remote directory.
     *
     * @return string|false The new directory name or false on failure.
     */
    public function mkdir(mixed $connection, string $directory): string|false;

    /**
     * Remove a remote directory.
     */
    public function rmdir(mixed $connection, string $directory): bool;

    /**
     * Rename or move a remote file or directory.
     */
    public function rename(mixed $connection, string $from, string $to): bool;

    /**
     * Get the size of a remote file in bytes.
     *
     * Returns -1 on error (native FTP behavior).
     */
    public function size(mixed $connection, string $path): int;

    /**
     * Get the last modification time of a remote file.
     *
     * Returns a Unix timestamp, or -1 on error.
     */
    public function mdtm(mixed $connection, string $path): int;

    /**
     * Change permissions of a remote file or directory.
     *
     * @return int|false The new permissions or false on failure.
     */
    public function chmod(mixed $connection, int $mode, string $path): int|false;

    /**
     * Retrieve a raw directory listing.
     *
     * @return array<int, string>|false
     */
    public function rawlist(mixed $connection, string $directory, bool $recursive = false): array|false;

    /**
     * Retrieve a machine-readable directory listing (MLSD).
     *
     * @return array<int, mixed>|false
     */
    public function mlsd(mixed $connection, string $directory): array|false;
}
