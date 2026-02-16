<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Util;

use Cxxi\FtpClient\Util\WarningCatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WarningCatcher::class)]
final class WarningCatcherTest extends TestCase
{
    public function testRunCapturesWarningAndSwallowsWhenMaskMatches(): void
    {
        $catcher = new WarningCatcher(E_USER_WARNING);

        $result = $catcher->run(static function (): string {
            \trigger_error('hello warning', E_USER_WARNING);
            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame('hello warning', $catcher->getLastWarning());
        self::assertSame(' Details: hello warning', $catcher->formatLastWarning());
    }

    public function testRunDoesNotSwallowWhenMaskDoesNotMatchAndStillCapturesMessage(): void
    {
        $catcher = new WarningCatcher(0);

        try {
            $catcher->run(static function (): void {
                \trigger_error('should bubble', E_USER_WARNING);
            });

            self::fail('Expected an exception to be thrown.');
        } catch (\Throwable $e) {
            self::assertStringContainsString('should bubble', $e->getMessage());
        }

        self::assertSame('should bubble', $catcher->getLastWarning());
        self::assertSame(' Details: should bubble', $catcher->formatLastWarning());
    }

    public function testRunResetsLastWarningBetweenRuns(): void
    {
        $catcher = new WarningCatcher(E_USER_WARNING);

        $catcher->run(static function (): void {
            \trigger_error('first', E_USER_WARNING);
        });
        self::assertSame('first', $catcher->getLastWarning());

        $catcher->run(static function (): void {
        });

        self::assertNull($catcher->getLastWarning());
        self::assertSame('', $catcher->formatLastWarning());
    }

    public function testRunRestoresPreviousErrorHandlerEvenWhenCallableThrows(): void
    {
        $catcher = new WarningCatcher(E_USER_WARNING);

        $previousHandlerCalled = false;

        \set_error_handler(static function () use (&$previousHandlerCalled): bool {
            $previousHandlerCalled = true;

            return true;
        });

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('boom');

            try {
                $catcher->run(static function (): void {
                    throw new \RuntimeException('boom');
                });
            } finally {
                // Même si run() throw, son finally doit restaurer le handler précédent.
                \trigger_error('after', E_USER_WARNING);
                self::assertTrue($previousHandlerCalled, 'Previous error handler was not restored.');
            }
        } finally {
            \restore_error_handler();
        }
    }
}
