<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Port;

/**
 * Detects whether a value represents a FTP connection handle.
 *
 * Rationale:
 * - PHP < 8.1: ext-ftp functions return a resource for connections
 * - PHP >= 8.1: ext-ftp functions may return an internal final object (FTP\Connection)
 *
 * This port exists so runtime-specific checks can be implemented in Native
 * and unit-tested without requiring ext-ftp.
 */
interface FtpConnectionTypeCheckerInterface
{
    /**
     * Returns true if the given value is a FTP connection object for the current runtime.
     */
    public function isFtpConnection(mixed $value): bool;
}
