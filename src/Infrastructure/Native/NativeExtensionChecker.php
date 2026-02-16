<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;

/**
 * Native implementation of {@see ExtensionCheckerInterface}.
 *
 * This class delegates to PHP's built-in {@see \extension_loaded()} function
 * to determine whether a given extension is available at runtime.
 *
 * The indirection through {@see NativeFunctionInvokerInterface} exists to:
 * - keep production behavior identical (real global call),
 * - make unit tests deterministic (no reliance on the runtime environment),
 * - keep the class `final` without requiring inheritance for testing.
 */
final class NativeExtensionChecker implements ExtensionCheckerInterface
{
    /**
     * Invoker used for calling native/global functions.
     */
    private readonly NativeFunctionInvokerInterface $invoke;

    /**
     * @param NativeFunctionInvokerInterface|null $invoke
     *        Invoker used to call native functions. If null, a default
     *        {@see NativeFunctionInvoker} is used.
     */
    public function __construct(?NativeFunctionInvokerInterface $invoke = null)
    {
        $this->invoke = $invoke ?? new NativeFunctionInvoker();
    }

    /**
     * Check whether a PHP extension is loaded.
     *
     * Delegates to {@see \extension_loaded()}.
     *
     * @param string $extension Extension name (e.g. "ftp", "ssh2", "Core").
     */
    public function loaded(string $extension): bool
    {
        return (bool) ($this->invoke)('extension_loaded', [$extension]);
    }
}
