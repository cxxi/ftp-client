<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Native\NativeFtpConnectionTypeChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeFtpConnectionTypeChecker::class)]
final class NativeFtpConnectionTypeCheckerTest extends TestCase
{
    public function testItUsesDefaultCallableWhenNoCallableProvided(): void
    {
        $checker = new NativeFtpConnectionTypeChecker();

        self::assertFalse($checker->isFtpConnection(new \stdClass()));
    }

    public function testItReturnsFalseWhenValueIsNotObject(): void
    {
        $checker = new NativeFtpConnectionTypeChecker();

        self::assertFalse($checker->isFtpConnection(null));
        self::assertFalse($checker->isFtpConnection(false));
        self::assertFalse($checker->isFtpConnection(123));
        self::assertFalse($checker->isFtpConnection('nope'));
        self::assertFalse($checker->isFtpConnection([]));
    }

    public function testItReturnsFalseWhenClassDoesNotExist(): void
    {
        $checker = new NativeFtpConnectionTypeChecker(
            static function (string $class): bool {
                return false;
            }
        );

        self::assertFalse($checker->isFtpConnection(new \stdClass()));
    }
}
