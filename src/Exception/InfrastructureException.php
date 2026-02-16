<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Base exception for infrastructure-level errors occurring
 * inside the FTP client implementation.
 *
 * These errors indicate internal invariant violations or
 * unexpected behavior from native PHP functions.
 */
abstract class InfrastructureException extends FtpClientException
{
}
