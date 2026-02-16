<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Service;

use Cxxi\FtpClient\Contracts\ClientTransportInterface;
use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Exception\FtpClientException;
use Cxxi\FtpClient\Exception\MissingExtensionException;
use Cxxi\FtpClient\Exception\TransferException;
use Cxxi\FtpClient\Infrastructure\Native\NativeFilesystemFunctions;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Util\WarningCatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Base implementation for FTP-like transports.
 *
 * This abstract client encapsulates:
 * - Connection/session state (connected & authenticated flags)
 * - Common transfer operations with consistent logging
 * - Retry strategy (safe vs. unsafe operations) with backoff/jitter
 * - A filesystem abstraction for local I/O checks/creation
 * - Warning capturing for native PHP warnings emitted by filesystem calls
 *
 * Concrete transports (FTP, FTPS, SFTP, ...) must implement the low-level
 * {@see do*} methods that perform the actual protocol-specific work.
 */
abstract class AbstractClient implements ClientTransportInterface
{
    /**
     * Underlying transport connection handle.
     *
     * Typically a native resource returned by the underlying extension.
     *
     * @var resource|false|null
     */
    protected $connection = null;

    /**
     * Whether the session has been successfully authenticated.
     *
     * This flag is expected to be managed by concrete implementations during
     * connect/login flows.
     */
    protected bool $authenticated = false;

    /**
     * Captures/records the last PHP warning, to be used for diagnostics (e.g. retry logs).
     */
    protected readonly WarningCatcher $warnings;

    /**
     * Transport protocol extracted from the provided URL.
     */
    protected Protocol $protocol;

    /**
     * Remote host extracted from the provided URL.
     */
    protected string $host;

    /**
     * Remote port extracted from the provided URL (if any).
     */
    protected ?int $port;

    /**
     * Username extracted from the provided URL (if any).
     */
    protected ?string $user;

    /**
     * Password extracted from the provided URL (if any).
     */
    protected ?string $pass;

    /**
     * Path extracted from the provided URL.
     *
     * Depending on the transport, this may represent a base directory.
     */
    protected string $path;

    /**
     * Connection and retry options.
     */
    protected ConnectionOptions $options;

    /**
     * PSR-3 logger used for transfer diagnostics.
     */
    protected LoggerInterface $logger;

    /**
     * Filesystem abstraction used for local file checks and directory creation.
     */
    protected readonly FilesystemFunctionsInterface $fs;

    /**
     * @param FtpUrl $url        Parsed FTP URL (protocol, host, port, credentials, path).
     * @param ConnectionOptions|null $options Transfer and retry options (defaults to a new instance).
     * @param LoggerInterface|null $logger    PSR-3 logger (defaults to {@see NullLogger}).
     * @param FilesystemFunctionsInterface|null $fs Filesystem adapter (defaults to {@see NativeFilesystemFunctions}).
     * @param WarningCatcher|null $warnings   Warning catcher (defaults to a new instance).
     */
    public function __construct(
        FtpUrl $url,
        ?ConnectionOptions $options = null,
        ?LoggerInterface $logger = null,
        ?FilesystemFunctionsInterface $fs = null,
        ?WarningCatcher $warnings = null
    ) {
        $this->protocol = $url->protocol;
        $this->host = $url->host;
        $this->port = $url->port;
        $this->user = $url->user;
        $this->pass = $url->pass;
        $this->path = $url->path;

        $this->warnings = $warnings ?? new WarningCatcher();
        $this->options = $options ?? new ConnectionOptions();
        $this->logger = $logger ?? new NullLogger();

        $this->fs = $fs ?? new NativeFilesystemFunctions();
    }

