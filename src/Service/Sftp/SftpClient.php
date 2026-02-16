<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Service\Sftp;

use Cxxi\FtpClient\Contracts\SftpClientTransportInterface;
use Cxxi\FtpClient\Enum\HostKeyAlgo;
use Cxxi\FtpClient\Exception\AuthenticationException;
use Cxxi\FtpClient\Exception\ConnectionException;
use Cxxi\FtpClient\Exception\MissingExtensionException;
use Cxxi\FtpClient\Exception\TransferException;
use Cxxi\FtpClient\Infrastructure\Native\NativeExtensionChecker;
use Cxxi\FtpClient\Infrastructure\Native\NativeSsh2Functions;
use Cxxi\FtpClient\Infrastructure\Native\NativeStreamFunctions;
use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\Ssh2FunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\StreamFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Service\AbstractClient;
use Cxxi\FtpClient\Util\Path;
use Cxxi\FtpClient\Util\WarningCatcher;
use Psr\Log\LoggerInterface;

/**
 * SFTP transport client implementation (SSH File Transfer Protocol).
 *
 * This client relies on PHP's ext-ssh2 (via {@see Ssh2FunctionsInterface}) and uses the
 * ssh2.sftp stream wrapper for file transfers and directory listing (via {@see StreamFunctionsInterface}).
 *
 * Key features:
 * - Connection establishment with configurable host key algorithm
 * - Optional strict host key checking using an expected MD5/SHA1 fingerprint (as supported by ext-ssh2)
 * - Password and public key authentication
 * - Common filesystem operations (list, upload, download, delete, mkdir, rmdir, chmod, stat-based checks)
 * - Uses {@see AbstractClient::withRetry()} for retriable, protocol-specific operations
 */
final class SftpClient extends AbstractClient implements SftpClientTransportInterface
{
    /**
     * Default host key algorithm used when not configured in {@see ConnectionOptions}.
     */
    private const DEFAULT_HOST_KEY_ALGO = HostKeyAlgo::SSH_RSA;

    /**
     * Supported fingerprint algorithms via ext-ssh2.
     */
    public const FINGERPRINT_ALGO_MD5 = 'md5';
    public const FINGERPRINT_ALGO_SHA1 = 'sha1';

    /**
     * Extension checker used to verify that required PHP extensions are loaded.
     */
    private readonly ExtensionCheckerInterface $extensions;

    /**
     * SSH2 functions adapter (usually backed by ext-ssh2 native functions).
     */
    private readonly Ssh2FunctionsInterface $ssh2;

    /**
     * Stream functions adapter used for the ssh2.sftp stream wrapper operations.
     */
    private readonly StreamFunctionsInterface $streams;

    /**
     * @param FtpUrl $url Parsed SFTP URL (protocol, host, port, credentials, path).
     * @param ConnectionOptions|null $options Connection and retry options.
     * @param LoggerInterface|null $logger PSR-3 logger.
     * @param ExtensionCheckerInterface|null $extensions Extension checker (defaults to {@see NativeExtensionChecker}).
     * @param Ssh2FunctionsInterface|null $ssh2 SSH2 adapter (defaults to {@see NativeSsh2Functions}).
     * @param StreamFunctionsInterface|null $streams Stream adapter (defaults to {@see NativeStreamFunctions}).
     * @param FilesystemFunctionsInterface|null $fs Filesystem adapter.
     * @param WarningCatcher|null $warnings Warning catcher for native warnings.
     */
    public function __construct(
        FtpUrl $url,
        ?ConnectionOptions $options = null,
        ?LoggerInterface $logger = null,
        ?ExtensionCheckerInterface $extensions = null,
        ?Ssh2FunctionsInterface $ssh2 = null,
        ?StreamFunctionsInterface $streams = null,
        ?FilesystemFunctionsInterface $fs = null,
        ?WarningCatcher $warnings = null
    ) {
        parent::__construct($url, $options, $logger, $fs, $warnings);

        $this->extensions = $extensions ?? new NativeExtensionChecker();
        $this->ssh2 = $ssh2 ?? new NativeSsh2Functions();
        $this->streams = $streams ?? new NativeStreamFunctions();
    }

