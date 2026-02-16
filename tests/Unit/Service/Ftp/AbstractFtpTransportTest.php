<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Service\Ftp;

use Cxxi\FtpClient\Enum\PassiveMode;
use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Exception\AuthenticationException;
use Cxxi\FtpClient\Exception\ConnectionException;
use Cxxi\FtpClient\Exception\MissingExtensionException;
use Cxxi\FtpClient\Exception\TransferException;
use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\FtpFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Service\Ftp\AbstractFtpTransport;
use Cxxi\FtpClient\Util\WarningCatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(AbstractFtpTransport::class)]
final class AbstractFtpTransportTest extends TestCase
{
    private function makeUrl(
        Protocol $proto = Protocol::FTP,
        ?string $user = 'u',
        ?string $pass = 'p',
        string $path = '/base',
        ?int $port = null
    ): FtpUrl {
        return new FtpUrl($proto, 'example.com', $port, $user, $pass, $path);
    }

    /**
     * @return resource
     */
    private function dummyResource()
    {
        $h = \fopen('php://temp', 'r+');
        if ($h === false) {
            self::fail('Unable to create dummy resource for tests.');
        }

        return $h;
    }

    private function makeClient(
        ?ConnectionOptions $options = null,
        ?ExtensionCheckerInterface $ext = null,
        ?FtpFunctionsInterface $ftp = null,
        ?FilesystemFunctionsInterface $fs = null,
        mixed $connectResult = null,
        ?FtpUrl $url = null
    ): AbstractFtpTransport {
        $options ??= new ConnectionOptions();
        $ext ??= $this->createMock(ExtensionCheckerInterface::class);
        $ftp ??= $this->createMock(FtpFunctionsInterface::class);
        $fs ??= $this->createMock(FilesystemFunctionsInterface::class);
        $url ??= $this->makeUrl();

        $connectResult ??= $this->dummyResource();

        if (!\is_resource($connectResult) && $connectResult !== false) {
            throw new \InvalidArgumentException('connectResult must be a resource or false.');
        }

        /** @var resource|false $connectResultTyped */
        $connectResultTyped = $connectResult;

        return new class ($url, $options, new NullLogger(), $ext, $ftp, $fs, new WarningCatcher(), $connectResultTyped) extends AbstractFtpTransport {
            /**
             * @var resource|false
             */
            private $connectResult;

            /**
             * @param resource|false $connectResult
             *
             * @phpstan-param resource|false $connectResult
             */
            public function __construct(
                FtpUrl $url,
                ?ConnectionOptions $options,
                $logger,
                $extensions,
                $ftp,
                $fs,
                $warnings,
                $connectResult
            ) {
                $this->connectResult = $connectResult;
                parent::__construct($url, $options, $logger, $extensions, $ftp, $fs, $warnings);
            }

            /**
             * @return resource|false
             *
             * @phpstan-return resource|false
             */
            protected function doConnectFtp(?int $timeout)
            {
                return $this->connectResult;
            }
        };
    }

    private function connectAndLogin(AbstractFtpTransport $client, string $user = 'u', string $pass = 'p'): void
    {
        $client->connect()->loginWithPassword($user, $pass);
    }

    public function testConnectThrowsWhenFtpExtensionMissing(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(false);

        $client = $this->makeClient(ext: $ext);

        $this->expectException(MissingExtensionException::class);
        $this->expectExceptionMessage('ext-ftp is required');

        $client->connect();
    }

    public function testConnectThrowsWhenConnectHandleIsFalse(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $client = $this->makeClient(ext: $ext, connectResult: false);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unable to connect');

        $client->connect();
    }

    public function testConnectOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $client = $this->makeClient(ext: $ext, connectResult: $this->dummyResource());
        $client->connect();

