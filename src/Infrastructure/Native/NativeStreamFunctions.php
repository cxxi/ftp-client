<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;
use Cxxi\FtpClient\Infrastructure\Port\StreamFunctionsInterface;

/**
 * Native implementation of {@see StreamFunctionsInterface}.
 *
 * This class provides a thin abstraction over PHP's built-in stream and
 * directory functions. It is primarily used by the SFTP transport, which often
 * relies on the "ssh2.sftp://" stream wrapper.
 *
 * The indirection through {@see NativeFunctionInvokerInterface} exists to:
 * - keep production behavior identical (real global calls),
 * - make unit tests deterministic and hermetic,
 * - keep the class `final` without relying on inheritance for testing.
 */
final class NativeStreamFunctions implements StreamFunctionsInterface
{
    /**
     * Invoker used for calling native/global functions.
     */
    private readonly NativeFunctionInvokerInterface $invoke;

    /**
     * @param NativeFunctionInvokerInterface|null $invoke
     *        Invoker used to call native stream/directory functions. If null, a default
     *        {@see NativeFunctionInvoker} is used.
     */
    public function __construct(?NativeFunctionInvokerInterface $invoke = null)
    {
        $this->invoke = $invoke ?? new NativeFunctionInvoker();
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \opendir()}.
     */
    public function opendir(string $path): mixed
    {
        return ($this->invoke)('opendir', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \readdir()}.
     */
    public function readdir(mixed $handle): string|false
    {
        /** @var string|false $out */
        $out = ($this->invoke)('readdir', [$handle]);

        return $out;
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \closedir()}.
     */
    public function closedir(mixed $handle): void
    {
        ($this->invoke)('closedir', [$handle]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \fopen()}.
     */
    public function fopen(string $path, string $mode): mixed
    {
        return ($this->invoke)('fopen', [$path, $mode]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \fclose()}.
     */
    public function fclose(mixed $handle): void
    {
        ($this->invoke)('fclose', [$handle]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \stream_set_timeout()}.
     */
    public function streamSetTimeout(mixed $handle, int $seconds): bool
    {
        return (bool) ($this->invoke)('stream_set_timeout', [$handle, $seconds]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \stream_copy_to_stream()}.
     *
     * @return int|false Number of bytes copied, or false on failure.
     */
    public function streamCopyToStream(mixed $from, mixed $to): int|false
    {
        /** @var int|false $out */
        $out = ($this->invoke)('stream_copy_to_stream', [$from, $to]);

        return $out;
    }
}
