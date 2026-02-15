<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\Ssh2FunctionsInterface;

/**
 * Native implementation of {@see Ssh2FunctionsInterface}.
 *
 * This class provides a thin abstraction over PHP's ext-ssh2 functions.
 * It allows SFTP transport logic to depend on an interface rather than
 * directly calling global \ssh2_* functions, improving testability and decoupling.
 *
 * All methods delegate to the corresponding ext-ssh2 function.
 */
final class NativeSsh2Functions implements Ssh2FunctionsInterface
{
    /**
     * {@inheritDoc}
     *
     * Establish an SSH2 connection.
     *
     * @param string $host Remote host.
     * @param int $port Remote port.
     * @param array<string, mixed> $methods Authentication methods / connection methods (ext-ssh2).
     * @param array<string, mixed> $callbacks Optional callbacks used by ext-ssh2.
     *
     * @return mixed Connection handle (typically a resource) or false on failure.
     */
    public function connect(string $host, int $port, array $methods = [], array $callbacks = []): mixed
    {
        return \ssh2_connect($host, $port, $methods, $callbacks);
    }

    /**
     * {@inheritDoc}
     *
     * Authenticate using username/password.
     */
    public function authPassword(mixed $connection, string $user, string $pass): bool
    {
        return \ssh2_auth_password($connection, $user, $pass);
    }

    /**
     * {@inheritDoc}
     *
     * Authenticate using public key files.
     */
    public function authPubkeyFile(
        mixed $connection,
        string $user,
        string $pubkeyFile,
        string $privkeyFile
    ): bool {
        return \ssh2_auth_pubkey_file($connection, $user, $pubkeyFile, $privkeyFile);
    }

    /**
     * {@inheritDoc}
     *
     * Initialize the SFTP subsystem for a given SSH connection.
     *
     * @return mixed SFTP handle (typically a resource) or false on failure.
     */
    public function sftp(mixed $connection): mixed
    {
        return \ssh2_sftp($connection);
    }

    /**
     * {@inheritDoc}
     *
     * Retrieve stat information for a remote path.
     *
     * @return array<int|string, mixed>|false Stat array or false on failure.
     */
    public function sftpStat(mixed $sftp, string $path): array|false
    {
        return \ssh2_sftp_stat($sftp, $path);
    }

    /**
     * {@inheritDoc}
     *
     * Remove a remote file.
     */
    public function sftpUnlink(mixed $sftp, string $path): bool
    {
        return \ssh2_sftp_unlink($sftp, $path);
    }

    /**
     * {@inheritDoc}
     *
     * Create a remote directory.
     */
    public function sftpMkdir(mixed $sftp, string $directory, int $mode = 0775, bool $recursive = false): bool
    {
        return \ssh2_sftp_mkdir($sftp, $directory, $mode, $recursive);
    }

    /**
     * {@inheritDoc}
     *
     * Remove a remote directory.
     */
    public function sftpRmdir(mixed $sftp, string $directory): bool
    {
        return \ssh2_sftp_rmdir($sftp, $directory);
    }

    /**
     * {@inheritDoc}
     *
     * Rename or move a remote path.
     */
    public function sftpRename(mixed $sftp, string $from, string $to): bool
    {
        return \ssh2_sftp_rename($sftp, $from, $to);
    }

    /**
     * {@inheritDoc}
     *
     * Change permissions of a remote path.
     */
    public function sftpChmod(mixed $sftp, string $path, int $mode): bool
    {
        return \ssh2_sftp_chmod($sftp, $path, $mode);
    }

    /**
     * {@inheritDoc}
     *
     * Retrieve the server host key fingerprint.
     *
     * @param mixed $connection SSH connection handle.
     * @param int $flags Fingerprint flags (ext-ssh2).
     *
     * @return string|false Fingerprint string, or false on failure.
     */
    public function fingerprint(mixed $connection, int $flags): string|false
    {
        return \ssh2_fingerprint($connection, $flags);
    }
}
