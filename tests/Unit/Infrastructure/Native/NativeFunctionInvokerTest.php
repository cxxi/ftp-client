<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Native\NativeFunctionInvoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeFunctionInvoker::class)]
final class NativeFunctionInvokerTest extends TestCase
{
    public function testInvokeCallsGlobalFunction(): void
    {
        $invoke = new NativeFunctionInvoker();

        self::assertSame(3, $invoke('strlen', ['abc']));
    }

    public function testFunctionExistsUsesRuntime(): void
    {
        $invoke = new NativeFunctionInvoker();

        self::assertTrue($invoke->functionExists('strlen'));
        self::assertFalse($invoke->functionExists('__definitely_not_a_function__'));
    }

    public function testInvokeThrowsWhenFunctionIsNotCallable(): void
    {
        $invoker = new \Cxxi\FtpClient\Infrastructure\Native\NativeFunctionInvoker();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not callable');

        $invoker('__this_function_does_not_exist__', []);
    }
}