    /**
     * Best-effort resource cleanup.
     *
     * Any exception thrown while closing is intentionally swallowed.
     */
    public function __destruct()
    {
        try {
            $this->closeConnection();
        } catch (\Throwable) {
        }
    }

    /**
     * Assert that ext-ssh2 is available.
     *
     * @throws MissingExtensionException If the "ssh2" extension is not loaded.
     */
    private function assertSsh2Extension(): void
    {
        if (!$this->extensions->loaded('ssh2')) {
            throw new MissingExtensionException('ext-ssh2 is required for SFTP operations.');
        }
    }

    /**
     * Establish the SSH connection and optionally validate the server host key.
     *
     * Host key validation is controlled by:
     * - {@see ConnectionOptions::$strictHostKeyChecking}
     * - {@see ConnectionOptions::$expectedFingerprint}
     *
     * @return static
     *
     * @throws MissingExtensionException If ext-ssh2 is missing.
     * @throws ConnectionException If connection fails or host key validation fails.
     */
    public function connect(): static
    {
        $this->assertSsh2Extension();

        $rawHostKeyAlgo = $this->options->hostKeyAlgo ?? self::DEFAULT_HOST_KEY_ALGO;

        $hostKeyAlgo = $rawHostKeyAlgo instanceof HostKeyAlgo
            ? $rawHostKeyAlgo->value
            : $rawHostKeyAlgo;

        $this->logger->info('SFTP transport connecting', [
            'protocol' => $this->protocol->value,
            'host' => $this->host,
            'port' => $this->port ?? 22,
            'path' => $this->path,
            'hostKeyAlgo' => $hostKeyAlgo,
            'strictHostKeyChecking' => $this->options->strictHostKeyChecking,
            'hasExpectedFingerprint' => $this->options->expectedFingerprint !== null,
            'timeout' => $this->options->timeout,
        ]);

        $this->withRetry('connect', function () use ($hostKeyAlgo): void {

            $this->connection = $this->warnings->run(
                fn () => $this->ssh2->connect(
                    $this->host,
                    $this->port ?? 22,
                    ['hostkey' => $hostKeyAlgo]
                )
            );

            if (!$this->isConnected()) {

                $this->logger->error('SFTP transport connection failed', [
                    'protocol' => $this->protocol->value,
                    'host' => $this->host,
                    'port' => $this->port ?? 22,
                    'path' => $this->path,
                    'hostKeyAlgo' => $hostKeyAlgo,
                    'warning' => $this->warnings->formatLastWarning(),
                ]);

                throw new ConnectionException(sprintf(
                    'Unable to connect to server "%s" (SFTP).%s',
                    $this->host,
                    $this->warnings->formatLastWarning()
                ));
            }

            $this->assertHostKeyVerified();

        }, safe: true);

        $this->logger->info('SFTP transport connected', [
            'protocol' => $this->protocol->value,
            'host' => $this->host,
            'port' => $this->port ?? 22,
            'path' => $this->path,
        ]);

        return $this;
    }

    /**
     * Authenticate using username and password.
     *
     * If $user or $pass are null, values from the URL are used.
     *
     * @param string|null $user Username override (defaults to the URL username).
     * @param string|null $pass Password override (defaults to the URL password).
     *
     * @return static
     *
     * @throws MissingExtensionException If ext-ssh2 is missing.
     * @throws AuthenticationException If not connected, credentials are missing, or authentication fails.
     */
    public function loginWithPassword(?string $user = null, ?string $pass = null): static
    {
        if (!$this->isConnected()) {
            throw new AuthenticationException('Cannot login: connection not established yet.');
        }

        $this->assertSsh2Extension();

        $user ??= $this->user;
        $pass ??= $this->pass;

        if ($user === null || $user === '' || $pass === null || $pass === '') {
            throw new AuthenticationException('Missing username or password for login.');
        }

        $this->withRetry('loginWithPassword', function () use ($user, $pass): void {

            $ok = $this->warnings->run(fn () => $this->ssh2->authPassword($this->connection, $user, $pass));

            if (!$ok) {
                throw new AuthenticationException(sprintf(
                    'Login failed on "%s" for user "%s".%s',
                    $this->host,
                    $user,
                    $this->warnings->formatLastWarning()
                ));
            }

        }, safe: true);

        $this->user = $user;
        $this->pass = $pass;
        $this->authenticated = true;

        return $this;
    }

