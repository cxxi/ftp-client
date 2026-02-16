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
     * Typed wrapper over {@see NativeFunctionInvokerInterface} ensuring
     * deterministic return types for static analysis and runtime safety.
     */
    private readonly TypedNativeInvoker $typed;

    /**
     * @param NativeFunctionInvokerInterface|null $invoke
     *        Invoker used to call native stream/directory functions. If null, a default
     *        {@see NativeFunctionInvoker} is used.
     */
    public function __construct(?NativeFunctionInvokerInterface $invoke = null)
    {
        $this->invoke = $invoke ?? new NativeFunctionInvoker();
        $this->typed = new TypedNativeInvoker($this->invoke);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \opendir()}.
     *
     * @return resource|false
     * @phpstan-return resource|false
     */
    public function opendir(string $path)
    {
        return $this->typed->resourceOrFalse('opendir', [$path]);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to {@see \readdir()}.
     */
    public function readdir(mixed $handle): string|false
    {
        return $this->typed->stringOrFalse('readdir', [$handle]);
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
     *
     * @return resource|false
     * @phpstan-return resource|false
     */
    public function fopen(string $path, string $mode)
    {
        return $this->typed->resourceOrFalse('fopen', [$path, $mode]);
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
        return $this->typed->bool('stream_set_timeout', [$handle, $seconds]);
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
        return $this->typed->intOrFalse('stream_copy_to_stream', [$from, $to]);
    }
}
