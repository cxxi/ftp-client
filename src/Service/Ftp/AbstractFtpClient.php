<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Service\Ftp;

use Cxxi\FtpClient\Enum\PassiveMode;
use Cxxi\FtpClient\Exception\AuthenticationException;
use Cxxi\FtpClient\Exception\ConnectionException;
use Cxxi\FtpClient\Exception\MissingExtensionException;
use Cxxi\FtpClient\Exception\TransferException;
use Cxxi\FtpClient\Infrastructure\Native\NativeExtensionChecker;
use Cxxi\FtpClient\Infrastructure\Native\NativeFtpFunctions;
use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\FtpFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Service\AbstractClient;
use Cxxi\FtpClient\Util\WarningCatcher;
use Psr\Log\LoggerInterface;

/**
 * Base class for FTP/FTPS transports relying on PHP's ext-ftp.
 *
 * This class builds on {@see AbstractClient} by:
 * - Ensuring the PHP "ftp" extension is available
 * - Managing connect/login/close lifecycle for FTP-family transports
 * - Implementing common FTP operations using an {@see FtpFunctionsInterface} adapter
 * - Enforcing a base remote directory (URL path) via {@see ensureFtpDirectory()}
 * - Applying passive mode configuration after successful authentication
 *
 * Concrete implementations must provide the low-level connection creation
 * via {@see doConnectFtp()}, e.g. FTP vs. FTPS differences.
 */
abstract class AbstractFtpClient extends AbstractClient
{
    /**
     * Extension checker used to verify that required PHP extensions are loaded.
     */
    protected readonly ExtensionCheckerInterface $extensions;

    /**
     * FTP function adapter (usually backed by native ext-ftp functions).
     */
    protected readonly FtpFunctionsInterface $ftp;

    /**
     * @param FtpUrl $url Parsed FTP URL (protocol, host, port, credentials, path).
     * @param ConnectionOptions|null $options Transfer and retry options (defaults to a new instance).
     * @param LoggerInterface|null $logger PSR-3 logger.
     * @param ExtensionCheckerInterface|null $extensions Extension checker (defaults to {@see NativeExtensionChecker}).
     * @param FtpFunctionsInterface|null $ftp FTP adapter (defaults to {@see NativeFtpFunctions}).
     * @param FilesystemFunctionsInterface|null $fs Filesystem adapter.
     * @param WarningCatcher|null $warnings Warning catcher.
     */
    public function __construct(
        FtpUrl $url,
        ?ConnectionOptions $options = null,
        ?LoggerInterface $logger = null,
        ?ExtensionCheckerInterface $extensions = null,
        ?FtpFunctionsInterface $ftp = null,
        ?FilesystemFunctionsInterface $fs = null,
        ?WarningCatcher $warnings = null
    ) {
        parent::__construct($url, $options, $logger, $fs, $warnings);

        $this->extensions = $extensions ?? new NativeExtensionChecker();
        $this->ftp = $ftp ?? new NativeFtpFunctions();
    }

    /**
     * Assert that ext-ftp is available.
     *
     * @throws MissingExtensionException If the "ftp" extension is not loaded.
     */
    final protected function assertFtpExtension(): void
    {
        if (!$this->extensions->loaded('ftp')) {
            throw new MissingExtensionException('ext-ftp is required for FTP/FTPS operations.');
        }
    }

