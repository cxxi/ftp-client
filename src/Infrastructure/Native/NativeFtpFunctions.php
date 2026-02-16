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
     * Invoker used for all native calls.
     */
    private readonly NativeFunctionInvokerInterface $invoke;

    /**
     * Typed wrapper over {@see NativeFunctionInvokerInterface} ensuring
     * deterministic return types for static analysis and runtime safety.
     */
    private readonly TypedNativeInvoker $typed;

    /**
     * @param NativeFunctionInvokerInterface|null $invoke
     *        Invoker used to call ext-ftp functions (e.g. "ftp_connect").
     *        - If null, a default {@see NativeFunctionInvoker} is used.
     *        - In unit tests, inject a fake invoker to avoid real network calls.
     */
    public function __construct(?NativeFunctionInvokerInterface $invoke = null)
    {
        $this->invoke = $invoke ?? new NativeFunctionInvoker();
        $this->typed = new TypedNativeInvoker($this->invoke);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to:
     * - \ftp_connect($host, $port) when $timeout is null
     * - \ftp_connect($host, $port, $timeout) otherwise
     *
     * @return mixed
     * @phpstan-return resource|\FTP\Connection|false
     */
    public function connect(string $host, int $port = 21, ?int $timeout = null)
    {
        return $timeout === null
            ? $this->typed->ftpConnectionOrFalse('ftp_connect', [$host, $port])
            : $this->typed->ftpConnectionOrFalse('ftp_connect', [$host, $port, $timeout]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to:
     * - \ftp_ssl_connect($host, $port) when $timeout is null
     * - \ftp_ssl_connect($host, $port, $timeout) otherwise
     *
     * @return mixed
     * @phpstan-return resource|\FTP\Connection|false
     */
    public function sslConnect(string $host, int $port = 21, ?int $timeout = null)
    {
        return $timeout === null
            ? $this->typed->ftpConnectionOrFalse('ftp_ssl_connect', [$host, $port])
            : $this->typed->ftpConnectionOrFalse('ftp_ssl_connect', [$host, $port, $timeout]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_login($connection, $user, $pass).
     */
    public function login(mixed $connection, string $user, string $pass): bool
    {
        return $this->typed->bool('ftp_login', [$connection, $user, $pass]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_close($connection).
     */
    public function close(mixed $connection): bool
    {
        return $this->typed->bool('ftp_close', [$connection]);
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
        $out = $this->typed->arrayOrFalse('ftp_nlist', [$connection, $dir]);

        /** @var array<int, string>|false $out */
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

        return $this->typed->bool('ftp_get', [$connection, $localFilePath, $remoteFilename, $mode]);
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

        return $this->typed->bool('ftp_put', [$connection, $remoteFilename, $localFilePath, $mode]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_pwd($connection).
     */
    public function pwd(mixed $connection): string|false
    {
        return $this->typed->stringOrFalse('ftp_pwd', [$connection]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_chdir($connection, $directory).
     */
    public function chdir(mixed $connection, string $directory): bool
    {
        return $this->typed->bool('ftp_chdir', [$connection, $directory]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_pasv($connection, $enabled).
     */
    public function pasv(mixed $connection, bool $enabled): bool
    {
        return $this->typed->bool('ftp_pasv', [$connection, $enabled]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_delete($connection, $path).
     */
    public function delete(mixed $connection, string $path): bool
    {
        return $this->typed->bool('ftp_delete', [$connection, $path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_mkdir($connection, $directory).
     */
    public function mkdir(mixed $connection, string $directory): string|false
    {
        return $this->typed->stringOrFalse('ftp_mkdir', [$connection, $directory]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_rmdir($connection, $directory).
     */
    public function rmdir(mixed $connection, string $directory): bool
    {
        return $this->typed->bool('ftp_rmdir', [$connection, $directory]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_rename($connection, $from, $to).
     */
    public function rename(mixed $connection, string $from, string $to): bool
    {
        return $this->typed->bool('ftp_rename', [$connection, $from, $to]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_size($connection, $path).
     */
    public function size(mixed $connection, string $path): int
    {
        return $this->typed->int('ftp_size', [$connection, $path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_mdtm($connection, $path).
     */
    public function mdtm(mixed $connection, string $path): int
    {
        return $this->typed->int('ftp_mdtm', [$connection, $path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ftp_chmod($connection, $mode, $path).
     */
    public function chmod(mixed $connection, int $mode, string $path): int|false
    {
        return $this->typed->intOrFalse('ftp_chmod', [$connection, $mode, $path]);
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
        $out = $this->typed->arrayOrFalse('ftp_rawlist', [$connection, $directory, $recursive]);

        /** @var array<int, string>|false $out */
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

        $out = $this->typed->arrayOrFalse('ftp_mlsd', [$connection, $directory]);

        /** @var array<int, mixed>|false $out */
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
