<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Exception;

use Cxxi\FtpClient\Exception\AuthenticationException;
use Cxxi\FtpClient\Exception\ConnectionException;
use Cxxi\FtpClient\Exception\FtpClientException;
use Cxxi\FtpClient\Exception\InvalidFtpUrlException;
use Cxxi\FtpClient\Exception\MissingExtensionException;
use Cxxi\FtpClient\Exception\TransferException;
use Cxxi\FtpClient\Exception\UnsupportedProtocolException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthenticationException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(FtpClientException::class)]
#[CoversClass(InvalidFtpUrlException::class)]
#[CoversClass(MissingExtensionException::class)]
#[CoversClass(TransferException::class)]
#[CoversClass(UnsupportedProtocolException::class)]
final class ExceptionsTest extends TestCase
{
    #[DataProvider('exceptionsProvider')]
    public function testExceptionsAreInHierarchy(string $class): void
    {
        $previous = new \RuntimeException('prev', 41);

        /** @var \Throwable $e */
        $e = new $class('msg', 42, $previous);

        self::assertSame('msg', $e->getMessage());
        self::assertSame(42, $e->getCode());
        self::assertSame($previous, $e->getPrevious());

        self::assertInstanceOf(FtpClientException::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(\Throwable::class, $e);
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function exceptionsProvider(): array
    {
        return [
            'AuthenticationException' => [AuthenticationException::class],
            'ConnectionException' => [ConnectionException::class],
            'InvalidFtpUrlException' => [InvalidFtpUrlException::class],
            'MissingExtensionException' => [MissingExtensionException::class],
            'TransferException' => [TransferException::class],
            'UnsupportedProtocolException' => [UnsupportedProtocolException::class],
        ];
    }
}
