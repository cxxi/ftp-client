<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Exception;

/**
 * Thrown when a native PHP function returns a value
 * that does not match the expected type contract.
 */
final class NativeCallTypeMismatchException extends InfrastructureException
{
}
