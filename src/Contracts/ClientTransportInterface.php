<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Contracts;

/**
 * Common transport interface for FTP, FTPS and SFTP clients.
 *
 * This interface defines a unified set of filesystem-like operations
 * supported by all transport implementations.
 *
 * Implementations are expected to:
 * - Establish and manage a remote connection
 * - Handle authentication
 * - Provide file and directory operations
 * - Throw transport-specific exceptions on failure
 */
interface ClientTransportInterface
{
    /**
     * Establish the connection to the remote server.
     *
     * @return static
     */
    public function connect(): static;

    /**
     * Whether the underlying connection has been successfully established.
     */
    public function isConnected(): bool;

    /**
     * Whether the client has successfully authenticated.
     */
    public function isAuthenticated(): bool;

    /**
     * Authenticate using username and password.
     *
     * If parameters are null, implementation may use credentials
     * defined in the connection URL.
     *
     * @param string|null $user Username override.
     * @param string|null $pass Password override.
     *
     * @return static
     */
    public function loginWithPassword(?string $user = null, ?string $pass = null): static;

    /**
     * Upload a local file to the remote server.
     *
     * @param string $destinationFilename Remote destination path or filename.
     * @param string $sourceFilePath Local file path.
     *
     * @return static
     */
    public function putFile(string $destinationFilename, string $sourceFilePath): static;

    /**
     * List files in a remote directory.
     *
     * @param string $remoteDir Remote directory path (default: current directory).
     *
     * @return array<int, string> List of filenames.
     */
    public function listFiles(string $remoteDir = '.'): array;

    /**
     * Download a remote file to a local path.
     *
     * @param string $remoteFilename Remote file path.
     * @param string $localFilePath Local destination file path.
     *
     * @return static
     */
    public function downloadFile(string $remoteFilename, string $localFilePath): static;

    /**
     * Check whether a remote path is a directory.
     *
     * @param string $remotePath Remote path to check.
     */
    public function isDirectory(string $remotePath): bool;

    /**
     * Delete a remote file.
     *
     * @param string $remotePath Remote file path.
     *
     * @return static
     */
    public function deleteFile(string $remotePath): static;

    /**
     * Create a remote directory.
     *
     * @param string $remoteDir Remote directory path.
     * @param bool $recursive Whether to create parent directories if needed.
     *
     * @return static
     */
    public function makeDirectory(string $remoteDir, bool $recursive = true): static;

    /**
     * Remove a remote directory (non-recursive).
     *
     * @param string $remoteDir Remote directory path.
     *
     * @return static
     */
    public function removeDirectory(string $remoteDir): static;

    /**
     * Remove a remote directory and all its contents recursively.
     *
     * @param string $remoteDir Remote directory path.
     *
     * @return static
     */
    public function removeDirectoryRecursive(string $remoteDir): static;

    /**
     * Rename or move a remote file or directory.
     *
     * @param string $from Source path.
     * @param string $to Destination path.
     *
     * @return static
     */
    public function rename(string $from, string $to): static;

    /**
     * Retrieve the size of a remote file.
     *
     * @param string $remotePath Remote file path.
     *
     * @return int|null File size in bytes, or null if unavailable.
     */
    public function getSize(string $remotePath): ?int;

    /**
     * Retrieve the modification time of a remote file.
     *
     * @param string $remotePath Remote file path.
     *
     * @return int|null Unix timestamp, or null if unavailable.
     */
    public function getMTime(string $remotePath): ?int;

    /**
     * Change permissions of a remote file or directory.
     *
     * @param string $remotePath Remote path.
     * @param int $mode POSIX permission mode (e.g. 0755).
     *
     * @return static
     */
    public function chmod(string $remotePath, int $mode): static;

    /**
     * Close the connection and release associated resources.
     */
    public function closeConnection(): void;
}
