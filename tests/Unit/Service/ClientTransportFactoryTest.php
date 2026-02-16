<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Service;

use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\FtpFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\Ssh2FunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\StreamFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Service\ClientTransportFactory;
use Cxxi\FtpClient\Service\Ftp\FtpsTransport;
use Cxxi\FtpClient\Service\Ftp\FtpTransport;
use Cxxi\FtpClient\Service\Sftp\SftpTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ClientTransportFactory::class)]
final class ClientTransportFactoryTest extends TestCase
{
    #[DataProvider('createProvider')]
    public function testCreateBuildsExpectedTransportAndLogsContext(
        string $url,
        string $expectedClass,
        string $expectedProtocolValue
    ): void {
        /** @var ExtensionCheckerInterface&\PHPUnit\Framework\MockObject\MockObject $extensions */
        $extensions = $this->createMock(ExtensionCheckerInterface::class);

        $extensions
            ->method('loaded')
            ->willReturnMap([
                ['ftp', true],
                ['ssh2', true],
            ]);

        $factory = new ClientTransportFactory(
            extensions: $extensions,
            ftp: $this->createMock(FtpFunctionsInterface::class),
            ssh2: $this->createMock(Ssh2FunctionsInterface::class),
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
        );

        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                self::equalTo('Transport factory create()'),
                self::callback(function (array $context) use ($expectedProtocolValue): bool {
                    self::assertSame($expectedProtocolValue, $context['protocol'] ?? null);
                    self::assertSame('example.com', $context['host'] ?? null);
                    self::assertTrue(($context['port'] ?? null) === null || \is_int($context['port']));
                    self::assertIsString($context['path'] ?? null);
                    self::assertNotSame('', $context['path']);
                    self::assertStringStartsWith('/', $context['path']);

                    return true;
                })
            );

        $client = $factory->create($url, null, $logger);

        /** @var class-string<object> $expectedClass */
        self::assertInstanceOf($expectedClass, $client);
    }

    public function testCreateDoesNotLogWhenLoggerIsNull(): void
    {
        /** @var ExtensionCheckerInterface&\PHPUnit\Framework\MockObject\MockObject $extensions */
        $extensions = $this->createMock(ExtensionCheckerInterface::class);

        $extensions
            ->method('loaded')
            ->willReturnMap([
                ['ftp', true],
                ['ssh2', true],
            ]);

        $factory = new ClientTransportFactory(
            extensions: $extensions,
            ftp: $this->createMock(FtpFunctionsInterface::class),
            ssh2: $this->createMock(Ssh2FunctionsInterface::class),
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
        );

        $client = $factory->create('ftp://example.com/path', new ConnectionOptions(), null);

        self::assertInstanceOf(FtpTransport::class, $client);
    }

    /**
     * @return array<string, array{0:string, 1:class-string, 2:string}>
     */
    public static function createProvider(): array
    {
        return [
            'FTP' => ['ftp://example.com/base', FtpTransport::class, 'ftp'],
            'FTPS' => ['ftps://example.com/base', FtpsTransport::class, 'ftps'],
            'SFTP' => ['sftp://example.com/base', SftpTransport::class, 'sftp'],
        ];
    }
}
