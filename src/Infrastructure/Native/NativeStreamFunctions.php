<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\StreamFunctionsInterface;

/**
 * Native implementation of {@see StreamFunctionsInterface}.
 *
 * This class provides a thin abstraction over PHP's built-in stream
 * and directory functions. It is primarily used by the SFTP transport,
 * which relies on the "ssh2.sftp://" stream wrapper.
 *
 * Abstracting these calls improves testability and decouples
 * higher-level logic from global PHP functions.
 */
final class NativeStreamFunctions implements StreamFunctionsInterface
{
    /**
     * {@inheritDoc}
     */
    public function opendir(string $path): mixed
    {
        return \opendir($path);
    }

    /**
     * {@inheritDoc}
     */
    public function readdir(mixed $handle): string|false
    {
        return \readdir($handle);
    }

    /**
     * {@inheritDoc}
     */
    public function closedir(mixed $handle): void
    {
        \closedir($handle);
    }

    /**
     * {@inheritDoc}
     */
    public function fopen(string $path, string $mode): mixed
    {
        return \fopen($path, $mode);
    }

    /**
     * {@inheritDoc}
     */
    public function fclose(mixed $handle): void
    {
        \fclose($handle);
    }

    /**
     * {@inheritDoc}
     *
     * Set the timeout on a stream resource.
     */
    public function streamSetTimeout(mixed $handle, int $seconds): bool
    {
        return \stream_set_timeout($handle, $seconds);
    }

    /**
     * {@inheritDoc}
     *
     * Copy data from one stream to another.
     *
     * @return int|false Number of bytes copied, or false on failure.
     */
    public function streamCopyToStream(mixed $from, mixed $to): int|false
    {
        return \stream_copy_to_stream($from, $to);
    }
}
