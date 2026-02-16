<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\FtpConnectionTypeCheckerInterface;

/**
 * Native FTP connection type checker.
 *
 * - PHP < 8.1: ext-ftp returns resource
 * - PHP >= 8.1: ext-ftp returns FTP\Connection (internal final class)
 *
 * This adapter allows higher-level code (TypedNativeInvoker) to accept both
 * return shapes while keeping unit tests deterministic and independent from ext-ftp.
 */
final class NativeFtpConnectionTypeChecker implements FtpConnectionTypeCheckerInterface
{
    /**
     * @var callable
     */
    private $classExists;

    /**
     * @param callable|null $classExists Internal seam for unit testing.
     *                                    Defaults to \class_exists.
     */
    public function __construct(?callable $classExists = null)
    {
        if ($classExists !== null) {
            $this->classExists = $classExists;
        } else {
            $this->classExists = [$this, 'defaultClassExists'];
        }
    }

    /**
     * Default implementation delegating to global class_exists().
     */
    private function defaultClassExists(string $class): bool
    {
        return \class_exists($class);
    }

    /**
     * {@inheritDoc}
     */
    public function isFtpConnection(mixed $value): bool
    {
        if (!\is_object($value)) {
            return false;
        }

        $exists = ($this->classExists)('FTP\\Connection');

        if (!$exists) {
            return false;
        }

        return $value instanceof \FTP\Connection;
    }
}
