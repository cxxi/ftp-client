<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Base exception for all FTP client-related errors.
 *
 * All transport, authentication, connection, and transfer
 * exceptions extend this class.
 *
 * Extending \RuntimeException allows consumers to either:
 * - Catch specific transport exceptions (e.g. AuthenticationException),
 * - Or catch this base type to handle all client-related errors
 *   in a single place.
 */
abstract class FtpClientException extends \RuntimeException
{
}