        self::assertTrue($client->isConnected());
    }

    public function testLoginWithPasswordThrowsWhenNotConnected(): void
    {
        $client = $this->makeClient();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('connection not established');

        $client->loginWithPassword();
    }

    public function testLoginWithPasswordThrowsWhenCredsMissing(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);

        $client = $this->makeClient(
            ext: $ext,
            ftp: $ftp,
            url: $this->makeUrl(user: null, pass: null),
            connectResult: $this->dummyResource()
        );

        $client->connect();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing username or password');

        $client->loginWithPassword();
    }

    public function testLoginWithPasswordThrowsWhenLoginFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp);
        $client->connect();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Login failed');

        $client->loginWithPassword('u', 'p');
    }

    public function testLoginWithPasswordOkPassiveTrueCallsPasvTrue(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);

        $ftp->expects(self::once())
            ->method('pasv')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), true)
            ->willReturn(true);

        $client = $this->makeClient(
            options: new ConnectionOptions(passive: PassiveMode::TRUE),
            ext: $ext,
            ftp: $ftp
        );

        $client->connect()->loginWithPassword('u', 'p');
        self::assertTrue($client->isAuthenticated());
    }

    public function testLoginWithPasswordOkPassiveFalseCallsPasvFalse(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);

        $ftp->expects(self::once())
            ->method('pasv')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), false)
            ->willReturn(true);

        $client = $this->makeClient(
            options: new ConnectionOptions(passive: PassiveMode::FALSE),
            ext: $ext,
            ftp: $ftp
        );

        $client->connect()->loginWithPassword('u', 'p');
        self::assertTrue($client->isAuthenticated());
    }

    public function testLoginWithPasswordOkPassiveAutoFallsBackWhenNlistFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);

        $pasvCall = 0;
        $ftp->expects(self::exactly(2))
            ->method('pasv')
            ->willReturnCallback(function (mixed $conn, bool $enabled) use (&$pasvCall): bool {
                self::assertTrue(\is_resource($conn));
                $pasvCall++;

                if ($pasvCall === 1) {
                    self::assertTrue($enabled);
                    return true;
                }

                if ($pasvCall === 2) {
                    self::assertFalse($enabled);
                    return true;
                }

                throw new \LogicException('Unexpected pasv() call count');
            });

        $ftp->expects(self::once())
            ->method('nlist')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), '.')
            ->willReturn(false);

        $client = $this->makeClient(
            options: new ConnectionOptions(passive: PassiveMode::AUTO),
            ext: $ext,
            ftp: $ftp
        );

        $client->connect()->loginWithPassword('u', 'p');
        self::assertTrue($client->isAuthenticated());
    }

    public function testLoginWithPasswordOkPassiveAutoNoFallbackWhenNlistOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);

        $ftp->expects(self::once())
            ->method('pasv')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), true)
            ->willReturn(true);

        $ftp->expects(self::once())
            ->method('nlist')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), '.')
            ->willReturn([]);

        $client = $this->makeClient(
            options: new ConnectionOptions(passive: PassiveMode::AUTO),
            ext: $ext,
            ftp: $ftp
        );

        $client->connect()->loginWithPassword('u', 'p');
        self::assertTrue($client->isAuthenticated());
    }

    public function testCloseConnectionIsIdempotentAndResetsFlags(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->expects(self::once())
            ->method('close')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)))
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ftp: $ftp);
        $client->connect()->loginWithPassword('u', 'p');

        $client->closeConnection();
        self::assertFalse($client->isConnected());
        self::assertFalse($client->isAuthenticated());

        $client->closeConnection();
    }

    public function testListFilesEnsuresDirectoryAndFiltersHidden(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);

        $ftp->expects(self::once())
            ->method('pasv')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), true)
            ->willReturn(true);

        $ftp->expects(self::once())->method('pwd')->willReturn('/base');

        $ftp->expects(self::once())
            ->method('nlist')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), '.')
            ->willReturn([
                '.hidden',
                '/base/.hidden2',
                'a.txt',
                '/base/b.txt',
            ]);

        $client = $this->makeClient(
            options: new ConnectionOptions(passive: PassiveMode::TRUE),
            ext: $ext,
            ftp: $ftp
        );
        $this->connectAndLogin($client);

        self::assertSame(['a.txt', '/base/b.txt'], $client->listFiles('.'));
    }

    public function testListFilesThrowsWhenNlistFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('nlist')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to list files');

        $client->listFiles('.');
    }

    public function testRawListThrowsWhenRawlistFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('rawlist')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to raw list');

        $client->rawList('.', false);
    }

    public function testRawListOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('rawlist')->willReturn(['l1', 'l2']);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        self::assertSame(['l1', 'l2'], $client->rawList('.', true));
    }

    public function testMlsdThrowsWhenMlsdFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('mlsd')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to MLSD');

        $client->mlsd('.');
    }

    public function testMlsdOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('mlsd')->willReturn([
            ['name' => 'a.txt', 'type' => 'file'],
        ]);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        self::assertSame([['name' => 'a.txt', 'type' => 'file']], $client->mlsd('.'));
    }

    public function testDownloadThrowsWhenGetFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('get')->willReturn(false);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->willReturn('/tmp');
        $fs->method('isDir')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, fs: $fs, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Download "r.txt"');

        $client->downloadFile('r.txt', '/tmp/l.txt');
    }

    public function testDownloadOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('get')->willReturn(true);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->willReturn('/tmp');
        $fs->method('isDir')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, fs: $fs, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->downloadFile('r.txt', '/tmp/l.txt');
        self::assertTrue($client->isAuthenticated());
    }

    public function testPutThrowsWhenPutFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('put')->willReturn(false);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, fs: $fs, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Upload "/tmp/source.txt"');

        $client->putFile('dest.txt', '/tmp/source.txt');
    }

    public function testPutOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('put')->willReturn(true);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, fs: $fs, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->putFile('dest.txt', '/tmp/source.txt');
        self::assertTrue($client->isAuthenticated());
    }

    public function testIsDirectoryReturnsFalseWhenPwdInvalid(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturnOnConsecutiveCalls('/base', false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        self::assertFalse($client->isDirectory('x'));
    }

    public function testIsDirectoryTrueWhenChdirOkAndReturnsToCurrent(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturnOnConsecutiveCalls('/base', '/base');

        $ftp->method('chdir')->willReturnOnConsecutiveCalls(true, true);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        self::assertTrue($client->isDirectory('dir'));
    }

    public function testIsDirectoryFalseWhenChdirFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturnOnConsecutiveCalls('/base', '/base');
        $ftp->method('chdir')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        self::assertFalse($client->isDirectory('dir'));
    }

    public function testDeleteThrowsWhenDeleteFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('delete')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to delete');

        $client->deleteFile('x.txt');
    }

    public function testEnsureFtpDirectoryThrowsWhenPwdInvalid(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to determine current directory');

        $client->listFiles('.');
    }

    public function testEnsureFtpDirectoryChdirWhenNotOnBase(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/else');

        $ftp->expects(self::once())
            ->method('chdir')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), '/base')
            ->willReturn(true);

        $ftp->method('nlist')->willReturn([]);

        $client = $this->makeClient(ext: $ext, ftp: $ftp);
        $this->connectAndLogin($client);

        self::assertSame([], $client->listFiles('.'));
    }

    public function testEnsureFtpDirectoryThrowsWhenChdirFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/else');
        $ftp->method('chdir')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to change directory');

        $client->listFiles('.');
    }

    public function testMakeDirectoryReturnsEarlyOnEmptyOrDot(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->expects(self::never())->method('mkdir');

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->makeDirectory('');
        $client->makeDirectory('.');
        self::assertTrue($client->isAuthenticated());
    }

    public function testMakeDirectoryRecursiveSkipsAlreadyExistingDirectories(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturnOnConsecutiveCalls('/base', '/base', '/base', '/base', '/base', '/base');

        $ftp->method('chdir')->willReturnOnConsecutiveCalls(true, true, true, true);

        $ftp->expects(self::never())->method('mkdir');

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->makeDirectory('a/b', recursive: true);
        self::assertTrue($client->isAuthenticated());
    }

    public function testMakeDirectoryRecursiveCreatesMissingDirectoryWhenMkdirOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chdir')->willReturnOnConsecutiveCalls(false, false);

        $mkdirCall = 0;
        $ftp->expects(self::exactly(2))
            ->method('mkdir')
            ->willReturnCallback(function (mixed $conn, string $dir) use (&$mkdirCall) {
                self::assertTrue(\is_resource($conn));
                $mkdirCall++;

                if ($mkdirCall === 1) {
                    self::assertSame('a', $dir);
                    return 'a';
                }

                if ($mkdirCall === 2) {
                    self::assertSame('a/b', $dir);
                    return 'a/b';
                }

                throw new \LogicException('Unexpected mkdir() call count');
            });

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->makeDirectory('a/b', recursive: true);
        self::assertTrue($client->isAuthenticated());
    }

    public function testMakeDirectoryRecursiveMkdirFailsButDirectoryExistsAfterwardsSoNoThrow(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $chdirCalls = 0;
        $ftp->method('chdir')->willReturnCallback(function () use (&$chdirCalls) {
            $chdirCalls++;

            return match ($chdirCalls) {
                1 => false,
                2 => true,
                3 => true,
                default => true,
            };
        });

        $ftp->expects(self::once())
            ->method('mkdir')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), 'a')
            ->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->makeDirectory('a', recursive: true);
        self::assertTrue($client->isAuthenticated());
    }

    public function testMakeDirectoryRecursiveMkdirFailsAndDirectoryStillMissingThrows(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chdir')->willReturn(false);

        $ftp->method('mkdir')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to create directory "a"');

        $client->makeDirectory('a', recursive: true);
    }

    public function testMakeDirectoryNonRecursiveCreatesOrThrows(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->expects(self::once())
            ->method('mkdir')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), 'a/b')
            ->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to create directory "a/b"');

        $client->makeDirectory('a/b', recursive: false);
    }

    public function testRemoveDirectoryThrowsWhenRmdirFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('rmdir')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to remove directory');

        $client->removeDirectory('x');
    }

    public function testRemoveDirectoryRecursiveThrowsWhenTargetNotDirectory(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chdir')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('is not a directory');

        $client->removeDirectoryRecursive('dir');
    }

    public function testRemoveDirectoryRecursiveThrowsWhenNlistFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chdir')->willReturn(true);

        $ftp->method('nlist')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to list directory');

        $client->removeDirectoryRecursive('dir');
    }

    public function testRemoveDirectoryRecursiveDeletesEntriesWithFallbackJoinAndThenRmdirOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);

        $ftp->expects(self::once())
            ->method('pasv')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), true)
            ->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chdir')->willReturnCallback(function (mixed $conn, string $dir) {
            self::assertTrue(\is_resource($conn));

            return match ($dir) {
                'dir' => true,
                'dir/file1' => false,
                'file2' => false,
                'dir/file2' => false,
                default => true,
            };
        });

        $ftp->method('nlist')->willReturnCallback(function (mixed $conn, string $dir) {
            self::assertTrue(\is_resource($conn));

            if ($dir === 'dir') {
                return [
                    'dir/.',
                    'dir/..',
                    'dir/file1',
                    'file2',
                ];
            }

            return [];
        });

        $deleteCalls = 0;
        $ftp->expects(self::exactly(3))
            ->method('delete')
            ->willReturnCallback(function (mixed $conn, string $path) use (&$deleteCalls) {
                self::assertTrue(\is_resource($conn));

                $deleteCalls++;

                if ($deleteCalls === 1) {
                    self::assertSame('dir/file1', $path);
                    return true;
                }

                if ($deleteCalls === 2) {
                    self::assertSame('file2', $path);
                    return false;
                }

                if ($deleteCalls === 3) {
                    self::assertSame('dir/file2', $path);
                    return true;
                }

                throw new \LogicException('Unexpected delete() call count');
            });

        $ftp->expects(self::once())
            ->method('rmdir')
            ->willReturnCallback(function (mixed $conn, string $dir) {
                self::assertTrue(\is_resource($conn));
                self::assertSame('dir', $dir);
                return true;
            });

        $client = $this->makeClient(
            options: new ConnectionOptions(passive: PassiveMode::TRUE),
            ext: $ext,
            ftp: $ftp
        );
        $this->connectAndLogin($client);

        $client->removeDirectoryRecursive('dir');
        self::assertTrue($client->isAuthenticated());
    }

    public function testRemoveDirectoryRecursiveWhenDeleteFailsAndJoinedIsDirectoryThenRecurses(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chdir')->willReturnOnConsecutiveCalls(
            true,
            true,
            false,
            true,
            true,
            true,
            true,
            false
        );

        $ftp->method('nlist')->willReturnCallback(function (mixed $conn, string $dir) {
            if ($dir === 'dir') {
                return ['child'];
            }
            if ($dir === 'dir/child') {
                return ['dir/child/file'];
            }
            return [];
        });

        $ftp->method('delete')->willReturnCallback(function (mixed $conn, string $path) {
            if ($path === 'child') {
                return false;
            }
            if ($path === 'dir/child/file') {
                return true;
            }
            return false;
        });

        $ftp->method('rmdir')->willReturnCallback(function (mixed $conn, string $dir) {
            return \in_array($dir, ['dir/child', 'dir'], true);
        });

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->removeDirectoryRecursive('dir');
        self::assertTrue($client->isAuthenticated());
    }

    public function testRemoveDirectoryRecursiveThrowsWhenDeleteFailsEvenAfterJoin(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chdir')->willReturnCallback(function (mixed $conn, string $dir) {
            self::assertTrue(\is_resource($conn));

            return match ($dir) {
                'dir' => true,
                'bad' => false,
                'dir/bad' => false,
                default => true,
            };
        });

        $ftp->method('nlist')->willReturnCallback(function (mixed $conn, string $dir) {
            self::assertTrue(\is_resource($conn));

            if ($dir === 'dir' || $dir === '.') {
                return ['bad'];
            }

            return [];
        });

        $ftp->method('delete')->willReturnCallback(function (mixed $conn, string $path) {
            self::assertTrue(\is_resource($conn));
            return false;
        });

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to delete "bad"');

        $client->removeDirectoryRecursive('dir');
    }

    public function testRemoveDirectoryRecursiveThrowsWhenFinalRmdirFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);

        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chdir')->willReturnCallback(function (mixed $conn, string $dir) {
            self::assertTrue(\is_resource($conn));

            return match ($dir) {
                'dir' => true,
                default => true,
            };
        });

        $ftp->method('nlist')->willReturnCallback(function (mixed $conn, string $dir) {
            self::assertTrue(\is_resource($conn));

            if ($dir === 'dir' || $dir === '.') {
                return [];
            }

            return [];
        });

        $ftp->expects(self::once())
            ->method('rmdir')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), 'dir')
            ->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to remove directory "dir"');

        $client->removeDirectoryRecursive('dir');
    }

    public function testGetSizeAndMtimeNullOrInts(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('size')->willReturn(-1);
        $ftp->method('mdtm')->willReturn(123);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        self::assertNull($client->getSize('x'));
        self::assertSame(123, $client->getMTime('x'));
    }

    public function testGetMtimeNullWhenNegative(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('mdtm')->willReturn(-1);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        self::assertNull($client->getMTime('x'));
    }

    public function testChmodThrowsWhenChmodFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chmod')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to chmod');

        $client->chmod('x', 0644);
    }

    public function testChmodOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('chmod')->willReturn(0644);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->chmod('x', 0644);
        self::assertTrue($client->isAuthenticated());
    }

    public function testRenameThrowsWhenRenameFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('rename')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to rename');

        $client->rename('a', 'b');
    }

    public function testRenameOk(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->method('rename')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->rename('a', 'b');
        self::assertTrue($client->isAuthenticated());
    }

    public function testDestructorSwallowsThrowableFromCloseConnection(): void
    {
        self::expectNotToPerformAssertions();

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);

        $client = new class (
            $this->makeUrl(),
            new ConnectionOptions(),
            new NullLogger(),
            $ext,
            $ftp,
            $this->createMock(FilesystemFunctionsInterface::class),
            new WarningCatcher()
        ) extends AbstractFtpTransport {
            /**
             * @return resource|false
             *
             * @phpstan-return resource|false
             */
            protected function doConnectFtp(?int $timeout)
            {
                $h = \fopen('php://temp', 'r+');
                return $h === false ? false : $h;
            }

            public function closeConnection(): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $client->__destruct();
    }

    public function testMakeDirectoryReturnsEarlyWhenRemoteDirIsOnlySlashes(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->expects(self::never())->method('mkdir');

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->makeDirectory('/', recursive: false);
        $client->makeDirectory('////', recursive: true);

        self::assertTrue($client->isAuthenticated());
    }

    public function testMakeDirectoryNonRecursiveSuccessReturns(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);
        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');

        $ftp->expects(self::once())
            ->method('mkdir')
            ->with(self::callback(static fn ($conn): bool => \is_resource($conn)), 'a/b')
            ->willReturn('a/b');

        $client = $this->makeClient(ext: $ext, ftp: $ftp, connectResult: $this->dummyResource());
        $this->connectAndLogin($client);

        $client->makeDirectory('a/b', recursive: false);

        self::assertTrue($client->isAuthenticated());
    }

    public function testRemoveDirectoryRecursiveWhenCandidateIsDirectoryThenRecursesAndContinues(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ftp')->willReturn(true);

        $ftp = $this->createMock(FtpFunctionsInterface::class);
        $ftp->method('login')->willReturn(true);

        $ftp->method('pasv')->willReturn(true);
        $ftp->method('pwd')->willReturn('/base');
        $ftp->method('chdir')->willReturn(true);

        $ftp->method('nlist')->willReturnCallback(function (mixed $conn, string $dir) {
            self::assertTrue(\is_resource($conn));

            return match ($dir) {
                'dir' => ['dir/.', 'dir/..', 'dir/subdir'],
                'dir/subdir' => ['dir/subdir/.', 'dir/subdir/..'],
                default => [],
            };
        });

        $ftp->expects(self::never())->method('delete');

        $ftp->expects(self::exactly(2))
            ->method('rmdir')
            ->willReturnCallback(function (mixed $conn, string $dir) {
                self::assertTrue(\is_resource($conn));
                self::assertContains($dir, ['dir/subdir', 'dir']);
                return true;
            });

        $client = $this->makeClient(
            options: new ConnectionOptions(passive: PassiveMode::TRUE),
            ext: $ext,
            ftp: $ftp,
            connectResult: $this->dummyResource()
        );
        $this->connectAndLogin($client);

        $client->removeDirectoryRecursive('dir');
        self::assertTrue($client->isAuthenticated());
    }
}
