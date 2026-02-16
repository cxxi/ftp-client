<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Enum;

/**
 * Passive mode configuration for FTP/FTPS transports.
 *
 * This enum controls how the client configures passive mode
 * (PASV) when using the FTP protocol family.
 *
 * - AUTO  : Try passive mode first and fall back if necessary.
 * - TRUE  : Force passive mode.
 * - FALSE : Disable passive mode (active mode).
 */
enum PassiveMode: string
{
    /**
     * Automatically determine passive mode behavior.
     *
     * The client typically enables passive mode first and may
     * fall back to active mode if listing or transfers fail.
     */
    case AUTO = 'auto';

    /**
     * Force passive mode (PASV).
     */
    case TRUE = 'true';

    /**
     * Disable passive mode (use active mode).
     */
    case FALSE = 'false';
}
