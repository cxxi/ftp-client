<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;

/**
 * Native implementation of {@see ExtensionCheckerInterface}.
 *
 * This class delegates to PHP's built-in {@see extension_loaded()} function
 * to determine whether a given extension is available at runtime.
 *
 * It is primarily used by transport implementations to verify that
 * required extensions (e.g. ext-ftp, ext-ssh2) are installed before use.
 */
final class NativeExtensionChecker implements ExtensionCheckerInterface
{
    /**
     * Check whether a PHP extension is loaded.
     *
     * @param string $extension Extension name (e.g. "ftp", "ssh2").
     *
     * @return bool True if the extension is loaded, false otherwise.
     */
    public function loaded(string $extension): bool
    {
        return \extension_loaded($extension);
    }
}
