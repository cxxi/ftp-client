<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Exception thrown when a connection-related error occurs.
 *
 * This exception is raised when:
 * - A connection to the remote server cannot be established
 * - The connection is unexpectedly lost
 * - The SFTP subsystem cannot be initialized
 * - Host key verification fails
 *
 * It extends {@see FtpClientException} and represents
 * transport-level connectivity failures.
 */
final class ConnectionException extends FtpClientException
{
}
