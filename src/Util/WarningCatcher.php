<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Util;

/**
 * Utility class to capture and optionally swallow PHP warnings
 * emitted during the execution of a callable.
 *
 * This is especially useful when interacting with native extensions
 * (e.g. ext-ftp, ext-ssh2) that emit warnings instead of throwing exceptions.
 *
 * The catcher:
 * - Temporarily installs a custom error handler
 * - Stores the last warning message
 * - Optionally swallows specific error levels (based on a mask)
 * - Throws when the mask does not match (so the warning "bubbles")
 * - Restores the previous error handler after execution
 */
final class WarningCatcher
{
    /**
     * Last captured warning message, if any.
     */
    private ?string $lastWarning = null;

    /**
     * @param int $swallowMask Bitmask of PHP error levels to swallow.
     *
     * By default, this includes:
     * - E_WARNING
     * - E_USER_WARNING
     * - E_NOTICE
     * - E_USER_NOTICE
     * - E_DEPRECATED
     * - E_USER_DEPRECATED
     *
     * Errors matching the mask are swallowed (handler returns true).
     * Other errors are converted to exceptions and will bubble up.
     */
    public function __construct(
        private readonly int $swallowMask = E_WARNING
            | E_USER_WARNING
            | E_NOTICE
            | E_USER_NOTICE
            | E_DEPRECATED
            | E_USER_DEPRECATED
    ) {
    }

    /**
     * Execute a callable while capturing warnings.
     *
     * @template T
     *
     * @param callable():T $fn Callable to execute.
     *
     * @return T The callable's return value.
     */
    public function run(callable $fn)
    {
        $this->lastWarning = null;

        \set_error_handler(function (int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool {
            $this->lastWarning = $errstr;

            if (($errno & $this->swallowMask) !== 0) {
                return true;
            }

            throw new \ErrorException(
                $errstr,
                0,
                $errno,
                $errfile ?? '',
                $errline ?? 0
            );
        });

        try {
            return $fn();
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * Return the last captured warning formatted for inclusion in error messages.
     */
    public function formatLastWarning(): string
    {
        return ($this->lastWarning !== null && $this->lastWarning !== '')
            ? \sprintf(' Details: %s', $this->lastWarning)
            : '';
    }

    /**
     * Get the raw last captured warning message.
     */
    public function getLastWarning(): ?string
    {
        return $this->lastWarning;
    }
}