    /**
     * Authenticate using a public/private key pair stored on disk.
     *
     * @param string $pubkeyFile  Path to the public key file.
     * @param string $privkeyFile Path to the private key file.
     * @param string|null $user   Username override (defaults to the URL username).
     *
     * @return static
     *
     * @throws MissingExtensionException If ext-ssh2 is missing.
     * @throws AuthenticationException If not connected, username is missing, key files do not exist/readable,
     *                                 or authentication fails.
     */
    public function loginWithPubkey(string $pubkeyFile, string $privkeyFile, ?string $user = null): static
    {
        if (!$this->isConnected()) {
            throw new AuthenticationException('Cannot login: connection not established yet.');
        }

        $this->assertSsh2Extension();

        $pubExists = $this->warnings->run(fn () => $this->fs->fileExists($pubkeyFile));
        $privExists = $this->warnings->run(fn () => $this->fs->fileExists($privkeyFile));

        if (!$pubExists || !$privExists) {
            throw new AuthenticationException('Public or private key file does not exist.');
        }

        $pubReadable = $this->warnings->run(fn () => $this->fs->isReadable($pubkeyFile));
        $privReadable = $this->warnings->run(fn () => $this->fs->isReadable($privkeyFile));

        if (!$pubReadable || !$privReadable) {
            throw new AuthenticationException('Public or private key file is not readable.');
        }

        $user ??= $this->user;
        if ($user === null || $user === '') {
            throw new AuthenticationException('Username must be provided for public key authentication.');
        }

        $this->logger->info('SFTP transport authenticating (pubkey)', [
            'protocol' => $this->protocol->value,
            'host' => $this->host,
            'user' => $user,
            'pubkey' => $pubkeyFile,
            'privkey' => $privkeyFile,
        ]);

        $this->withRetry('loginWithPubkey', function () use ($user, $pubkeyFile, $privkeyFile): void {

            $ok = $this->warnings->run(
                fn () => $this->ssh2->authPubkeyFile($this->connection, $user, $pubkeyFile, $privkeyFile)
            );

            if (!$ok) {

                $this->logger->warning('SFTP transport authentication failed (pubkey)', [
                    'protocol' => $this->protocol->value,
                    'host' => $this->host,
                    'user' => $user,
                    'pubkey' => $pubkeyFile,
                    'privkey' => $privkeyFile,
                    'warning' => $this->warnings->formatLastWarning(),
                ]);

                throw new AuthenticationException(sprintf(
                    'Public key authentication failed for user "%s" on host "%s".%s',
                    $user,
                    $this->host,
                    $this->warnings->formatLastWarning()
                ));
            }

        }, safe: true);

        $this->user = $user;
        $this->authenticated = true;

        $this->logger->info('SFTP transport authenticated (pubkey)', [
            'protocol' => $this->protocol->value,
            'host' => $this->host,
            'user' => $user,
        ]);

        return $this;
    }

    /**
     * Close the connection and reset session state.
     *
     * Note: ext-ssh2 does not expose an explicit "disconnect" call; releasing the
     * connection handle is sufficient.
     *
     * This method is safe to call multiple times.
     */
    public function closeConnection(): void
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->logger->debug('SFTP transport closing connection', [
            'protocol' => $this->protocol->value,
            'host' => $this->host,
            'port' => $this->port ?? 22,
        ]);

