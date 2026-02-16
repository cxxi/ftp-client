<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Native\NativeExtensionChecker;
use Cxxi\FtpClient\Tests\Support\RecordingInvoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeExtensionChecker::class)]
final class NativeExtensionCheckerTest extends TestCase
{
    public function testLoadedDelegatesToInvoker(): void
    {
        $invoker = new RecordingInvoker(
            returnsByFunction: [
                'extension_loaded' => true,
            ]
        );

        $checker = new NativeExtensionChecker($invoker);

        self::assertTrue($checker->loaded('Core'));
        self::assertSame([['extension_loaded', ['Core']]], $invoker->calls);
    }

    public function testLoadedReturnsFalseWhenInvokerReturnsFalse(): void
    {
        $invoker = new RecordingInvoker(
            returnsByFunction: [
                'extension_loaded' => false,
            ]
        );

        $checker = new NativeExtensionChecker($invoker);

        self::assertFalse($checker->loaded('__definitely_not_an_extension__'));
        self::assertSame([['extension_loaded', ['__definitely_not_an_extension__']]], $invoker->calls);
    }
}
