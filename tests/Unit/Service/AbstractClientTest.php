<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Service;

use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Exception\MissingExtensionException;
use Cxxi\FtpClient\Exception\TransferException;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Service\AbstractClient;
use Cxxi\FtpClient\Util\WarningCatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(AbstractClient::class)]
final class AbstractClientTest extends TestCase
{
    private function makeUrl(): FtpUrl
    {
        return new FtpUrl(Protocol::SFTP, 'example.com', null, 'u', 'p', '/base');
    }

    private function makeClient(
        ?ConnectionOptions $options = null,
        ?LoggerInterface $logger = null,
        ?FilesystemFunctionsInterface $fs = null,
        ?WarningCatcher $warnings = null
    ): TestClient {
        return new TestClient(
            url: $this->makeUrl(),
            options: $options,
            logger: $logger,
            fs: $fs,
            warnings: $warnings
        );
    }

    public function testConstructorSetsFieldsAndDefaultDeps(): void
    {
        $client = $this->makeClient();

        self::assertFalse($client->isConnected());
        self::assertFalse($client->isAuthenticated());

        $client->setConnection('X');
        self::assertTrue($client->isConnected());

        $client->setAuthenticated(true);
        self::assertTrue($client->isAuthenticated());
    }

    public function testAssertReadyForTransferFailsWhenNotConnected(): void
    {
        $client = $this->makeClient();
        $client->setAuthenticated(true);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('connection has not been established');

        $client->listFiles('.');
    }

    public function testAssertReadyForTransferFailsWhenNotAuthenticated(): void
    {
        $client = $this->makeClient();
        $client->setConnection('X');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('session is not authenticated');

        $client->listFiles('.');
    }

    public function testPutFileThrowsWhenLocalFileMissingOrUnreadable(): void
    {
        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(false);
        $fs->method('isReadable')->willReturn(false);

        $client = $this->makeClient(
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            fs: $fs,
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        $client->putFile('/remote.txt', '/local.txt');
    }

    public function testPutFileCallsDoPutFileAndReturnsSelf(): void
    {
        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: $logger,
            fs: $fs,
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->putFile('/remote.txt', '/local.txt');

        self::assertSame($client, $out);
        self::assertSame([['/remote.txt', '/local.txt']], $client->calls['doPutFile']);
    }

    public function testListFilesUsesRetryWhenSafeAndRetryEnabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $logger->expects(self::once())->method('debug');

        $client = $this->makeClient(
            options: new ConnectionOptions(
                retryMax: 1,
                retryDelayMs: 0,
                retryBackoff: 2.0,
                retryJitter: false,
                retryUnsafeOperations: false
            ),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->failures['doListFiles'] = 1;

        $files = $client->listFiles('/dir');

        self::assertSame(['a.txt', 'b.txt'], $files);
        self::assertSame(2, $client->counters['doListFiles']);
    }

    public function testUnsafeOperationDoesNotRetryWhenRetryUnsafeOperationsIsFalse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $client = $this->makeClient(
            options: new ConnectionOptions(
                retryMax: 3,
                retryDelayMs: 0,
                retryUnsafeOperations: false
            ),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->failures['doDeleteFile'] = 5;

        $this->expectException(TransferException::class);
        $client->deleteFile('/x');

        self::assertSame(1, $client->counters['doDeleteFile']);
    }

    public function testUnsafeOperationRetriesWhenEnabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $client = $this->makeClient(
            options: new ConnectionOptions(
                retryMax: 1,
                retryDelayMs: 1,
                retryBackoff: 2.0,
                retryJitter: true,
                retryUnsafeOperations: true
            ),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->failures['doDeleteFile'] = 1;

        $out = $client->deleteFile('/x');

        self::assertSame($client, $out);
        self::assertSame(2, $client->counters['doDeleteFile']);
    }

    public function testWithRetryDoesNotRetryNonFtpClientException(): void
    {
        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 3),
            logger: new NullLogger(),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->throwNonClientExceptionIn = 'doGetSize';

        $this->expectException(\RuntimeException::class);
        $client->getSize('/x');

        self::assertSame(1, $client->counters['doGetSize']);
    }

    public function testWithRetryDoesNotRetryMissingExtensionException(): void
    {
        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 3),
            logger: new NullLogger(),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->throwMissingExtensionIn = 'doGetMTime';

        $this->expectException(MissingExtensionException::class);
        $client->getMTime('/x');

