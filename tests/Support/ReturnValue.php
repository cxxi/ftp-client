<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Support;

/**
 * Wrapper to force an array/list to be treated as a single return value
 * (not as a sequential queue of return values).
 */
final class ReturnValue
{
    public function __construct(
        public readonly mixed $value
    ) {
    }
}
