<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Exception thrown when a file or directory transfer operation fails.
 *
 * This exception is raised when:
 * - Uploading a file fails
 * - Downloading a file fails
 * - Deleting or renaming a remote path fails
 * - Creating or removing a directory fails
 * - A filesystem-related precondition is not met
 *
 * It extends {@see FtpClientException} and represents
 * operational errors occurring after a successful connection
 * and (usually) authentication.
 */
final class TransferException extends FtpClientException
{
}
