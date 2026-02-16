<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Exception thrown when an unsupported protocol is requested.
 *
 * This exception is raised when:
 * - An unknown URL scheme is provided (e.g. not ftp, ftps, or sftp)
 * - The protocol cannot be mapped to a supported transport implementation
 *
 * It extends {@see FtpClientException} and represents
 * a configuration or input validation error.
 */
final class UnsupportedProtocolException extends FtpClientException
{
}
