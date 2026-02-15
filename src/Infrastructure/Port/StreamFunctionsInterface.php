<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Port;

/**
 * Contract for stream and directory-related operations.
 *
 * This interface abstracts PHP's native stream and directory functions
 * (e.g. fopen, opendir, stream_copy_to_stream) in order to:
 * - Decouple higher-level logic from global PHP functions
 * - Improve testability by allowing mock implementations
 * - Centralize stream handling behavior
 *
 * It is primarily used by the SFTP transport, which relies on
 * the "ssh2.sftp://" stream wrapper.
 */
interface StreamFunctionsInterface
{
    /**
     * Open a directory handle.
     *
     * @param string $path Directory path or stream URI.
     *
     * @return mixed Directory handle or false on failure.
     */
    public function opendir(string $path): mixed;

    /**
     * Read an entry from a directory handle.
     *
     * @param mixed $handle Directory handle.
     *
     * @return string|false Entry name or false when no more entries are available.
     */
    public function readdir(mixed $handle): string|false;

    /**
     * Close a directory handle.
     *
     * @param mixed $handle Directory handle.
     */
    public function closedir(mixed $handle): void;

    /**
     * Open a file or stream.
     *
     * @param string $path File path or stream URI.
     * @param string $mode fopen mode (e.g. "r", "w", "rb").
     *
     * @return mixed Stream handle or false on failure.
     */
    public function fopen(string $path, string $mode): mixed;

    /**
     * Close a file or stream handle.
     *
     * @param mixed $handle Stream handle.
     */
    public function fclose(mixed $handle): void;

    /**
     * Set a timeout on a stream.
     *
     * @param mixed $handle  Stream handle.
     * @param int   $seconds Timeout in seconds.
     *
     * @return bool True on success, false on failure.
     */
    public function streamSetTimeout(mixed $handle, int $seconds): bool;

    /**
     * Copy data from one stream to another.
     *
     * @param mixed $from Source stream.
     * @param mixed $to   Destination stream.
     *
     * @return int|false Number of bytes copied, or false on failure.
     */
    public function streamCopyToStream(mixed $from, mixed $to): int|false;
}