        self::assertSame(1, $client->counters['doGetMTime']);
    }

    public function testWithRetryThrowsAfterMaxAttemptsExceeded(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');

        $client = $this->makeClient(
            options: new ConnectionOptions(
                retryMax: 2,
                retryDelayMs: 0,
                retryBackoff: 2.0,
                retryJitter: false,
                retryUnsafeOperations: true
            ),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->failures['doRename'] = 999;

        $this->expectException(TransferException::class);
        $client->rename('/a', '/b');

        self::assertSame(3, $client->counters['doRename']);
    }

    public function testDownloadFileCreatesLocalDirAndCallsDoDownloadFile(): void
    {
        $fs = $this->createMock(FilesystemFunctionsInterface::class);

        $fs->method('dirname')->with('/tmp/x/file.txt')->willReturn('/tmp/x');

        $fs->expects(self::exactly(2))
            ->method('isDir')
            ->with('/tmp/x')
            ->willReturnOnConsecutiveCalls(false, true);

        $fs->expects(self::once())
            ->method('mkdir')
            ->with('/tmp/x', 0775, true)
            ->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: $logger,
            fs: $fs,
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->downloadFile('/remote.txt', '/tmp/x/file.txt');

        self::assertSame($client, $out);
        self::assertSame([['/remote.txt', '/tmp/x/file.txt']], $client->calls['doDownloadFile']);
    }

    public function testDownloadFileThrowsWhenCannotCreateLocalDir(): void
    {
        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->willReturn('/tmp/x');

        $fs->expects(self::exactly(2))
            ->method('isDir')
            ->willReturnOnConsecutiveCalls(false, false);

        $fs->expects(self::once())->method('mkdir')->willReturn(false);

        $client = $this->makeClient(
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            fs: $fs,
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to create local directory');

        $client->downloadFile('/remote.txt', '/tmp/x/file.txt');
    }

    public function testDownloadFileDoesNotMkdirWhenDirAlreadyExists(): void
    {
        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->with('/tmp/x/file.txt')->willReturn('/tmp/x');

        $fs->expects(self::once())
            ->method('isDir')
            ->with('/tmp/x')
            ->willReturn(true);

        $fs->expects(self::never())->method('mkdir');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: new NullLogger(),
            fs: $fs,
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->downloadFile('/remote.txt', '/tmp/x/file.txt');

        self::assertSame(1, $client->counters['doDownloadFile']);
    }

    public function testIsDirectoryDelegatesToDoIsDirectory(): void
    {
        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: new NullLogger(),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->isDirectory('/x');

        self::assertFalse($out);
        self::assertSame([['/x']], $client->calls['doIsDirectory']);
    }

    public function testMakeDirectoryDelegatesToDoMakeDirectoryAndReturnsSelf(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->makeDirectory('/dir', true);

        self::assertSame($client, $out);
        self::assertSame([['/dir', true]], $client->calls['doMakeDirectory']);
    }

    public function testRemoveDirectoryDelegatesToDoRemoveDirectoryAndReturnsSelf(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->removeDirectory('/dir');

        self::assertSame($client, $out);
        self::assertSame([['/dir']], $client->calls['doRemoveDirectory']);
    }

    public function testRemoveDirectoryRecursiveRejectsUnsafeTargets(): void
    {
        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: new NullLogger(),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        foreach (['', ' ', '.', '..', '/'] as $bad) {
            try {
                $client->removeDirectoryRecursive($bad);
                self::fail('Expected TransferException.');
            } catch (TransferException $e) {
                self::assertStringContainsString('Refusing to remove directory recursively', $e->getMessage());
            }
        }
    }

    public function testRemoveDirectoryRecursiveDelegatesToDoRemoveDirectoryRecursiveAndReturnsSelf(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->removeDirectoryRecursive('/safe');

        self::assertSame($client, $out);
        self::assertSame([['/safe']], $client->calls['doRemoveDirectoryRecursive']);
    }

    public function testRenameDelegatesToDoRenameAndReturnsSelf(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->rename('/a', '/b');

        self::assertSame($client, $out);
        self::assertSame([['/a', '/b']], $client->calls['doRename']);
    }

    public function testGetSizeDelegatesToDoGetSize(): void
    {
        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: new NullLogger(),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->getSize('/x');

        self::assertNull($out);
        self::assertSame([['/x']], $client->calls['doGetSize']);
    }

    public function testGetMTimeDelegatesToDoGetMTime(): void
    {
        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: new NullLogger(),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->getMTime('/x');

        self::assertNull($out);
        self::assertSame([['/x']], $client->calls['doGetMTime']);
    }

    public function testChmodDelegatesToDoChmodAndReturnsSelf(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $out = $client->chmod('/x', 0644);

        self::assertSame($client, $out);
        self::assertSame([['/x', 0644]], $client->calls['doChmod']);
    }

    public function testWithRetryUsesDefaultBackoffWhenInvalid(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $client = $this->makeClient(
            options: new ConnectionOptions(
                retryMax: 1,
                retryDelayMs: 0,
                retryBackoff: 0.0,
                retryJitter: false,
                retryUnsafeOperations: false
            ),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->failures['doListFiles'] = 1;

        $files = $client->listFiles('/dir');

        self::assertSame(['a.txt', 'b.txt'], $files);
        self::assertSame(2, $client->counters['doListFiles']);
    }

    public function testWithRetryDoesNotRetryWhenRetryMaxZero(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $client = $this->makeClient(
            options: new ConnectionOptions(retryMax: 0),
            logger: $logger,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->setConnection('X');
        $client->setAuthenticated(true);

        $client->failures['doListFiles'] = 1;

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('fail list');

        $client->listFiles('/dir');

        self::assertSame(1, $client->counters['doListFiles']);
    }
}

final class TestClient extends AbstractClient
{
    /** @var array<string, list<array>> */
    public array $calls = [
        'doListFiles' => [],
        'doDownloadFile' => [],
        'doPutFile' => [],
        'doIsDirectory' => [],
        'doDeleteFile' => [],
        'doMakeDirectory' => [],
        'doRemoveDirectory' => [],
        'doRemoveDirectoryRecursive' => [],
        'doRename' => [],
        'doGetSize' => [],
        'doGetMTime' => [],
        'doChmod' => [],
    ];

    /** @var array<string, int> */
    public array $counters = [];

    /** @var array<string, int> "fail N times then succeed" */
    public array $failures = [];

    public ?string $throwNonClientExceptionIn = null;
    public ?string $throwMissingExtensionIn = null;

    public function setConnection(mixed $handle): void
    {
        $this->connection = $handle;
    }

    public function setAuthenticated(bool $v): void
    {
        $this->authenticated = $v;
    }

    public function connect(): static
    {
        $this->connection = \fopen('php://temp', 'r+');
        return $this;
    }

    public function loginWithPassword(?string $user = null, ?string $pass = null): static
    {
        $this->authenticated = true;
        return $this;
    }

    public function closeConnection(): void
    {
        $this->connection = null;
        $this->authenticated = false;
    }

    protected function doListFiles(string $remoteDir): array
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remoteDir];

        if (($this->failures[__FUNCTION__] ?? 0) > 0) {
            $this->failures[__FUNCTION__]--;
            throw new TransferException('fail list');
        }

        return ['a.txt', 'b.txt'];
    }

    protected function doDownloadFile(string $remoteFilename, string $localFilePath): void
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remoteFilename, $localFilePath];
    }

    protected function doPutFile(string $destinationFilename, string $sourceFilePath): void
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$destinationFilename, $sourceFilePath];
    }

    protected function doIsDirectory(string $remotePath): bool
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remotePath];
        return false;
    }

    protected function doDeleteFile(string $remotePath): void
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remotePath];

        if (($this->failures[__FUNCTION__] ?? 0) > 0) {
            $this->failures[__FUNCTION__]--;
            throw new TransferException('fail delete');
        }
    }

    protected function doMakeDirectory(string $remoteDir, bool $recursive): void
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remoteDir, $recursive];
    }

    protected function doRemoveDirectory(string $remoteDir): void
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remoteDir];
    }

    protected function doRemoveDirectoryRecursive(string $remoteDir): void
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remoteDir];
    }

    protected function doRename(string $from, string $to): void
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$from, $to];

        if (($this->failures[__FUNCTION__] ?? 0) > 0) {
            $this->failures[__FUNCTION__]--;
            throw new TransferException('fail rename');
        }
    }

    protected function doGetSize(string $remotePath): ?int
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remotePath];

        if ($this->throwNonClientExceptionIn === __FUNCTION__) {
            throw new \RuntimeException('boom');
        }

        return null;
    }

    protected function doGetMTime(string $remotePath): ?int
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remotePath];

        if ($this->throwMissingExtensionIn === __FUNCTION__) {
            throw new MissingExtensionException('no ext');
        }

        return null;
    }

    protected function doChmod(string $remotePath, int $mode): void
    {
        $this->bump(__FUNCTION__);
        $this->calls[__FUNCTION__][] = [$remotePath, $mode];
    }

    private function bump(string $fn): void
    {
        $this->counters[$fn] = ($this->counters[$fn] ?? 0) + 1;
    }
}