    /**
     * Create a connection handle using the underlying FTP-family connector.
     *
     * Concrete classes typically use either ftp_connect() or ftp_ssl_connect().
     *
     * @param int|null $timeout Connection timeout in seconds (null to use ext default).
     *
     * @return resource|false|null Connection handle (implementation-specific).
     */
    abstract protected function doConnectFtp(?int $timeout);

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
     * Establish the remote connection.
     *
     * @return static
     *
     * @throws MissingExtensionException If ext-ftp is missing.
     * @throws ConnectionException If the connection cannot be established.
     */
    public function connect(): static
    {
        $this->assertFtpExtension();

        $timeout = $this->options->timeout;

        $this->logger->info('FTP transport connecting', [
            'protocol' => $this->protocol->value ?? null,
            'host' => $this->host,
            'port' => $this->port ?? 21,
            'path' => $this->path,
            'timeout' => $timeout,
        ]);

        $this->withRetry('connect', function () use ($timeout): void {

            $this->connection = $this->warnings->run(fn () => $this->doConnectFtp($timeout));

            if (!$this->isConnected()) {

                $this->logger->error('FTP transport connection failed', [
                    'protocol' => $this->protocol->value ?? null,
                    'host' => $this->host,
                    'port' => $this->port ?? 21,
                    'path' => $this->path,
                    'timeout' => $timeout,
                    'warning' => $this->warnings->formatLastWarning(),
                ]);

                throw new ConnectionException(sprintf(
                    'Unable to connect to server "%s" (FTP family).%s',
                    $this->host,
                    $this->warnings->formatLastWarning()
                ));
            }

        }, safe: true);

        $this->logger->info('FTP transport connected', [
            'protocol' => $this->protocol->value ?? null,
            'host' => $this->host,
            'port' => $this->port ?? 21,
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
     * @throws MissingExtensionException If ext-ftp is missing.
     * @throws AuthenticationException If not connected, credentials are missing, or login fails.
     */
    public function loginWithPassword(?string $user = null, ?string $pass = null): static
    {
        if (!$this->isConnected()) {
            throw new AuthenticationException('Cannot login: connection not established yet.');
        }

        $this->assertFtpExtension();

        $user ??= $this->user;
        $pass ??= $this->pass;

        if ($user === null || $user === '' || $pass === null || $pass === '') {
            throw new AuthenticationException('Missing username or password for login.');
        }

        $this->logger->info('FTP transport authenticating (password)', [
            'protocol' => $this->protocol->value ?? null,
            'host' => $this->host,
            'user' => $user,
        ]);

        $this->withRetry('loginWithPassword', function () use ($user, $pass): void {

            $ok = $this->warnings->run(fn () => $this->ftp->login($this->connection, $user, $pass));

            if (!$ok) {

                $this->logger->warning('FTP transport authentication failed', [
                    'protocol' => $this->protocol->value ?? null,
                    'host' => $this->host,
                    'user' => $user,
                    'warning' => $this->warnings->formatLastWarning(),
                ]);

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

        $this->configurePassiveMode();

        $this->logger->info('FTP transport authenticated', [
            'protocol' => $this->protocol->value ?? null,
            'host' => $this->host,
            'user' => $user,
            'passive' => $this->options->passive->value,
        ]);

        return $this;
    }

    /**
     * Close the remote connection and reset session state.
     *
     * This method is safe to call multiple times.
     */
    public function closeConnection(): void
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->logger->debug('FTP transport closing connection', [
            'protocol' => $this->protocol->value ?? null,
            'host' => $this->host,
            'port' => $this->port ?? 21,
        ]);

        $this->warnings->run(fn () => $this->ftp->close($this->connection));

        $this->connection = null;
        $this->authenticated = false;
    }

    /**
     * {@inheritDoc}
     *
     * Lists non-hidden entries (leading dot entries are filtered out).
     *
     * @param string $remoteDir
     * @return array<int, string>
     *
     * @throws TransferException
     */
    protected function doListFiles(string $remoteDir): array
    {
        $this->assertReadyForTransfer('list files');
        $this->ensureFtpDirectory();

        /** @var array<int, string>|false $files */
        $files = $this->warnings->run(fn () => $this->ftp->nlist($this->connection, $remoteDir));

        if ($files === false) {
            $this->logger->warning('FTP transport list files failed', [
                'protocol' => $this->protocol->value ?? null,
                'host' => $this->host,
                'remoteDir' => $remoteDir,
                'warning' => $this->warnings->formatLastWarning(),
            ]);

            throw new TransferException(sprintf(
                'Unable to list files on "%s".%s',
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        $filtered = array_values(array_filter(
            $files,
            static fn (string $file) => !str_starts_with(\basename($file), '.')
        ));

        return $filtered;
    }

    /**
     * Raw directory listing (server-dependent format).
     *
     * @param string $remoteDir  Directory to list.
     * @param bool $recursive    Whether to request a recursive listing when supported.
     *
     * @return array<int, string> Raw listing lines as returned by the FTP server.
     *
     * @throws TransferException If the client is not ready or the operation fails.
     */
    public function rawList(string $remoteDir = '.', bool $recursive = false): array
    {
        $this->assertReadyForTransfer('raw list');
        $this->ensureFtpDirectory();

        /** @var array<int, string>|false $list */
        $list = $this->warnings->run(fn () => $this->ftp->rawlist($this->connection, $remoteDir, $recursive));
        if ($list === false) {
            throw new TransferException(sprintf(
                'Unable to raw list "%s" on "%s".%s',
                $remoteDir,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        return $list;
    }

    /**
     * Machine-readable directory listing (MLSD).
     *
     * Availability depends on both ext-ftp and server support.
     *
     * @param string $remoteDir Directory to list.
     *
     * @return array<int, array<string, string>> MLSD entries as returned by ext-ftp.
     *
     * @throws TransferException If the client is not ready or the operation fails.
     */
    public function mlsd(string $remoteDir = '.'): array
    {
        $this->assertReadyForTransfer('mlsd');
        $this->ensureFtpDirectory();

        /** @var array<int, array<string, string>>|false $list */
        $list = $this->warnings->run(fn () => $this->ftp->mlsd($this->connection, $remoteDir));
        if ($list === false) {
            throw new TransferException(sprintf(
                'Unable to MLSD "%s" on "%s".%s',
                $remoteDir,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        return $list;
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doDownloadFile(string $remoteFilename, string $localFilePath): void
    {
        $this->assertReadyForTransfer('download file');
        $this->ensureFtpDirectory();

        $ok = $this->warnings->run(
            fn () => $this->ftp->get($this->connection, $localFilePath, $remoteFilename, \FTP_BINARY)
        );

        if (!$ok) {
            throw new TransferException(sprintf(
                'Download "%s" from "%s" failed.%s',
                $remoteFilename,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doPutFile(string $destinationFilename, string $sourceFilePath): void
    {
        $this->assertReadyForTransfer('upload file');
        $this->ensureFtpDirectory();

        $ok = $this->warnings->run(
            fn () => $this->ftp->put($this->connection, $destinationFilename, $sourceFilePath, \FTP_BINARY)
        );

        if (!$ok) {
            throw new TransferException(sprintf(
                'Upload "%s" to "%s" failed.%s',
                $sourceFilePath,
                $destinationFilename,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doIsDirectory(string $remotePath): bool
    {
        $this->assertReadyForTransfer('check directory');
        $this->ensureFtpDirectory();

        $currentDir = $this->warnings->run(fn () => $this->ftp->pwd($this->connection));
        if (!is_string($currentDir) || $currentDir === '') {
            return false;
        }

        $ok = $this->warnings->run(fn () => $this->ftp->chdir($this->connection, $remotePath));

        if ($ok) {
            $this->warnings->run(fn () => $this->ftp->chdir($this->connection, $currentDir));
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doDeleteFile(string $remotePath): void
    {
        $this->ensureFtpDirectory();

        $ok = $this->warnings->run(fn () => $this->ftp->delete($this->connection, $remotePath));
        if (!$ok) {
            throw new TransferException(sprintf(
                'Unable to delete "%s" on "%s".%s',
                $remotePath,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doMakeDirectory(string $remoteDir, bool $recursive): void
    {
        $this->ensureFtpDirectory();

        $dir = trim($remoteDir);
        if ($dir === '' || $dir === '.') {
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

            if ($recursive) {

                $existsAsDir = $this->doIsDirectory($current);
                if ($existsAsDir) {
                    continue;
                }

                $created = $this->warnings->run(fn () => $this->ftp->mkdir($this->connection, $current));
                if ($created !== false) {
                    continue;
                }

                $existsAsDirAfter = $this->doIsDirectory($current);

                if (!$existsAsDirAfter) {
                    throw new TransferException(sprintf(
                        'Unable to create directory "%s" on "%s".%s',
                        $current,
                        $this->host,
                        $this->warnings->formatLastWarning()
                    ));
                }

                continue;
            }

            $created = $this->warnings->run(fn () => $this->ftp->mkdir($this->connection, $remoteDir));

            if ($created === false) {
                throw new TransferException(sprintf(
                    'Unable to create directory "%s" on "%s".%s',
                    $remoteDir,
                    $this->host,
                    $this->warnings->formatLastWarning()
                ));
            }

            return;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doRemoveDirectory(string $remoteDir): void
    {
        $this->ensureFtpDirectory();

        $ok = $this->warnings->run(fn () => $this->ftp->rmdir($this->connection, $remoteDir));
        if (!$ok) {
            throw new TransferException(sprintf(
                'Unable to remove directory "%s" on "%s".%s',
                $remoteDir,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doRemoveDirectoryRecursive(string $remoteDir): void
    {
        $this->ensureFtpDirectory();

        $dir = trim($remoteDir);

        if (!$this->doIsDirectory($dir)) {
            throw new TransferException(sprintf(
                'Path "%s" is not a directory on "%s".%s',
                $dir,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        /** @var array<int, string>|false $files */
        $files = $this->warnings->run(fn () => $this->ftp->nlist($this->connection, $dir));
        if ($files === false) {
            throw new TransferException(sprintf(
                'Unable to list directory "%s" on "%s".%s',
                $dir,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        $entries = array_values(array_filter(
            $files,
            static fn (string $p) => !str_ends_with($p, '/.') && !str_ends_with($p, '/..') && !in_array(\basename($p), ['.', '..'], true)
        ));

        foreach ($entries as $entry) {

            $candidate = $entry;

            if ($this->doIsDirectory($candidate)) {
                $this->doRemoveDirectoryRecursive($candidate);
                continue;
            }

            $ok = $this->warnings->run(fn () => $this->ftp->delete($this->connection, $candidate));
            if (!$ok) {
                $joined = rtrim($dir, '/') . '/' . ltrim($candidate, '/');

                if ($this->doIsDirectory($joined)) {
                    $this->doRemoveDirectoryRecursive($joined);
                    continue;
                }

                $ok2 = $this->warnings->run(fn () => $this->ftp->delete($this->connection, $joined));
                if (!$ok2) {
                    throw new TransferException(sprintf(
                        'Unable to delete "%s" in "%s" on "%s".%s',
                        $candidate,
                        $dir,
                        $this->host,
                        $this->warnings->formatLastWarning()
                    ));
                }
            }
        }

        $ok = $this->warnings->run(fn () => $this->ftp->rmdir($this->connection, $dir));
        if (!$ok) {
            throw new TransferException(sprintf(
                'Unable to remove directory "%s" on "%s".%s',
                $dir,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doRename(string $from, string $to): void
    {
        $this->ensureFtpDirectory();

        $ok = $this->warnings->run(fn () => $this->ftp->rename($this->connection, $from, $to));
        if (!$ok) {
            throw new TransferException(sprintf(
                'Unable to rename "%s" to "%s" on "%s".%s',
                $from,
                $to,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function doGetSize(string $remotePath): ?int
    {
        $this->ensureFtpDirectory();

        $size = $this->warnings->run(fn () => $this->ftp->size($this->connection, $remotePath));
        return $size >= 0 ? $size : null;
    }

    /**
     * {@inheritDoc}
     */
    protected function doGetMTime(string $remotePath): ?int
    {
        $this->ensureFtpDirectory();

        $t = $this->warnings->run(fn () => $this->ftp->mdtm($this->connection, $remotePath));
        return $t >= 0 ? $t : null;
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransferException
     */
    protected function doChmod(string $remotePath, int $mode): void
    {
        $this->ensureFtpDirectory();

        $result = $this->warnings->run(fn () => $this->ftp->chmod($this->connection, $mode, $remotePath));
        if ($result === false) {
            throw new TransferException(sprintf(
                'Unable to chmod "%s" on "%s".%s',
                $remotePath,
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }
    }

    /**
     * Ensure the remote working directory matches the base path from the URL.
     *
     * Many FTP servers treat operations as relative to the current working directory.
     * This helper makes sure the session is positioned at {@see AbstractClient::$path}
     * before executing operations.
     *
     * @throws TransferException If the current directory cannot be determined or if chdir fails.
     */
    private function ensureFtpDirectory(): void
    {
        $currentDir = $this->warnings->run(fn () => $this->ftp->pwd($this->connection));
        if (!is_string($currentDir) || $currentDir === '') {
            throw new TransferException(sprintf(
                'Unable to determine current directory on "%s".%s',
                $this->host,
                $this->warnings->formatLastWarning()
            ));
        }

        $current = rtrim($currentDir, '/');
        $target = rtrim($this->path, '/');

        if ($current !== $target) {
            $ok = $this->warnings->run(fn () => $this->ftp->chdir($this->connection, $this->path));
            if (!$ok) {
                throw new TransferException(sprintf(
                    'Unable to change directory to "%s" on "%s".%s',
                    $this->path,
                    $this->host,
                    $this->warnings->formatLastWarning()
                ));
            }
        }
    }

    /**
     * Configure passive mode according to {@see ConnectionOptions::$passive}.
     *
     * - TRUE: force passive mode
     * - FALSE: force active mode
     * - AUTO: try passive first, and fallback to active mode if a simple listing fails
     *
     * Failures are not considered fatal at this stage; the function relies on warning capture.
     */
    private function configurePassiveMode(): void
    {
        $mode = $this->options->passive;

        if ($mode === PassiveMode::TRUE) {
            $this->warnings->run(fn () => $this->ftp->pasv($this->connection, true));
            return;
        }

        if ($mode === PassiveMode::FALSE) {
            $this->warnings->run(fn () => $this->ftp->pasv($this->connection, false));
            return;
        }

        $this->warnings->run(fn () => $this->ftp->pasv($this->connection, true));

        $list = $this->warnings->run(fn () => $this->ftp->nlist($this->connection, '.'));
        if ($list === false) {
            $this->warnings->run(fn () => $this->ftp->pasv($this->connection, false));
        }
    }
}
