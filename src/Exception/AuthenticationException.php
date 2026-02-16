<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Exception thrown when an authentication attempt fails.
 *
 * This exception is raised when:
 * - Login credentials are missing or invalid
 * - Password authentication fails
 * - Public key authentication fails
 * - Authentication is attempted before a connection is established
 *
 * It extends {@see FtpClientException} and is part of the
 * transport-level error hierarchy.
 */
final class AuthenticationException extends FtpClientException
{
}
