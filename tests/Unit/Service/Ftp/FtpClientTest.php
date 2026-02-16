<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Service\Ftp;

use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\FtpFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Service\Ftp\FtpClient;
use Cxxi\FtpClient\Util\WarningCatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(FtpClient::class)]
final class FtpClientTest extends TestCase
{
    public function testDoConnectFtpUsesDefaultPort21WhenUrlPortIsNull(): void
    {
        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp
            ->expects(self::once())
            ->method('connect')
            ->with('example.com', 21, null)
            ->willReturn('HANDLE');

        $client = new FtpClient(
            url: new FtpUrl(Protocol::FTP, 'example.com', null, null, null, '/'),
            options: new ConnectionOptions(timeout: null),
            logger: new NullLogger(),
            extensions: $this->createMock(ExtensionCheckerInterface::class),
            ftp: $ftp,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $m = new \ReflectionMethod($client, 'doConnectFtp');
        $m->setAccessible(true);

        $handle = $m->invoke($client, null);

        self::assertSame('HANDLE', $handle);
    }

    public function testDoConnectFtpUsesUrlPortWhenProvided(): void
    {
        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp
            ->expects(self::once())
            ->method('connect')
            ->with('example.com', 2121, 10)
            ->willReturn('HANDLE2');

        $client = new FtpClient(
            url: new FtpUrl(Protocol::FTP, 'example.com', 2121, null, null, '/'),
            options: new ConnectionOptions(timeout: 10),
            logger: new NullLogger(),
            extensions: $this->createMock(ExtensionCheckerInterface::class),
            ftp: $ftp,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $m = new \ReflectionMethod($client, 'doConnectFtp');
        $m->setAccessible(true);

        $handle = $m->invoke($client, 10);

        self::assertSame('HANDLE2', $handle);
    }
}
