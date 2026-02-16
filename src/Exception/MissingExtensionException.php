<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Exception thrown when a required PHP extension is not available.
 *
 * This exception is raised when:
 * - ext-ftp is required but not loaded
 * - ext-ssh2 is required but not loaded
 * - A transport-specific feature depends on a missing extension
 *
 * It extends {@see FtpClientException} and indicates
 * an environment configuration issue rather than a runtime
 * transport failure.
 */
final class MissingExtensionException extends FtpClientException
{
}
