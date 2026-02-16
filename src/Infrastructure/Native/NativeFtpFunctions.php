<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\FtpFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;

/**
 * Native implementation of {@see FtpFunctionsInterface}.
 *
 * This adapter provides a thin abstraction over PHP's ext-ftp functions.
 * It allows higher-level services to depend on an interface instead of
 * calling global \ftp_* functions directly.
 *
 * Testing strategy:
 * - In production, this class calls real global functions via a
 *   {@see NativeFunctionInvoker}.
 * - In unit tests, callers may inject a fake {@see NativeFunctionInvokerInterface}
 *   to avoid network calls and return deterministic values.
 *
 * This class remains `final`, and testability is achieved through composition
 * (invoker injection) rather than inheritance.
 */
final class NativeFtpFunctions implements FtpFunctionsInterface
{
    /**
     * @var NativeFunctionInvokerInterface Invoker used for all native calls.
     */
    private readonly NativeFunctionInvokerInterface $invoke;

    /**
     * @param NativeFunctionInvokerInterface|null $invoke
     *        Invoker used to call ext-ftp functions (e.g. "ftp_connect").
     *        - If null, a default {@see NativeFunctionInvoker} is used.
     *        - In unit tests, inject a fake invoker to avoid real network calls.
     */
    public function __construct(?NativeFunctionInvokerInterface $invoke = null)
    {
        $this->invoke = $invoke ?? new NativeFunctionInvoker();
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to:
     * - \ftp_connect($host, $port) when $timeout is null
     * - \ftp_connect($host, $port, $timeout) otherwise
     */
    public function connect(string $host, int $port = 21, ?int $timeout = null): mixed
    {
        return $timeout === null
            ? ($this->invoke)('ftp_connect', [$host, $port])
            : ($this->invoke)('ftp_connect', [$host, $port, $timeout]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to:
     * - \ftp_ssl_connect($host, $port) when $timeout is null
     * - \ftp_ssl_connect($host, $port, $timeout) otherwise
     */
    public function sslConnect(string $host, int $port = 21, ?int $timeout = null): mixed
    {
        return $timeout === null
            ? ($this->invoke)('ftp_ssl_connect', [$host, $port])
            : ($this->invoke)('ftp_ssl_connect', [$host, $port, $timeout]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_login($connection, $user, $pass).
     */
    public function login(mixed $connection, string $user, string $pass): bool
    {
        return (bool) ($this->invoke)('ftp_login', [$connection, $user, $pass]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_close($connection).
     */
    public function close(mixed $connection): bool
    {
        return (bool) ($this->invoke)('ftp_close', [$connection]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_nlist($connection, $dir).
     *
     * @return array<int, string>|false
     */
    public function nlist(mixed $connection, string $dir): array|false
    {
        /** @var array<int, string>|false $out */
        $out = ($this->invoke)('ftp_nlist', [$connection, $dir]);

        return $out;
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_get($connection, $localFilePath, $remoteFilename, $mode).
     *
     * Normalizes $mode to either FTP_ASCII or FTP_BINARY, defaulting to FTP_BINARY.
     */
    public function get(mixed $connection, string $localFilePath, string $remoteFilename, int $mode): bool
    {
        $mode = $mode === \FTP_ASCII ? \FTP_ASCII : \FTP_BINARY;

        return (bool) ($this->invoke)('ftp_get', [$connection, $localFilePath, $remoteFilename, $mode]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_put($connection, $remoteFilename, $localFilePath, $mode).
     *
     * Normalizes $mode to either FTP_ASCII or FTP_BINARY, defaulting to FTP_BINARY.
     */
    public function put(mixed $connection, string $remoteFilename, string $localFilePath, int $mode): bool
    {
        $mode = $mode === \FTP_ASCII ? \FTP_ASCII : \FTP_BINARY;

        return (bool) ($this->invoke)('ftp_put', [$connection, $remoteFilename, $localFilePath, $mode]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_pwd($connection).
     */
    public function pwd(mixed $connection): string|false
    {
        /** @var string|false $out */
        $out = ($this->invoke)('ftp_pwd', [$connection]);

        return $out;
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_chdir($connection, $directory).
     */
    public function chdir(mixed $connection, string $directory): bool
    {
        return (bool) ($this->invoke)('ftp_chdir', [$connection, $directory]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_pasv($connection, $enabled).
     */
    public function pasv(mixed $connection, bool $enabled): bool
    {
        return (bool) ($this->invoke)('ftp_pasv', [$connection, $enabled]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_delete($connection, $path).
     */
    public function delete(mixed $connection, string $path): bool
    {
        return (bool) ($this->invoke)('ftp_delete', [$connection, $path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_mkdir($connection, $directory).
     */
    public function mkdir(mixed $connection, string $directory): string|false
    {
        /** @var string|false $out */
        $out = ($this->invoke)('ftp_mkdir', [$connection, $directory]);

        return $out;
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_rmdir($connection, $directory).
     */
    public function rmdir(mixed $connection, string $directory): bool
    {
        return (bool) ($this->invoke)('ftp_rmdir', [$connection, $directory]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_rename($connection, $from, $to).
     */
    public function rename(mixed $connection, string $from, string $to): bool
    {
        return (bool) ($this->invoke)('ftp_rename', [$connection, $from, $to]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_size($connection, $path).
     */
    public function size(mixed $connection, string $path): int
    {
        return (int) ($this->invoke)('ftp_size', [$connection, $path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_mdtm($connection, $path).
     */
    public function mdtm(mixed $connection, string $path): int
    {
        return (int) ($this->invoke)('ftp_mdtm', [$connection, $path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_chmod($connection, $mode, $path).
     */
    public function chmod(mixed $connection, int $mode, string $path): int|false
    {
        /** @var int|false $out */
        $out = ($this->invoke)('ftp_chmod', [$connection, $mode, $path]);

        return $out;
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_rawlist($connection, $directory, $recursive).
     *
     * @return array<int, string>|false
     */
    public function rawlist(mixed $connection, string $directory, bool $recursive = false): array|false
    {
        /** @var array<int, string>|false $out */
        $out = ($this->invoke)('ftp_rawlist', [$connection, $directory, $recursive]);

        return $out;
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_mlsd($connection, $directory) when available.
     *
     * Returns false if ftp_mlsd() is not available in the current runtime.
     *
     * @return array<int, mixed>|false
     */
    public function mlsd(mixed $connection, string $directory): array|false
    {
        if (!$this->hasMlsd()) {
            return false;
        }

        /** @var array<int, mixed>|false $out */
        $out = ($this->invoke)('ftp_mlsd', [$connection, $directory]);

        return $out;
    }

    /**
     * Checks whether `ftp_mlsd` is available in the current runtime.
     *
     * This exists because ftp_mlsd() may not be available depending on
     * the PHP build and the ext-ftp version.
     *
     * Unit tests can inject a fake invoker that returns deterministic values
     * for {@see NativeFunctionInvokerInterface::functionExists()}.
     */
    protected function hasMlsd(): bool
    {
        return $this->invoke->functionExists('ftp_mlsd');
    }
}
