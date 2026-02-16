<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Port;

/**
 * Contract for SSH2 and SFTP-related operations.
 *
 * This interface abstracts PHP's ext-ssh2 functions in order to:
 * - Decouple SFTP transport logic from global \ssh2_* functions
 * - Improve testability via mock implementations
 * - Centralize SSH/SFTP-related behavior
 *
 * Implementations typically delegate to PHP's native ext-ssh2 functions.
 */
interface Ssh2FunctionsInterface
{
    /**
     * Establish an SSH2 connection.
     *
     * @param string $host      Remote host.
     * @param int    $port      Remote port (typically 22).
     * @param array<string, mixed>  $methods   Optional connection methods (e.g. host key algorithms).
     * @param array<string, mixed>  $callbacks Optional callbacks for SSH2 negotiation.
     *
     * @return mixed SSH connection handle (resource) or false on failure.
     * @phpstan-return resource|false
     */
    public function connect(string $host, int $port, array $methods = [], array $callbacks = []);

    /**
     * Authenticate using username and password.
     */
    public function authPassword(mixed $connection, string $user, string $pass): bool;

    /**
     * Authenticate using public/private key files.
     *
     * @param mixed  $connection SSH connection handle.
     * @param string $user       Username.
     * @param string $pubkeyFile Path to public key file.
     * @param string $privkeyFile Path to private key file.
     */
    public function authPubkeyFile(
        mixed $connection,
        string $user,
        string $pubkeyFile,
        string $privkeyFile
    ): bool;

    /**
     * Initialize the SFTP subsystem.
     *
     * @return mixed SFTP handle (resource) or false on failure.
     * @phpstan-return resource|false
     */
    public function sftp(mixed $connection);

    /**
     * Retrieve stat information for a remote path.
     *
     * @return array<int|string, mixed>|false Stat array or false on failure.
     */
    public function sftpStat(mixed $sftp, string $path): array|false;

    /**
     * Delete a remote file.
     */
    public function sftpUnlink(mixed $sftp, string $path): bool;

    /**
     * Create a remote directory.
     *
     * @param int  $mode      Permissions (default: 0775).
     * @param bool $recursive Whether to create directories recursively.
     */
    public function sftpMkdir(mixed $sftp, string $directory, int $mode = 0775, bool $recursive = false): bool;

    /**
     * Remove a remote directory.
     */
    public function sftpRmdir(mixed $sftp, string $directory): bool;

    /**
     * Rename or move a remote file or directory.
     */
    public function sftpRename(mixed $sftp, string $from, string $to): bool;

    /**
     * Change permissions of a remote file or directory.
     */
    public function sftpChmod(mixed $sftp, string $path, int $mode): bool;

    /**
     * Retrieve the server host key fingerprint.
     *
     * Typical flags come from ext-ssh2 constants:
     * - SSH2_FINGERPRINT_MD5
     * - SSH2_FINGERPRINT_SHA1
     * combined with:
     * - SSH2_FINGERPRINT_HEX (default)
     * - SSH2_FINGERPRINT_RAW
     *
     * @param mixed $connection SSH connection handle.
     * @param int   $flags      Fingerprint flags.
     *
     * @return string|false Fingerprint string or false on failure.
     */
    public function fingerprint(mixed $connection, int $flags): string|false;
}