    /**
     * Whether the client currently has an open connection handle.
     */
    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection !== false;
    }

    /**
     * Whether the session has been authenticated.
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * Upload a local file to a remote destination.
     *
     * @param string $destinationFilename Remote destination path/filename.
     * @param string $sourceFilePath      Local source file path.
     *
     * @return static
     *
     * @throws TransferException If the client is not ready, the local file does not exist,
     *                           is not readable, or the underlying transfer fails.
     */
    public function putFile(string $destinationFilename, string $sourceFilePath): static
    {
        $this->assertReadyForTransfer('upload file');

        $exists = $this->warnings->run(fn () => $this->fs->fileExists($sourceFilePath));
        $readable = $this->warnings->run(fn () => $this->fs->isReadable($sourceFilePath));

        if (!$exists || !$readable) {
            throw new TransferException(sprintf(
                'Local file "%s" does not exist or is not readable.',
                $sourceFilePath
            ));
        }

        $this->logger->info('Transport putFile', [
            'host' => $this->host,
            'local' => $sourceFilePath,
            'remote' => $destinationFilename,
        ]);

        $this->withRetry('putFile', fn () => $this->doPutFile($destinationFilename, $sourceFilePath), safe: false);

        $this->logger->info('Transport putFile ok', [
            'host' => $this->host,
            'local' => $sourceFilePath,
            'remote' => $destinationFilename,
        ]);

        return $this;
    }

    /**
     * List files in a remote directory.
     *
     * @param string $remoteDir Remote directory to list. Defaults to ".".
     *
     * @return array<int, string> List of filenames/paths returned by the transport.
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function listFiles(string $remoteDir = '.'): array
    {
        $this->assertReadyForTransfer('list files');

        /** @var array<int, string> $files */
        $files = $this->withRetry('listFiles', fn () => $this->doListFiles($remoteDir), safe: true);

        $this->logger->debug('Transport listFiles ok', [
            'host' => $this->host,
            'remoteDir' => $remoteDir,
            'count' => count($files),
        ]);

        return $files;
    }

    /**
     * Download a remote file to a local path.
     *
     * If the local directory does not exist, it will be created recursively.
     *
     * @param string $remoteFilename Remote source path/filename.
     * @param string $localFilePath  Local destination file path.
     *
     * @return static
     *
     * @throws TransferException If the client is not ready, the local directory cannot be created,
     *                           or the underlying transfer fails.
     */
    public function downloadFile(string $remoteFilename, string $localFilePath): static
    {
        $this->assertReadyForTransfer('download file');

        $localDir = $this->fs->dirname($localFilePath);

        $isDir = $this->warnings->run(fn () => $this->fs->isDir($localDir));
        if (!$isDir) {
            $ok = $this->warnings->run(fn () => $this->fs->mkdir($localDir, 0775, true));
            $isDirAfter = $this->warnings->run(fn () => $this->fs->isDir($localDir));

            if (!$ok && !$isDirAfter) {
                throw new TransferException(sprintf('Unable to create local directory "%s".', $localDir));
            }
        }

        $this->logger->info('Transport downloadFile', [
            'host' => $this->host,
            'remote' => $remoteFilename,
            'local' => $localFilePath,
        ]);

        $this->withRetry('downloadFile', fn () => $this->doDownloadFile($remoteFilename, $localFilePath), safe: true);

        $this->logger->info('Transport downloadFile ok', [
            'host' => $this->host,
            'remote' => $remoteFilename,
            'local' => $localFilePath,
        ]);

        return $this;
    }

    /**
     * Determine whether a given remote path is a directory.
     *
     * @param string $remotePath Remote path to check.
     *
     * @return bool True if the path is a directory, false otherwise.
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function isDirectory(string $remotePath): bool
    {
        $this->assertReadyForTransfer('check directory');
        return $this->withRetry('isDirectory', fn () => $this->doIsDirectory($remotePath), safe: true);
    }

    /**
     * Delete a remote file.
     *
     * @param string $remotePath Remote file path.
     *
     * @return static
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function deleteFile(string $remotePath): static
    {
        $this->assertReadyForTransfer('delete file');

        $this->logger->info('Transport deleteFile', ['host' => $this->host, 'remote' => $remotePath]);

        $this->withRetry('deleteFile', fn () => $this->doDeleteFile($remotePath), safe: false);

        $this->logger->info('Transport deleteFile ok', ['host' => $this->host, 'remote' => $remotePath]);
        return $this;
    }

    /**
     * Create a remote directory.
     *
     * @param string $remoteDir  Remote directory path.
     * @param bool $recursive    Whether to create intermediate directories.
     *
     * @return static
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function makeDirectory(string $remoteDir, bool $recursive = true): static
    {
        $this->assertReadyForTransfer('make directory');

        $this->logger->info('Transport makeDirectory', [
            'host' => $this->host,
            'remoteDir' => $remoteDir,
            'recursive' => $recursive,
        ]);

        $this->withRetry('makeDirectory', fn () => $this->doMakeDirectory($remoteDir, $recursive), safe: false);

        $this->logger->info('Transport makeDirectory ok', ['host' => $this->host, 'remoteDir' => $remoteDir]);
        return $this;
    }

    /**
     * Remove a remote directory.
     *
     * @param string $remoteDir Remote directory path.
     *
     * @return static
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function removeDirectory(string $remoteDir): static
    {
        $this->assertReadyForTransfer('remove directory');

        $this->logger->info('Transport removeDirectory', ['host' => $this->host, 'remoteDir' => $remoteDir]);

        $this->withRetry('removeDirectory', fn () => $this->doRemoveDirectory($remoteDir), safe: false);

        $this->logger->info('Transport removeDirectory ok', ['host' => $this->host, 'remoteDir' => $remoteDir]);
        return $this;
    }

    /**
     * Remove a remote directory recursively (dangerous operation).
     *
     * This method performs basic safety checks and refuses to delete common unsafe targets
     * such as empty paths, ".", ".." or "/".
     *
     * @param string $remoteDir Remote directory path.
     *
     * @return static
     *
     * @throws TransferException If the target is unsafe, the client is not ready, or the underlying operation fails.
     */
    public function removeDirectoryRecursive(string $remoteDir): static
    {
        $this->assertReadyForTransfer('remove directory recursively');

        $trimmed = trim($remoteDir);
        if ($trimmed === '' || $trimmed === '.' || $trimmed === '..' || $trimmed === '/') {
            throw new TransferException('Refusing to remove directory recursively: invalid or unsafe target.');
        }

        $this->logger->warning('Transport removeDirectoryRecursive', [
            'host' => $this->host,
            'remoteDir' => $remoteDir,
        ]);

        $this->withRetry('removeDirectoryRecursive', fn () => $this->doRemoveDirectoryRecursive($remoteDir), safe: false);

        $this->logger->warning('Transport removeDirectoryRecursive ok', [
            'host' => $this->host,
            'remoteDir' => $remoteDir,
        ]);

        return $this;
    }

    /**
     * Rename (or move) a remote path.
     *
     * @param string $from Source remote path.
     * @param string $to   Destination remote path.
     *
     * @return static
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function rename(string $from, string $to): static
    {
        $this->assertReadyForTransfer('rename');

        $this->logger->info('Transport rename', ['host' => $this->host, 'from' => $from, 'to' => $to]);

        $this->withRetry('rename', fn () => $this->doRename($from, $to), safe: false);

        $this->logger->info('Transport rename ok', ['host' => $this->host, 'from' => $from, 'to' => $to]);
        return $this;
    }

    /**
     * Get the size of a remote file in bytes.
     *
     * @param string $remotePath Remote file path.
     *
     * @return int|null File size in bytes, or null if not available.
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function getSize(string $remotePath): ?int
    {
        $this->assertReadyForTransfer('get size');
        return $this->withRetry('getSize', fn () => $this->doGetSize($remotePath), safe: true);
    }

    /**
     * Get the last modification time of a remote file (Unix timestamp).
     *
     * @param string $remotePath Remote file path.
     *
     * @return int|null Unix timestamp, or null if not available.
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function getMTime(string $remotePath): ?int
    {
        $this->assertReadyForTransfer('get mtime');
        return $this->withRetry('getMTime', fn () => $this->doGetMTime($remotePath), safe: true);
    }

    /**
     * Change permissions of a remote path.
     *
     * @param string $remotePath Remote path.
     * @param int $mode          Permission mode (e.g. 0644).
     *
     * @return static
     *
     * @throws TransferException If the client is not ready or the underlying operation fails.
     */
    public function chmod(string $remotePath, int $mode): static
    {
        $this->assertReadyForTransfer('chmod');

        $this->logger->info('Transport chmod', [
            'host' => $this->host,
            'remotePath' => $remotePath,
            'mode' => $mode,
        ]);

        $this->withRetry('chmod', fn () => $this->doChmod($remotePath, $mode), safe: false);

        $this->logger->info('Transport chmod ok', [
            'host' => $this->host,
            'remotePath' => $remotePath,
            'mode' => $mode,
        ]);

        return $this;
    }

    /**
     * Low-level implementation of {@see listFiles()}.
     *
     * @param string $remoteDir Remote directory to list.
     *
     * @return array<int, string>
     */
    abstract protected function doListFiles(string $remoteDir): array;

    /**
     * Low-level implementation of {@see downloadFile()}.
     *
     * @param string $remoteFilename Remote source file.
     * @param string $localFilePath  Local destination path.
     */
    abstract protected function doDownloadFile(string $remoteFilename, string $localFilePath): void;

    /**
     * Low-level implementation of {@see putFile()}.
     *
     * @param string $destinationFilename Remote destination path/filename.
     * @param string $sourceFilePath      Local source file path.
     */
    abstract protected function doPutFile(string $destinationFilename, string $sourceFilePath): void;

    /**
     * Low-level implementation of {@see isDirectory()}.
     *
     * @param string $remotePath Remote path to check.
     */
    abstract protected function doIsDirectory(string $remotePath): bool;

    /**
     * Low-level implementation of {@see deleteFile()}.
     *
     * @param string $remotePath Remote file path.
     */
    abstract protected function doDeleteFile(string $remotePath): void;

    /**
     * Low-level implementation of {@see makeDirectory()}.
     *
     * @param string $remoteDir Remote directory path.
     * @param bool $recursive   Whether to create intermediate directories.
     */
    abstract protected function doMakeDirectory(string $remoteDir, bool $recursive): void;

    /**
     * Low-level implementation of {@see removeDirectory()}.
     *
     * @param string $remoteDir Remote directory path.
     */
    abstract protected function doRemoveDirectory(string $remoteDir): void;

    /**
     * Low-level implementation of {@see removeDirectoryRecursive()}.
     *
     * @param string $remoteDir Remote directory path.
     */
    abstract protected function doRemoveDirectoryRecursive(string $remoteDir): void;

    /**
     * Low-level implementation of {@see rename()}.
     *
     * @param string $from Source remote path.
     * @param string $to   Destination remote path.
     */
    abstract protected function doRename(string $from, string $to): void;

    /**
     * Low-level implementation of {@see getSize()}.
     *
     * @param string $remotePath Remote file path.
     *
     * @return int|null
     */
    abstract protected function doGetSize(string $remotePath): ?int;

    /**
     * Low-level implementation of {@see getMTime()}.
     *
     * @param string $remotePath Remote file path.
     *
     * @return int|null
     */
    abstract protected function doGetMTime(string $remotePath): ?int;

    /**
     * Low-level implementation of {@see chmod()}.
     *
     * @param string $remotePath Remote path.
     * @param int $mode          Permission mode.
     */
    abstract protected function doChmod(string $remotePath, int $mode): void;

    /**
     * Ensure the client is connected and authenticated before attempting a transfer.
     *
     * @param string $operation Human-readable operation name used in error messages.
     *
     * @throws TransferException If the connection is not established or not authenticated.
     */
    protected function assertReadyForTransfer(string $operation): void
    {
        if (!$this->isConnected()) {
            throw new TransferException(sprintf('Cannot %s: connection has not been established yet.', $operation));
        }
        if (!$this->isAuthenticated()) {
            throw new TransferException(sprintf('Cannot %s: session is not authenticated.', $operation));
        }
    }

    /**
     * Execute an operation with retry logic.
     *
     * Retries are applied only when:
     * - {@see ConnectionOptions::$retryMax} is > 0
     * - and either the operation is considered "safe" (idempotent/read-only),
     *   or unsafe retries are explicitly enabled via {@see ConnectionOptions::$retryUnsafeOperations}.
     *
     * Only exceptions that implement {@see \Cxxi\FtpClient\Exception\FtpClientException} are retried.
     * {@see \Cxxi\FtpClient\Exception\MissingExtensionException} is never retried.
     *
     * @template T
     *
     * @param string $operation Operation name used for logs.
     * @param callable():T $fn  Callback performing the operation.
     * @param bool $safe        Whether the operation is safe to retry (read-only/idempotent).
     *
     * @return T
     *
     * @throws \Throwable Re-throws the last failure when retries are exhausted or non-retriable.
     */
    protected function withRetry(string $operation, callable $fn, bool $safe = true): mixed
    {
        $max = max(0, $this->options->retryMax);

        if ($max === 0) {
            return $fn();
        }

        if (!$safe && !$this->options->retryUnsafeOperations) {
            return $fn();
        }

        $delayMs = max(0, $this->options->retryDelayMs);

        $backoff = $this->options->retryBackoff;
        if ($backoff <= 0) {
            $backoff = 2.0;
        }

        $jitter = $this->options->retryJitter;

        $attempt = 0;
        $sleepMs = $delayMs;

        while (true) {
            try {
                return $fn();
            } catch (\Throwable $e) {

                if (!$e instanceof FtpClientException) {
                    throw $e;
                }

                if ($e instanceof MissingExtensionException) {
                    throw $e;
                }

                $attempt++;

                if ($attempt > $max) {
                    throw $e;
                }

                $this->logger->warning('Transport retry', [
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'max' => $max,
                    'sleepMs' => $sleepMs,
                    'error' => $e::class . ': ' . $e->getMessage(),
                    'warning' => $this->warnings->formatLastWarning(),
                ]);

                if ($sleepMs > 0) {
                    $actual = $sleepMs;

                    if ($jitter) {
                        $factor = mt_rand(50, 150) / 100;
                        $actual = (int) round($sleepMs * $factor);
                    }

                    usleep(max(0, $actual) * 1000);
                }

                $sleepMs = (int) round($sleepMs * $backoff);
            }
        }
    }
}
