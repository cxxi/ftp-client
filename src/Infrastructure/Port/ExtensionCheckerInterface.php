<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Port;

/**
 * Contract for checking whether a PHP extension is loaded.
 *
 * This abstraction allows transport implementations to verify
 * required extensions (e.g. ext-ftp, ext-ssh2) without directly
 * coupling to PHP's global extension_loaded() function.
 *
 * It also improves testability by enabling mocked implementations.
 */
interface ExtensionCheckerInterface
{
    /**
     * Determine whether a given PHP extension is loaded.
     *
     * @param string $extension Extension name (e.g. "ftp", "ssh2").
     *
     * @return bool True if the extension is available, false otherwise.
     */
    public function loaded(string $extension): bool;
}
