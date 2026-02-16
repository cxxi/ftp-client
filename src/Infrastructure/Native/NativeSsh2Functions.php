<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;
use Cxxi\FtpClient\Infrastructure\Port\Ssh2FunctionsInterface;

/**
 * Native implementation of {@see Ssh2FunctionsInterface}.
 *
 * This adapter provides a thin abstraction over PHP's ext-ssh2 functions.
 * It allows SFTP transport logic to depend on an interface rather than
 * calling global \ssh2_* functions directly.
 *
 * Testing strategy:
 * - In production, this class calls real global functions via a
 *   {@see NativeFunctionInvoker}.
 * - In unit tests, callers may inject a fake {@see NativeFunctionInvokerInterface}
 *   to avoid real network access and to return deterministic values.
 */
final class NativeSsh2Functions implements Ssh2FunctionsInterface
{
    /**
     * @var NativeFunctionInvokerInterface Invoker used for all native calls.
     */
    private readonly NativeFunctionInvokerInterface $invoke;

    /**
     * @param NativeFunctionInvokerInterface|null $invoke
     *        Invoker used to call ext-ssh2 functions (e.g. "ssh2_connect").
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
     * Delegates to \ssh2_connect($host, $port, $methods, $callbacks).
     *
     * @param array<string, mixed> $methods
     * @param array<string, mixed> $callbacks
     */
    public function connect(string $host, int $port, array $methods = [], array $callbacks = []): mixed
    {
        return ($this->invoke)('ssh2_connect', [$host, $port, $methods, $callbacks]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_auth_password($connection, $user, $pass).
     */
    public function authPassword(mixed $connection, string $user, string $pass): bool
    {
        return (bool) ($this->invoke)('ssh2_auth_password', [$connection, $user, $pass]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_auth_pubkey_file($connection, $user, $pubkeyFile, $privkeyFile).
     */
    public function authPubkeyFile(mixed $connection, string $user, string $pubkeyFile, string $privkeyFile): bool
    {
        return (bool) ($this->invoke)(
            'ssh2_auth_pubkey_file',
            [$connection, $user, $pubkeyFile, $privkeyFile]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_sftp($connection).
     */
    public function sftp(mixed $connection): mixed
    {
        return ($this->invoke)('ssh2_sftp', [$connection]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_sftp_stat($sftp, $path).
     *
     * @return array<int|string, mixed>|false
     */
    public function sftpStat(mixed $sftp, string $path): array|false
    {
        /** @var array<int|string, mixed>|false $out */
        $out = ($this->invoke)('ssh2_sftp_stat', [$sftp, $path]);

        return $out;
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_sftp_unlink($sftp, $path).
     */
    public function sftpUnlink(mixed $sftp, string $path): bool
    {
        return (bool) ($this->invoke)('ssh2_sftp_unlink', [$sftp, $path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_sftp_mkdir($sftp, $directory, $mode, $recursive).
     */
    public function sftpMkdir(mixed $sftp, string $directory, int $mode = 0775, bool $recursive = false): bool
    {
        return (bool) ($this->invoke)('ssh2_sftp_mkdir', [$sftp, $directory, $mode, $recursive]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_sftp_rmdir($sftp, $directory).
     */
    public function sftpRmdir(mixed $sftp, string $directory): bool
    {
        return (bool) ($this->invoke)('ssh2_sftp_rmdir', [$sftp, $directory]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_sftp_rename($sftp, $from, $to).
     */
    public function sftpRename(mixed $sftp, string $from, string $to): bool
    {
        return (bool) ($this->invoke)('ssh2_sftp_rename', [$sftp, $from, $to]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_sftp_chmod($sftp, $path, $mode).
     */
    public function sftpChmod(mixed $sftp, string $path, int $mode): bool
    {
        return (bool) ($this->invoke)('ssh2_sftp_chmod', [$sftp, $path, $mode]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to \ssh2_fingerprint($connection, $flags).
     */
    public function fingerprint(mixed $connection, int $flags): string|false
    {
        /** @var string|false $out */
        $out = ($this->invoke)('ssh2_fingerprint', [$connection, $flags]);

        return $out;
    }
}
