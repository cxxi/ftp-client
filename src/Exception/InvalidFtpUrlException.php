<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Exception thrown when an FTP/SFTP URL is invalid.
 *
 * This exception is raised when:
 * - The URL cannot be parsed
 * - The scheme is missing or unsupported
 * - The host is missing
 * - The URL structure does not match expected FTP/FTPS/SFTP formats
 *
 * It extends {@see FtpClientException} and represents
 * configuration or input validation errors.
 */
final class InvalidFtpUrlException extends FtpClientException
{
}