        $this->connection = null;
        $this->authenticated = false;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, string>
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     * @throws TransferException If the remote directory cannot be opened.
     */
    protected function doListFiles(string $remoteDir): array
    {
        $this->assertReadyForTransfer('list files');
        $this->assertSsh2Extension();

        $sftp = $this->warnings->run(fn () => $this->ssh2->sftp($this->connection));
        if ($sftp === false || $sftp === null) {
            throw new ConnectionException('Failed to initialize SFTP subsystem.');
        }

        $dirPath = rtrim($this->normalizeRemotePath($remoteDir), '/');
        $uri = sprintf('ssh2.sftp://%d/%s', (int) $sftp, ltrim($dirPath, '/'));

        $handle = $this->warnings->run(fn () => $this->streams->opendir($uri));
        if ($handle === false) {
            throw new TransferException(sprintf(
                'Unable to open remote directory "%s" on "%s".',
                $dirPath,
                $this->host
            ));
        }

        try {
            $files = [];

            while (true) {
                $entry = $this->warnings->run(fn () => $this->streams->readdir($handle));
                if ($entry === false) {
                    break;
                }

                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                    continue;
                }

                $files[] = $entry;
            }

            return $files;
        } finally {
            $this->warnings->run(fn () => $this->streams->closedir($handle));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     * @throws TransferException If the remote file or local file cannot be opened, or if the copy fails.
     */
    protected function doDownloadFile(string $remoteFilename, string $localFilePath): void
    {
        $this->assertReadyForTransfer('download file');
        $this->assertSsh2Extension();

        $sftp = $this->warnings->run(fn () => $this->ssh2->sftp($this->connection));
        if ($sftp === false || $sftp === null) {
            throw new ConnectionException('Failed to initialize SFTP subsystem.');
        }

        $remotePath = $this->normalizeRemotePath($remoteFilename);
        $remoteUri = sprintf('ssh2.sftp://%d/%s', (int) $sftp, ltrim($remotePath, '/'));

        $remote = $this->warnings->run(fn () => $this->streams->fopen($remoteUri, 'r'));
        if ($remote === false) {
            throw new TransferException(sprintf(
                'Unable to open remote file "%s" for reading on "%s".',
                $remotePath,
                $this->host
            ));
        }

        $local = $this->warnings->run(fn () => $this->streams->fopen($localFilePath, 'w'));
        if ($local === false) {
            $this->warnings->run(fn () => $this->streams->fclose($remote));
            throw new TransferException(sprintf('Unable to open local file "%s" for writing.', $localFilePath));
        }

        $timeout = $this->options->timeout;
        if ($timeout !== null && $timeout > 0) {
            $this->warnings->run(fn () => $this->streams->streamSetTimeout($remote, $timeout));
            $this->warnings->run(fn () => $this->streams->streamSetTimeout($local, $timeout));
        }

        try {
            $copied = $this->warnings->run(fn () => $this->streams->streamCopyToStream($remote, $local));
            if ($copied === false) {
                throw new TransferException(sprintf('Download "%s" from "%s" failed.', $remotePath, $this->host));
            }
        } finally {
            $this->warnings->run(fn () => $this->streams->fclose($remote));
            $this->warnings->run(fn () => $this->streams->fclose($local));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     * @throws TransferException If the local file or remote file cannot be opened, or if the copy fails.
     */
    protected function doPutFile(string $destinationFilename, string $sourceFilePath): void
    {
        $this->assertReadyForTransfer('upload file');
        $this->assertSsh2Extension();

        $sftp = $this->warnings->run(fn () => $this->ssh2->sftp($this->connection));
        if ($sftp === false || $sftp === null) {
            throw new ConnectionException('Failed to initialize SFTP subsystem.');
        }

        $destinationPath = $this->normalizeRemotePath($destinationFilename);
        $remoteUri = sprintf('ssh2.sftp://%d/%s', (int) $sftp, ltrim($destinationPath, '/'));

        $local = $this->warnings->run(fn () => $this->streams->fopen($sourceFilePath, 'r'));
        if ($local === false) {
            throw new TransferException(sprintf('Unable to open local file "%s".', $sourceFilePath));
        }

        $remote = $this->warnings->run(fn () => $this->streams->fopen($remoteUri, 'w'));
        if ($remote === false) {
            $this->warnings->run(fn () => $this->streams->fclose($local));
            throw new TransferException(sprintf(
                'Unable to open remote file "%s" for writing on "%s".',
                $destinationPath,
                $this->host
            ));
        }

        $timeout = $this->options->timeout;
        if ($timeout !== null && $timeout > 0) {
            $this->warnings->run(fn () => $this->streams->streamSetTimeout($local, $timeout));
            $this->warnings->run(fn () => $this->streams->streamSetTimeout($remote, $timeout));
        }

        try {
            $copied = $this->warnings->run(fn () => $this->streams->streamCopyToStream($local, $remote));
            if ($copied === false) {
                throw new TransferException(sprintf(
                    'Upload "%s" to "%s" failed.',
                    $sourceFilePath,
                    $destinationPath
                ));
            }
        } finally {
            $this->warnings->run(fn () => $this->streams->fclose($local));
            $this->warnings->run(fn () => $this->streams->fclose($remote));
        }
    }

    /**
     * {@inheritDoc}
     *
     * Uses sftp_stat() and checks the POSIX mode bits to determine if the entry is a directory.
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     */
    protected function doIsDirectory(string $remotePath): bool
    {
        $this->assertReadyForTransfer('check directory');
        $this->assertSsh2Extension();

        $sftp = $this->warnings->run(fn () => $this->ssh2->sftp($this->connection));
        if ($sftp === false || $sftp === null) {
            throw new ConnectionException('Failed to initialize SFTP subsystem.');
        }

        $fullPath = $this->normalizeRemotePath($remotePath);

        $stat = $this->withRetry(
            operation: 'sftp_stat',
            fn: fn () => $this->warnings->run(fn () => $this->ssh2->sftpStat($sftp, $fullPath)),
            safe: true
        );

        if (!is_array($stat) || !isset($stat['mode'])) {
            return false;
        }

        return (($stat['mode'] & 0170000) === 0040000);
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     * @throws TransferException If the unlink operation fails.
     */
    protected function doDeleteFile(string $remotePath): void
    {
        $this->assertSsh2Extension();

        $sftp = $this->requireSftp();
        $fullPath = $this->normalizeRemotePath($remotePath);

        $ok = $this->warnings->run(fn () => $this->ssh2->sftpUnlink($sftp, $fullPath));
        if (!$ok) {
            throw new TransferException(sprintf(
                'Unable to delete "%s" on "%s".%s',
                $fullPath,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * When $recursive is true, this method creates intermediate directories.
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     * @throws TransferException If a directory cannot be created and does not exist afterwards.
     */
    protected function doMakeDirectory(string $remoteDir, bool $recursive): void
    {
        $this->assertSsh2Extension();

        $sftp = $this->requireSftp();

        $raw = \trim($remoteDir);
        if ($raw === '' || $raw === '.') {
            return;
        }

        $fullPath = $this->normalizeRemotePath($remoteDir);

        $dir = \trim($fullPath);
        if ($dir === '' || $dir === '/') {
            return;
        }

        $isAbsolute = str_starts_with($dir, '/');
        $dir = trim($dir, '/');
        if ($dir === '') {
            return;
        }

        /** @var list<non-empty-string> $parts */
        $parts = array_values(array_filter(
            explode('/', $dir),
            static fn (string $p): bool => $p !== ''
        ));

        $current = $isAbsolute ? '/' : '';

        foreach ($parts as $part) {

            $current = $current === '' || $current === '/'
                ? ($isAbsolute ? '/' . $part : $part)
                : $current . '/' . $part;

            $stat = $this->withRetry(
                operation: 'sftp_stat',
                fn: fn () => $this->warnings->run(fn () => $this->ssh2->sftpStat($sftp, $current)),
                safe: true
            );

            if (is_array($stat) && isset($stat['mode']) && $this->statModeIsDir((int) $stat['mode'])) {
                continue;
            }

            $ok = $this->warnings->run(fn () => $this->ssh2->sftpMkdir($sftp, $current, 0775, false));

            if (!$ok) {

                $statAfter = $this->withRetry(
                    operation: 'sftp_stat',
                    fn: fn () => $this->warnings->run(fn () => $this->ssh2->sftpStat($sftp, $current)),
                    safe: true
                );

                $existsAsDir = is_array($statAfter)
                    && isset($statAfter['mode'])
                    && $this->statModeIsDir((int) $statAfter['mode']);

                if (!$existsAsDir) {
                    throw new TransferException(sprintf(
                        'Unable to create directory "%s" on "%s".%s',
                        $current,
                        $this->host,
                        $this->warnings->formatLastWarning()
                    ));
                }
            }

            if (!$recursive) {
                break;
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     * @throws TransferException If the rmdir operation fails.
     */
    protected function doRemoveDirectory(string $remoteDir): void
    {
        $this->assertSsh2Extension();

        $sftp = $this->requireSftp();
        $fullPath = $this->normalizeRemotePath($remoteDir);

        $ok = $this->warnings->run(fn () => $this->ssh2->sftpRmdir($sftp, $fullPath));
        if (!$ok) {
            throw new TransferException(sprintf(
                'Unable to remove directory "%s" on "%s".%s',
                $fullPath,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException If the target is not a directory or any child operation fails.
     */
    protected function doRemoveDirectoryRecursive(string $remoteDir): void
    {
        $this->assertReadyForTransfer('remove directory recursively');
        $this->assertSsh2Extension();

        if (!$this->doIsDirectory($remoteDir)) {
            throw new TransferException(sprintf(
                'Path "%s" is not a directory on "%s".%s',
                $remoteDir,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        $entries = $this->doListFiles($remoteDir);

        foreach ($entries as $name) {
            $child = rtrim($remoteDir, '/') . '/' . ltrim($name, '/');

            if ($this->doIsDirectory($child)) {
                $this->doRemoveDirectoryRecursive($child);
                continue;
            }

            $this->doDeleteFile($child);
        }

        $this->doRemoveDirectory($remoteDir);
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     * @throws TransferException If the rename operation fails.
     */
    protected function doRename(string $from, string $to): void
    {
        $this->assertSsh2Extension();

        $sftp = $this->requireSftp();
        $fromPath = $this->normalizeRemotePath($from);
        $toPath = $this->normalizeRemotePath($to);

        $ok = $this->warnings->run(fn () => $this->ssh2->sftpRename($sftp, $fromPath, $toPath));
        if (!$ok) {
            throw new TransferException(sprintf(
                'Unable to rename "%s" to "%s" on "%s".%s',
                $fromPath,
                $toPath,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     */
    protected function doGetSize(string $remotePath): ?int
    {
        $this->assertSsh2Extension();

        $sftp = $this->requireSftp();
        $fullPath = $this->normalizeRemotePath($remotePath);

        $stat = $this->withRetry(
            operation: 'sftp_stat',
            fn: fn () => $this->warnings->run(fn () => $this->ssh2->sftpStat($sftp, $fullPath)),
            safe: true
        );

        if (!is_array($stat) || !isset($stat['size'])) {
            return null;
        }

        return (int) $stat['size'];
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     */
    protected function doGetMTime(string $remotePath): ?int
    {
        $this->assertSsh2Extension();

        $sftp = $this->requireSftp();
        $fullPath = $this->normalizeRemotePath($remotePath);

        $stat = $this->withRetry(
            operation: 'sftp_stat',
            fn: fn () => $this->warnings->run(fn () => $this->ssh2->sftpStat($sftp, $fullPath)),
            safe: true
        );

        if (!is_array($stat) || !isset($stat['mtime'])) {
            return null;
        }

        return (int) $stat['mtime'];
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     * @throws TransferException If chmod fails.
     */
    protected function doChmod(string $remotePath, int $mode): void
    {
        $this->assertSsh2Extension();

        $sftp = $this->requireSftp();
        $fullPath = $this->normalizeRemotePath($remotePath);

        $ok = $this->warnings->run(fn () => $this->ssh2->sftpChmod($sftp, $fullPath, $mode));
        if (!$ok) {
            throw new TransferException(sprintf(
                'Unable to chmod "%s" on "%s".%s',
                $fullPath,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * Initialize (or re-use) the SFTP subsystem for the current SSH connection.
     *
     * @return mixed The SFTP subsystem handle returned by ext-ssh2.
     *
     * @throws ConnectionException If the SFTP subsystem cannot be initialized.
     */
    private function requireSftp(): mixed
    {
        $sftp = $this->withRetry(
            operation: 'sftp_init',
            fn: fn () => $this->warnings->run(fn () => $this->ssh2->sftp($this->connection)),
            safe: true
        );

        if ($sftp === false || $sftp === null) {
            throw new ConnectionException(sprintf(
                'Failed to initialize SFTP subsystem.%s',
                $this->warnings->formatLastWarning()
            ));
        }

        return $sftp;
    }

    /**
     * Verify the server host key fingerprint against configured expectations.
     *
     * Rules:
     * - If strict host key checking is enabled and no expected fingerprint is provided -> fail.
     * - If expected fingerprint is provided -> compare with server fingerprint (MD5/SHA1 only with ext-ssh2).
     * - If a SHA256 fingerprint is provided -> fail (not supported via ext-ssh2 API).
     *
     * @throws ConnectionException If strict checking requirements are not met or fingerprints mismatch.
     * @throws MissingExtensionException If ext-ssh2 is not available.
     */
    private function assertHostKeyVerified(): void
    {
        $expectedParsed = $this->parseExpectedFingerprint($this->options->expectedFingerprint);

        if ($this->options->strictHostKeyChecking && $expectedParsed === null) {
            $this->invalidateConnection();
            throw new ConnectionException(\sprintf(
                'Strict host key checking is enabled but no expected fingerprint is configured for "%s".',
                $this->host
            ));
        }

        // No expectation => no verification (unless strict, handled above)
        if ($expectedParsed === null) {
            return;
        }

        $algo = $expectedParsed['algo'];
        $expectedNormalized = $expectedParsed['fingerprint'];

        $actual = $this->getServerFingerprintHex($algo);

        $this->logger->info('SFTP transport server fingerprint retrieved', [
            'host' => $this->host,
            'port' => $this->port ?? 22,
            'fingerprintAlgo' => strtoupper($algo),
            'fingerprint' => \substr($actual, 0, 12) . '…',
        ]);

        if (!\hash_equals($expectedNormalized, $actual)) {
            $this->invalidateConnection();
            throw new ConnectionException(\sprintf(
                'SFTP host key fingerprint mismatch for "%s" (%s).',
                $this->host,
                strtoupper($algo)
            ));
        }
    }

    /**
     * Retrieve the server host key fingerprint as HEX without separators.
     *
     * ext-ssh2 exposes only:
     * - SSH2_FINGERPRINT_MD5
     * - SSH2_FINGERPRINT_SHA1
     * combined with SSH2_FINGERPRINT_HEX (default) or SSH2_FINGERPRINT_RAW.
     *
     * @param string $algo One of: self::FINGERPRINT_ALGO_MD5, self::FINGERPRINT_ALGO_SHA1
     *
     * @return non-empty-string Normalized fingerprint: uppercase hex without separators.
     *
     * @throws ConnectionException If the fingerprint cannot be retrieved.
     * @throws MissingExtensionException If ext-ssh2 does not provide needed constants.
     */
    private function getServerFingerprintHex(string $algo): string
    {
        $flagConst = match ($algo) {
            self::FINGERPRINT_ALGO_MD5 => 'SSH2_FINGERPRINT_MD5',
            self::FINGERPRINT_ALGO_SHA1 => 'SSH2_FINGERPRINT_SHA1',
            default => null,
        };

        if ($flagConst === null || !\defined($flagConst)) {
            $this->invalidateConnection();
            throw new MissingExtensionException(sprintf(
                'Fingerprint algorithm "%s" is not supported by ext-ssh2. Available algorithms: MD5, SHA1.',
                $algo
            ));
        }

        // HEX is the default, but we make it explicit for clarity/consistency.
        if (!\defined('SSH2_FINGERPRINT_HEX')) {
            $this->invalidateConnection();
            throw new MissingExtensionException('ext-ssh2 is required for SFTP operations.');
        }

        /** @var int $flags */
        $flags = (\constant($flagConst) | \constant('SSH2_FINGERPRINT_HEX'));

        $fingerprintHex = $this->warnings->run(fn () => $this->ssh2->fingerprint(
            $this->connection,
            $flags
        ));

        if ($fingerprintHex === false) {
            $this->invalidateConnection();
            throw new ConnectionException(\sprintf(
                'Unable to retrieve server host key fingerprint for "%s".%s',
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        $normalized = $this->normalizeHexFingerprint($fingerprintHex);

        if ($normalized === '') {
            $this->invalidateConnection();
            throw new ConnectionException(\sprintf(
                'Unable to retrieve server host key fingerprint for "%s" (empty value).%s',
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        return $normalized;
    }

    /**
     * Parse and normalize a configured expected fingerprint.
     *
     * Accepted inputs:
     * - "MD5:aa:bb:cc:..." (colons optional, case-insensitive)
     * - "SHA1:aa:bb:..." (colons optional, case-insensitive)
     * - bare hex string (length 32 => MD5, length 40 => SHA1)
     *
     * Rejected inputs:
     * - "SHA256:..." (not supported with ext-ssh2)
     *
     * @return array{algo: self::FINGERPRINT_ALGO_MD5|self::FINGERPRINT_ALGO_SHA1, fingerprint: non-empty-string}|null
     *         Normalized fingerprint is uppercase hex without separators.
     *
     * @throws ConnectionException If SHA256 is provided or if the value is malformed.
     */
    private function parseExpectedFingerprint(?string $expected): ?array
    {
        if ($expected === null) {
            return null;
        }

        $value = \trim($expected);
        if ($value === '') {
            return null;
        }

        if (\stripos($value, 'sha256:') === 0) {
            $this->invalidateConnection();
            throw new ConnectionException(
                'SHA256 fingerprints are not supported with ext-ssh2. Available algorithms: MD5, SHA1.'
            );
        }

        $algo = null;
        $raw = $value;

        if (\stripos($value, 'md5:') === 0) {
            $algo = self::FINGERPRINT_ALGO_MD5;
            $raw = \trim(\substr($value, 4));
        } elseif (\stripos($value, 'sha1:') === 0) {
            $algo = self::FINGERPRINT_ALGO_SHA1;
            $raw = \trim(\substr($value, 5));
        }

        $normalized = $this->normalizeHexFingerprint($raw);

        if ($normalized === '') {
            return null;
        }

        if ($algo === null) {
            $len = \strlen($normalized);
            if ($len === 32) {
                $algo = self::FINGERPRINT_ALGO_MD5;
            } elseif ($len === 40) {
                $algo = self::FINGERPRINT_ALGO_SHA1;
            } else {
                $this->invalidateConnection();
                throw new ConnectionException(sprintf(
                    'Invalid expected fingerprint format for "%s". Provide "MD5:<hex>" or "SHA1:<hex>".',
                    $this->host
                ));
            }
        }

        $expectedLen = $algo === self::FINGERPRINT_ALGO_MD5 ? 32 : 40;
        if (\strlen($normalized) !== $expectedLen) {
            $this->invalidateConnection();
            throw new ConnectionException(sprintf(
                'Invalid %s fingerprint length for "%s".',
                strtoupper($algo),
                $this->host
            ));
        }

        /** @var non-falsy-string $normalized */
        return [
            'algo' => $algo,
            'fingerprint' => $normalized,
        ];
    }

    /**
     * Normalize a hex fingerprint string:
     * - trims
     * - removes separators (":" and spaces)
     * - uppercases
     * - keeps only [0-9A-F]
     *
     * @return string Uppercase hex without separators, or empty string if nothing usable.
     */
    private function normalizeHexFingerprint(string $value): string
    {
        $v = \trim($value);
        if ($v === '') {
            return '';
        }

        // Remove common separators
        $v = \str_replace([':', ' ', "\t", "\n", "\r"], '', $v);

        // Keep only hex chars
        $v = \preg_replace('/[^0-9a-fA-F]/', '', $v) ?? '';

        $v = \strtoupper($v);

        return $v;
    }

    /**
     * Invalidate the current session state (used when security checks fail).
     */
    private function invalidateConnection(): void
    {
        $this->connection = null;
        $this->authenticated = false;
    }

    /**
     * Normalize a remote path against the base path configured in the URL.
     *
     * - Empty / "." returns the base path.
     * - Absolute paths are returned as-is.
     * - Relative paths are joined with the base path.
     *
     * @param string $path Remote path as provided by the caller.
     *
     * @return string Normalized absolute remote path.
     */
    private function normalizeRemotePath(string $path): string
    {
        $p = \trim($path);
        if ($p === '' || $p === '.') {
            return $this->path;
        }

        return str_starts_with($p, '/') ? $p : Path::joinRemote($this->path, $p);
    }

    /**
     * Check whether a POSIX mode value indicates a directory.
     *
     * @param int $mode POSIX mode bits as returned by sftp_stat.
     */
    private function statModeIsDir(int $mode): bool
    {
        return (($mode & 0170000) === 0040000);
    }
}
