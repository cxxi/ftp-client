<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Service\Sftp;

use Cxxi\FtpClient\Enum\HostKeyAlgo;
use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Exception\AuthenticationException;
use Cxxi\FtpClient\Exception\ConnectionException;
use Cxxi\FtpClient\Exception\MissingExtensionException;
use Cxxi\FtpClient\Exception\TransferException;
use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\Ssh2FunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\StreamFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Service\Sftp\SftpTransport;
use Cxxi\FtpClient\Util\WarningCatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(SftpTransport::class)]
final class SftpTransportTest extends TestCase
{
    private function makeUrl(
        ?string $user = 'u',
        ?string $pass = 'p',
        string $path = '/base',
        ?int $port = null
    ): FtpUrl {
        return new FtpUrl(Protocol::SFTP, 'example.com', $port, $user, $pass, $path);
    }

    private function makeClient(
        ?ConnectionOptions $options = null,
        ?ExtensionCheckerInterface $ext = null,
        ?Ssh2FunctionsInterface $ssh2 = null,
        ?StreamFunctionsInterface $streams = null,
        ?FilesystemFunctionsInterface $fs = null
    ): SftpTransport {
        $options ??= new ConnectionOptions();
        $ext ??= $this->createMock(ExtensionCheckerInterface::class);
        $ssh2 ??= $this->createMock(Ssh2FunctionsInterface::class);
        $streams ??= $this->createMock(StreamFunctionsInterface::class);
        $fs ??= $this->createMock(FilesystemFunctionsInterface::class);

        return new SftpTransport(
            url: $this->makeUrl(),
            options: $options,
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $fs,
            warnings: new WarningCatcher()
        );
    }

    /**
     * @param array<int, mixed> $args
     */
    private function callProtected(object $obj, string $method, array $args = []): mixed
    {
        $rm = new \ReflectionMethod($obj, $method);
        $rm->setAccessible(true);

        return $rm->invokeArgs($obj, $args);
    }

    public function testConnectThrowsWhenSsh2ExtensionMissing(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(false);

        $client = $this->makeClient(ext: $ext);

        $this->expectException(MissingExtensionException::class);
        $this->expectExceptionMessage('ext-ssh2 is required');

        $client->connect();
    }

    public function testConnectFailsWhenSsh2ConnectReturnsFalse(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->expects(self::once())
            ->method('connect')
            ->with('example.com', 22, ['hostkey' => HostKeyAlgo::SSH_RSA->value])
            ->willReturn(false);

        $ssh2->expects(self::never())->method('fingerprint');

        $client = $this->makeClient(
            options: new ConnectionOptions(hostKeyAlgo: null),
            ext: $ext,
            ssh2: $ssh2
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unable to connect to server');

        $client->connect();
    }

    public function testConnectUsesCustomHostKeyAlgoStringAndDefaultPortDoesNotValidateFingerprintWhenNotStrictAndNoExpected(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);

        $ssh2->expects(self::once())
            ->method('connect')
            ->with('example.com', 22, ['hostkey' => 'custom-algo'])
            ->willReturn('__conn__');

        $ssh2->expects(self::never())->method('fingerprint');

        $client = new SftpTransport(
            url: $this->makeUrl(port: null),
            options: new ConnectionOptions(hostKeyAlgo: 'custom-algo', strictHostKeyChecking: false, expectedFingerprint: null),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $out = $client->connect();
        self::assertSame($client, $out);
        self::assertTrue($client->isConnected());
    }

    public function testConnectStrictHostKeyCheckingWithoutExpectedFingerprintThrowsAndInvalidatesConnection(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = $this->makeClient(
            options: new ConnectionOptions(strictHostKeyChecking: true, expectedFingerprint: null),
            ext: $ext,
            ssh2: $ssh2
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Strict host key checking is enabled');

        try {
            $client->connect();
        } finally {
            self::assertFalse($client->isConnected(), 'connection should be invalidated');
            self::assertFalse($client->isAuthenticated());
        }
    }

    public function testConnectNormalizesExpectedFingerprintWithLowercasePrefix(): void
    {
        if (!\defined('SSH2_FINGERPRINT_MD5')) {
            \define('SSH2_FINGERPRINT_MD5', 1);
        }
        if (!\defined('SSH2_FINGERPRINT_SHA1')) {
            \define('SSH2_FINGERPRINT_SHA1', 2);
        }
        if (!\defined('SSH2_FINGERPRINT_HEX')) {
            \define('SSH2_FINGERPRINT_HEX', 4);
        }
        if (!\defined('SSH2_FINGERPRINT_RAW')) {
            \define('SSH2_FINGERPRINT_RAW', 8);
        }

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $expected = 'md5:11:11:11:11:11:11:11:11:11:11:11:11:11:11:11:11';

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('fingerprint')->with('__conn__', self::anything())->willReturn('11:11:11:11:11:11:11:11:11:11:11:11:11:11:11:11');

        $client = $this->makeClient(
            options: new ConnectionOptions(expectedFingerprint: $expected, strictHostKeyChecking: true),
            ext: $ext,
            ssh2: $ssh2
        );

        $client->connect();
        self::assertTrue($client->isConnected());
    }

    public function testConnectNormalizesExpectedFingerprintWithoutPrefix(): void
    {
        if (!\defined('SSH2_FINGERPRINT_MD5')) {
            \define('SSH2_FINGERPRINT_MD5', 1);
        }
        if (!\defined('SSH2_FINGERPRINT_SHA1')) {
            \define('SSH2_FINGERPRINT_SHA1', 2);
        }
        if (!\defined('SSH2_FINGERPRINT_HEX')) {
            \define('SSH2_FINGERPRINT_HEX', 4);
        }
        if (!\defined('SSH2_FINGERPRINT_RAW')) {
            \define('SSH2_FINGERPRINT_RAW', 8);
        }

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $expectedHex = \str_repeat('1', 32);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->method('fingerprint')->with('__conn__', self::anything())->willReturn('11:11:11:11:11:11:11:11:11:11:11:11:11:11:11:11');

        $client = $this->makeClient(
            options: new ConnectionOptions(expectedFingerprint: $expectedHex, strictHostKeyChecking: true),
            ext: $ext,
            ssh2: $ssh2
        );

        $client->connect();
        self::assertTrue($client->isConnected());
    }

    public function testConnectFingerprintMismatchThrowsAndInvalidatesConnection(): void
    {
        if (!\defined('SSH2_FINGERPRINT_MD5')) {
            \define('SSH2_FINGERPRINT_MD5', 1);
        }
        if (!\defined('SSH2_FINGERPRINT_SHA1')) {
            \define('SSH2_FINGERPRINT_SHA1', 2);
        }
        if (!\defined('SSH2_FINGERPRINT_HEX')) {
            \define('SSH2_FINGERPRINT_HEX', 4);
        }
        if (!\defined('SSH2_FINGERPRINT_RAW')) {
            \define('SSH2_FINGERPRINT_RAW', 8);
        }

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->method('fingerprint')->with('__conn__', self::anything())->willReturn('22:22:22:22:22:22:22:22:22:22:22:22:22:22:22:22');

        $client = $this->makeClient(
            options: new ConnectionOptions(strictHostKeyChecking: false, expectedFingerprint: 'MD5:' . \str_repeat('11', 16)),
            ext: $ext,
            ssh2: $ssh2
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('fingerprint mismatch');

        try {
            $client->connect();
        } finally {
            self::assertFalse($client->isConnected());
            self::assertFalse($client->isAuthenticated());
        }
    }

    public function testConnectThrowsWhenFingerprintCannotBeRetrieved(): void
    {
        if (!\defined('SSH2_FINGERPRINT_MD5')) {
            \define('SSH2_FINGERPRINT_MD5', 1);
        }
        if (!\defined('SSH2_FINGERPRINT_SHA1')) {
            \define('SSH2_FINGERPRINT_SHA1', 2);
        }
        if (!\defined('SSH2_FINGERPRINT_HEX')) {
            \define('SSH2_FINGERPRINT_HEX', 4);
        }
        if (!\defined('SSH2_FINGERPRINT_RAW')) {
            \define('SSH2_FINGERPRINT_RAW', 8);
        }

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->expects(self::once())
            ->method('fingerprint')
            ->with('__conn__', self::anything())
            ->willReturn(false);

        $client = $this->makeClient(
            options: new ConnectionOptions(
                strictHostKeyChecking: false,
                expectedFingerprint: 'MD5:11:11:11:11:11:11:11:11:11:11:11:11:11:11:11:11'
            ),
            ext: $ext,
            ssh2: $ssh2
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unable to retrieve server host key fingerprint');

        $client->connect();
    }

    public function testConnectRejectsSha256ExpectedFingerprint(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = $this->makeClient(
            options: new ConnectionOptions(strictHostKeyChecking: true, expectedFingerprint: 'sha256:ABC=='),
            ext: $ext,
            ssh2: $ssh2
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('SHA256 fingerprints are not supported');

        try {
            $client->connect();
        } finally {
            self::assertFalse($client->isConnected());
        }
    }

    public function testParseExpectedFingerprintThrowsOnInvalidBareHexLength(): void
    {
        $client = $this->makeClient();

        $rm = new \ReflectionMethod($client, 'parseExpectedFingerprint');
        $rm->setAccessible(true);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Invalid expected fingerprint format');

        $rm->invoke($client, 'AB:CD:EF');
    }

    public function testParseExpectedFingerprintThrowsOnInvalidMd5LengthWhenPrefixed(): void
    {
        $client = $this->makeClient();

        $rm = new \ReflectionMethod($client, 'parseExpectedFingerprint');
        $rm->setAccessible(true);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Invalid MD5 fingerprint length');

        $rm->invoke($client, 'MD5:' . \str_repeat('1', 30));
    }

    public function testNormalizeHexFingerprintStripsSeparatorsAndNonHexAndUppercases(): void
    {
        $client = $this->makeClient();

        $rm = new \ReflectionMethod($client, 'normalizeHexFingerprint');
        $rm->setAccessible(true);

        $out = $rm->invoke($client, " aa:bb \t cc\n dd-EE!! ");

        self::assertSame('AABBCCDDEE', $out);
    }

    public function testGetServerFingerprintHexThrowsWhenFingerprintNormalizesToEmpty(): void
    {
        if (!\defined('SSH2_FINGERPRINT_MD5')) {
            \define('SSH2_FINGERPRINT_MD5', 1);
        }
        if (!\defined('SSH2_FINGERPRINT_SHA1')) {
            \define('SSH2_FINGERPRINT_SHA1', 2);
        }
        if (!\defined('SSH2_FINGERPRINT_HEX')) {
            \define('SSH2_FINGERPRINT_HEX', 4);
        }
        if (!\defined('SSH2_FINGERPRINT_RAW')) {
            \define('SSH2_FINGERPRINT_RAW', 8);
        }

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->method('fingerprint')->willReturn('   ::: --- !!!   ');

        $client = $this->makeClient(
            options: new ConnectionOptions(strictHostKeyChecking: false, expectedFingerprint: 'MD5:' . \str_repeat('1', 32)),
            ext: $ext,
            ssh2: $ssh2
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('empty value');

        $client->connect();
    }

    public function testNormalizeHexFingerprintReturnsEmptyWhenPregReplaceReturnsNonString(): void
    {
        $client = $this->makeClient();

        $rm = new \ReflectionMethod($client, 'normalizeHexFingerprint');
        $rm->setAccessible(true);

        $out = $rm->invoke($client, "%%%%");

        self::assertSame('', $out);
    }

    public function testParseExpectedFingerprintInfersSha1FromLength(): void
    {
        $client = $this->makeClient();

        $rm = new \ReflectionMethod($client, 'parseExpectedFingerprint');
        $rm->setAccessible(true);

        $hex = \str_repeat('a', 40);

        $out = $rm->invoke($client, $hex);

        self::assertSame([
            'algo' => SftpTransport::FINGERPRINT_ALGO_SHA1,
            'fingerprint' => \strtoupper($hex),
        ], $out);
    }

    public function testGetServerFingerprintHexThrowsWhenAlgoUnknown(): void
    {
        $client = $this->makeClient();

        $rm = new \ReflectionMethod($client, 'getServerFingerprintHex');
        $rm->setAccessible(true);

        $this->expectException(MissingExtensionException::class);

        $rm->invoke($client, 'invalid_algo');
    }

    public function testLoginWithPasswordThrowsWhenNotConnected(): void
    {
        $client = $this->makeClient();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('connection not established');

        $client->loginWithPassword();
    }

    public function testLoginWithPasswordThrowsWhenCredentialsMissing(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = new SftpTransport(
            url: $this->makeUrl(user: null, pass: null),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $client->connect();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing username or password');

        $client->loginWithPassword(null, null);
    }

    public function testLoginWithPasswordThrowsWhenSsh2ExtensionMissingEvenWhenConnected(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturnOnConsecutiveCalls(true, false);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->expects(self::never())->method('authPassword');

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect();

        $this->expectException(MissingExtensionException::class);
        $this->expectExceptionMessage('ext-ssh2 is required');

        $client->loginWithPassword('u', 'p');
    }

    public function testLoginWithPasswordFailsWhenAuthPasswordReturnsFalse(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->expects(self::once())
            ->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(false);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Login failed');

        $client->loginWithPassword();
    }

    public function testLoginWithPasswordSuccessSetsAuthenticatedAndOverridesCredentials(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->expects(self::once())
            ->method('authPassword')
            ->with('__conn__', 'user2', 'pass2')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect();

        $out = $client->loginWithPassword('user2', 'pass2');
        self::assertSame($client, $out);
        self::assertTrue($client->isAuthenticated());
    }

    public function testLoginWithPubkeyThrowsWhenNotConnected(): void
    {
        $client = $this->makeClient();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('connection not established');

        $client->loginWithPubkey('/pub', '/priv');
    }

    public function testLoginWithPubkeyThrowsWhenKeyFilesDontExist(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, fs: $fs);
        $client->connect();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not exist');

        $client->loginWithPubkey('/pub', '/priv');
    }

    public function testLoginWithPubkeyThrowsWhenKeyFilesNotReadable(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, fs: $fs);
        $client->connect();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('not readable');

        $client->loginWithPubkey('/pub', '/priv');
    }

    public function testLoginWithPubkeyThrowsWhenUsernameMissing(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(user: null, pass: null),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $fs,
            warnings: new WarningCatcher()
        );

        $client->connect();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Username must be provided');

        $client->loginWithPubkey('/pub', '/priv', null);
    }

    public function testLoginWithPubkeyFailsWhenAuthReturnsFalse(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->expects(self::once())
            ->method('authPubkeyFile')
            ->with('__conn__', 'u', '/pub', '/priv')
            ->willReturn(false);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, fs: $fs);
        $client->connect();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Public key authentication failed');

        $client->loginWithPubkey('/pub', '/priv');
    }

    public function testLoginWithPubkeySuccessSetsAuthenticated(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('authPubkeyFile')->willReturn(true);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, fs: $fs);
        $client->connect();

        $client->loginWithPubkey('/pub', '/priv', 'userX');
        self::assertTrue($client->isAuthenticated());
    }

    public function testCloseConnectionIsIdempotent(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect();

        $client->closeConnection();
        self::assertFalse($client->isConnected());

        $client->closeConnection();
        self::assertFalse($client->isConnected());
    }

    public function testListFilesHappyPathFiltersDotsAndHidden(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->with('__conn__')->willReturn(77);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('opendir')->willReturn('__dir__');

        $streams->expects(self::exactly(6))
            ->method('readdir')
            ->willReturnOnConsecutiveCalls('.', '..', '.hidden', 'a.txt', 'b.txt', false);

        $streams->expects(self::once())->method('closedir')->with('__dir__');

        $client = $this->makeClient(
            ext: $ext,
            ssh2: $ssh2,
            streams: $streams
        );
        $client->connect()->loginWithPassword('u', 'p');

        $files = $client->listFiles('.');
        self::assertSame(['a.txt', 'b.txt'], $files);
    }

    public function testListFilesThrowsWhenSftpInitFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(false);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Failed to initialize SFTP subsystem');

        $client->listFiles('/x');
    }

    public function testListFilesThrowsWhenOpendirFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(88);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('opendir')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, streams: $streams);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to open remote directory');

        $client->listFiles('/dir');
    }

    public function testDownloadFileHappyPathWithTimeoutAndCloseBoth(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftp')->willReturn(99);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')
            ->willReturnMap([
                ['ssh2.sftp://99/base/remote.txt', 'r', '__remote__'],
                ['/tmp/local.txt', 'w', '__local__'],
            ]);

        $streams->expects(self::exactly(2))->method('streamSetTimeout');
        $streams->method('streamCopyToStream')->willReturn(10);

        $streams->expects(self::exactly(2))
            ->method('fclose')
            ->withAnyParameters();

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->with('/tmp/local.txt')->willReturn('/tmp');
        $fs->method('isDir')->with('/tmp')->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/base'),
            options: new ConnectionOptions(timeout: 5),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $fs,
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');
        $client->downloadFile('remote.txt', '/tmp/local.txt');
    }

    public function testDownloadFileThrowsWhenRemoteCannotBeOpened(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')->willReturn(false);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->with('/tmp/local.txt')->willReturn('/tmp');
        $fs->method('isDir')->with('/tmp')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, streams: $streams, fs: $fs);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to open remote file');

        $client->downloadFile('remote.txt', '/tmp/local.txt');
    }

    public function testDownloadFileThrowsWhenLocalCannotBeOpenedClosesRemote(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')
            ->willReturnMap([
                ['ssh2.sftp://99/base/remote.txt', 'r', '__remote__'],
                ['/tmp/local.txt', 'w', false],
            ]);

        $streams->expects(self::once())->method('fclose')->with('__remote__');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->with('/tmp/local.txt')->willReturn('/tmp');
        $fs->method('isDir')->with('/tmp')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, streams: $streams, fs: $fs);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to open local file');

        $client->downloadFile('remote.txt', '/tmp/local.txt');
    }

    public function testDownloadFileThrowsWhenCopyFailsStillClosesBoth(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')
            ->willReturnMap([
                ['ssh2.sftp://99/base/remote.txt', 'r', '__remote__'],
                ['/tmp/local.txt', 'w', '__local__'],
            ]);

        $streams->method('streamCopyToStream')->willReturn(false);

        $streams->expects(self::exactly(2))
            ->method('fclose')
            ->withAnyParameters();

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->with('/tmp/local.txt')->willReturn('/tmp');
        $fs->method('isDir')->with('/tmp')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, streams: $streams, fs: $fs);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Download');

        $client->downloadFile('remote.txt', '/tmp/local.txt');
    }

    public function testPutFileHappyPathWithTimeout(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')
            ->willReturnMap([
                ['/tmp/local.txt', 'r', '__local__'],
                ['ssh2.sftp://99/base/remote.txt', 'w', '__remote__'],
            ]);

        $streams->expects(self::exactly(2))->method('streamSetTimeout');
        $streams->method('streamCopyToStream')->willReturn(10);

        $streams->expects(self::exactly(2))
            ->method('fclose')
            ->withAnyParameters();

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->with('/tmp/local.txt')->willReturn(true);
        $fs->method('isReadable')->with('/tmp/local.txt')->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/base'),
            options: new ConnectionOptions(timeout: 5),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $fs,
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');
        $client->putFile('remote.txt', '/tmp/local.txt');
    }

    public function testPutFileThrowsWhenLocalCannotBeOpened(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')->with('/tmp/local.txt', 'r')->willReturn(false);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->with('/tmp/local.txt')->willReturn(true);
        $fs->method('isReadable')->with('/tmp/local.txt')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, streams: $streams, fs: $fs);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to open local file');

        $client->putFile('remote.txt', '/tmp/local.txt');
    }

    public function testPutFileThrowsWhenRemoteCannotBeOpenedClosesLocal(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')
            ->willReturnMap([
                ['/tmp/local.txt', 'r', '__local__'],
                ['ssh2.sftp://99/base/remote.txt', 'w', false],
            ]);

        $streams->expects(self::once())->method('fclose')->with('__local__');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->with('/tmp/local.txt')->willReturn(true);
        $fs->method('isReadable')->with('/tmp/local.txt')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, streams: $streams, fs: $fs);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to open remote file');

        $client->putFile('remote.txt', '/tmp/local.txt');
    }

    public function testPutFileThrowsWhenCopyFailsStillClosesBoth(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')
            ->willReturnMap([
                ['/tmp/local.txt', 'r', '__local__'],
                ['ssh2.sftp://99/base/remote.txt', 'w', '__remote__'],
            ]);

        $streams->method('streamCopyToStream')->willReturn(false);

        $streams->expects(self::exactly(2))
            ->method('fclose')
            ->withAnyParameters();

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->with('/tmp/local.txt')->willReturn(true);
        $fs->method('isReadable')->with('/tmp/local.txt')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, streams: $streams, fs: $fs);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Upload');

        $client->putFile('remote.txt', '/tmp/local.txt');
    }

    public function testIsDirectoryReturnsFalseWhenStatMissingMode(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);
        $ssh2->method('sftpStat')->willReturn(['size' => 1]);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        self::assertFalse($client->isDirectory('/x'));
    }

    public function testIsDirectoryTrueWhenModeIsDirectory(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftpStat')->willReturn(['mode' => 0040000]);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        self::assertTrue($client->isDirectory('dir'));
    }

    public function testDeleteFileThrowsWhenRequireSftpFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftp')->with('__conn__')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Failed to initialize SFTP subsystem');

        $client->deleteFile('/x');
    }

    public function testDeleteFileThrowsWhenUnlinkFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('sftpUnlink')->willReturn(false);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to delete');

        $client->deleteFile('file.txt');
    }

    public function testDeleteFileOkWithAbsolutePathUsesAbsoluteAndDoesNotJoinBase(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->expects(self::once())
            ->method('sftpUnlink')
            ->with(55, '/abs.txt')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $client->deleteFile('/abs.txt');
        self::assertTrue($client->isAuthenticated());
    }

    public function testMakeDirectoryReturnsEarlyOnEmptyDotOrRoot(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->expects(self::never())->method('sftpStat');
        $ssh2->expects(self::never())->method('sftpMkdir');

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $client->makeDirectory('/');
        $client->makeDirectory('');
        $client->makeDirectory('.');
        self::assertTrue($client->isAuthenticated());
    }

    public function testMakeDirectoryCreatesSegmentsWhenRecursiveTrue(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftpStat')->willReturnOnConsecutiveCalls(
            false,
            false,
            false
        );

        $expected = [
            [55, '/a', 0775, false],
            [55, '/a/b', 0775, false],
            [55, '/a/b/c', 0775, false],
        ];
        $i = 0;

        $ssh2->expects(self::exactly(3))
            ->method('sftpMkdir')
            ->with(
                self::callback(function ($sftp) use (&$i, $expected): bool {
                    self::assertSame($expected[$i][0], $sftp);
                    return true;
                }),
                self::callback(function ($path) use (&$i, $expected): bool {
                    self::assertSame($expected[$i][1], $path);
                    return true;
                }),
                self::callback(function ($mode) use (&$i, $expected): bool {
                    self::assertSame($expected[$i][2], $mode);
                    return true;
                }),
                self::callback(function ($recursive) use (&$i, $expected): bool {
                    self::assertSame($expected[$i][3], $recursive);
                    $i++;
                    return true;
                })
            )
            ->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/'),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');

        $client->makeDirectory('/a/b/c', recursive: true);

        self::assertSame(3, $i);
    }

    public function testMakeDirectorySkipsExistingDirAndCreatesNextWhenRecursiveFalse(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftpStat')
            ->willReturnOnConsecutiveCalls(
                ['mode' => 0040000],
                false,
                ['mode' => 0040000]
            );

        $ssh2->expects(self::once())
            ->method('sftpMkdir')
            ->with(55, '/a/b', 0775, false)
            ->willReturn(false);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/'),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');

        $client->makeDirectory('/a/b/c', recursive: false);
        self::assertTrue($client->isAuthenticated());
    }

    public function testMakeDirectoryThrowsWhenMkdirFailsAndStillNotDirAfterwards(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftpStat')
            ->willReturnOnConsecutiveCalls(
                false,
                false
            );

        $ssh2->method('sftpMkdir')->willReturn(false);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/'),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to create directory');

        $client->makeDirectory('/a', true);
    }

    public function testRemoveDirectoryThrowsWhenRmdirFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('sftpRmdir')->willReturn(false);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to remove directory');

        $client->removeDirectory('/dir');
    }

    public function testRemoveDirectoryOkNormalizesRelativePath(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->expects(self::once())
            ->method('sftpRmdir')
            ->with(55, '/base/dir')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $client->removeDirectory('dir');
    }

    public function testRenameThrowsWhenRenameFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('sftpRename')->willReturn(false);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/base'),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to rename');

        $client->rename('a.txt', 'b.txt');
    }

    public function testRenameOkNormalizesFromAndTo(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->expects(self::once())
            ->method('sftpRename')
            ->with(55, '/base/a.txt', '/abs/b.txt')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $client->rename('a.txt', '/abs/b.txt');
    }

    public function testGetSizeAndMTimeReturnNullWhenMissingKeys(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftpStat')->willReturn(['mode' => 0100000]);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        self::assertNull($client->getSize('/x'));
        self::assertNull($client->getMTime('/x'));
    }

    public function testGetSizeAndMTimeReturnInts(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftpStat')->willReturn(['size' => 123, 'mtime' => 456, 'mode' => 0100000]);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        self::assertSame(123, $client->getSize('/x'));
        self::assertSame(456, $client->getMTime('/x'));
    }

    public function testGetSizeAndMTimeReturnNullWhenStatIsNotArray(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('authPassword')->with('__conn__', 'u', 'p')->willReturn(true);

        $ssh2->method('sftpStat')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        self::assertNull($client->getSize('/x'));
        self::assertNull($client->getMTime('/x'));
    }

    public function testGetSizeCastsFloatAndGetMTimeCastsNumericString(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('authPassword')->with('__conn__', 'u', 'p')->willReturn(true);

        $ssh2->method('sftpStat')->willReturn([
            'size' => 123.9,
            'mtime' => " 456 ",
            'mode' => 0100000,
        ]);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        self::assertSame(123, $client->getSize('/x'));
        self::assertSame(456, $client->getMTime('/x'));
    }

    public function testGetSizeReturnsNullWhenStatValueIsNonNumericType(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('authPassword')->with('__conn__', 'u', 'p')->willReturn(true);

        $ssh2->method('sftpStat')->willReturn([
            'size' => ['nope'],
            'mode' => 0100000,
        ]);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        self::assertNull($client->getSize('/x'));
    }

    public function testChmodThrowsWhenChmodFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('sftpChmod')->willReturn(false);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('Unable to chmod');

        $client->chmod('file.txt', 0644);
    }

    public function testChmodOkNormalizesRelativePath(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->expects(self::once())
            ->method('sftpChmod')
            ->with(55, '/base/file.txt', 0644)
            ->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $client->chmod('file.txt', 0644);
    }

    public function testRemoveDirectoryRecursiveThrowsWhenTargetIsNotDirectory(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $ssh2->method('sftpStat')->willReturn(['mode' => 0100000]);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('is not a directory');

        $client->removeDirectoryRecursive('/notdir');
    }

    public function testRemoveDirectoryRecursiveDeletesFilesAndDirsThenRemovesRoot(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('authPassword')
            ->with('__conn__', 'u', 'p')
            ->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);

        $streams->method('opendir')->willReturnOnConsecutiveCalls('__h1__', '__h2__');
        $streams->method('readdir')->willReturnOnConsecutiveCalls(
            'file1.txt',
            'dir1',
            false,
            'file2.txt',
            false
        );
        $streams->expects(self::exactly(2))->method('closedir');

        $ssh2->method('sftpStat')->willReturnMap([
            [55, '/root', ['mode' => 0040000]],
            [55, '/root/file1.txt', ['mode' => 0100000]],
            [55, '/root/dir1', ['mode' => 0040000]],
            [55, '/root/dir1/file2.txt', ['mode' => 0100000]],
        ]);

        $ssh2->expects(self::exactly(2))->method('sftpUnlink')->willReturn(true);
        $ssh2->expects(self::exactly(2))->method('sftpRmdir')->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/'),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');

        $client->removeDirectoryRecursive('/root');
    }

    public function testDestructorSwallowsExceptionsFromCloseConnection(): void
    {
        $this->expectNotToPerformAssertions();

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(false);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $streams = $this->createMock(StreamFunctionsInterface::class);
        $fs = $this->createMock(FilesystemFunctionsInterface::class);

        $client = new SftpTransport(
            url: $this->makeUrl(),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $fs,
            warnings: new WarningCatcher()
        );

        unset($client);
    }

    public function testDestructorClosesConnectionWhenConnected(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect();

        self::assertTrue($client->isConnected());

        unset($client);
    }

    public function testDownloadFileWithoutTimeoutDoesNotCallStreamSetTimeout(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);
        $ssh2->method('authPassword')->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')
            ->willReturnMap([
                ['ssh2.sftp://99/base/remote.txt', 'r', '__remote__'],
                ['/tmp/local.txt', 'w', '__local__'],
            ]);
        $streams->method('streamCopyToStream')->willReturn(1);

        $streams->expects(self::never())->method('streamSetTimeout');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->willReturn('/tmp');
        $fs->method('isDir')->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/base'),
            options: new ConnectionOptions(timeout: 0),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $fs,
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');
        $client->downloadFile('remote.txt', '/tmp/local.txt');
    }

    public function testPutFileWithoutTimeoutDoesNotCallStreamSetTimeout(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(99);
        $ssh2->method('authPassword')->willReturn(true);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')
            ->willReturnMap([
                ['/tmp/local.txt', 'r', '__local__'],
                ['ssh2.sftp://99/base/remote.txt', 'w', '__remote__'],
            ]);
        $streams->method('streamCopyToStream')->willReturn(1);

        $streams->expects(self::never())->method('streamSetTimeout');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/base'),
            options: new ConnectionOptions(timeout: 0),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $fs,
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');
        $client->putFile('remote.txt', '/tmp/local.txt');
    }

    public function testIsDirectoryReturnsFalseWhenStatReturnsFalse(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('authPassword')->willReturn(true);
        $ssh2->method('sftpStat')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        self::assertFalse($client->isDirectory('/x'));
    }

    public function testMakeDirectoryReturnsEarlyOnDot(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('authPassword')->willReturn(true);

        $ssh2->expects(self::never())->method('sftpMkdir');

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $client->makeDirectory('.');
    }

    public function testNormalizeExpectedFingerprintReturnsNullWhenOnlyPrefix(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = $this->makeClient(
            options: new ConnectionOptions(expectedFingerprint: 'md5:   ', strictHostKeyChecking: false),
            ext: $ext,
            ssh2: $ssh2
        );

        $client->connect();
        self::assertTrue($client->isConnected());
    }

    public function testDestructorSwallowsExceptionsWhenCloseConnectionThrowsViaLogger(): void
    {
        $this->expectNotToPerformAssertions();

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $throwingLogger = new class () extends \Psr\Log\NullLogger {
            public function debug(string|\Stringable $message, array $context = []): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $client = new SftpTransport(
            url: $this->makeUrl(),
            options: new ConnectionOptions(),
            logger: $throwingLogger,
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $client->connect();
        $client->__destruct();
    }

    public function testDoDownloadFileIsCountedAsCoveredByDirectInvocation(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('authPassword')->willReturn(true);
        $ssh2->method('sftp')->willReturn(99);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')->willReturnMap([
            ['ssh2.sftp://99/base/remote.txt', 'r', '__remote__'],
            ['/tmp/local.txt', 'w', '__local__'],
        ]);
        $streams->method('streamCopyToStream')->willReturn(1);
        $streams->expects(self::exactly(2))->method('fclose');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->willReturn('/tmp');
        $fs->method('isDir')->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/base'),
            options: new ConnectionOptions(timeout: 0),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $fs,
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');

        $this->callProtected($client, 'doDownloadFile', ['remote.txt', '/tmp/local.txt']);
    }

    public function testDoPutFileIsCountedAsCoveredByDirectInvocation(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('authPassword')->willReturn(true);
        $ssh2->method('sftp')->willReturn(99);

        $streams = $this->createMock(StreamFunctionsInterface::class);
        $streams->method('fopen')->willReturnMap([
            ['/tmp/local.txt', 'r', '__local__'],
            ['ssh2.sftp://99/base/remote.txt', 'w', '__remote__'],
        ]);
        $streams->method('streamCopyToStream')->willReturn(1);
        $streams->expects(self::exactly(2))->method('fclose');

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/base'),
            options: new ConnectionOptions(timeout: 0),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $streams,
            fs: $fs,
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');

        $this->callProtected($client, 'doPutFile', ['remote.txt', '/tmp/local.txt']);
    }

    public function testDoIsDirectoryIsCountedAsCoveredByDirectInvocation(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('authPassword')->willReturn(true);
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('sftpStat')->willReturn(['mode' => 0040000]);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $out = $this->callProtected($client, 'doIsDirectory', ['dir']);
        self::assertTrue($out);
    }

    public function testDoMakeDirectoryIsCountedAsCoveredByDirectInvocation(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('authPassword')->willReturn(true);
        $ssh2->method('sftp')->willReturn(55);

        $ssh2->method('sftpStat')->willReturn(false);

        $ssh2->expects(self::once())
            ->method('sftpMkdir')
            ->with(55, '/a', 0775, false)
            ->willReturn(true);

        $client = new SftpTransport(
            url: $this->makeUrl(path: '/'),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $client->connect()->loginWithPassword('u', 'p');

        $this->callProtected($client, 'doMakeDirectory', ['/a', true]);
    }

    public function testParseExpectedFingerprintIsCountedAsCoveredByDirectInvocation(): void
    {
        $client = $this->makeClient();

        $rm = new \ReflectionMethod($client, 'parseExpectedFingerprint');
        $rm->setAccessible(true);

        self::assertSame(
            ['algo' => 'md5', 'fingerprint' => '11111111111111111111111111111111'],
            $rm->invoke($client, \str_repeat('11', 16))
        );

        self::assertSame(
            ['algo' => 'md5', 'fingerprint' => '11111111111111111111111111111111'],
            $rm->invoke($client, 'md5:' . \str_repeat('11', 16))
        );

        self::assertSame(
            ['algo' => 'sha1', 'fingerprint' => \str_repeat('A', 40)],
            $rm->invoke($client, 'sha1:' . \str_repeat('A', 40))
        );
    }

    public function testNormalizeExpectedFingerprintReturnsNullWhenTrimmedEmpty(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = $this->makeClient(
            options: new ConnectionOptions(expectedFingerprint: '   ', strictHostKeyChecking: false),
            ext: $ext,
            ssh2: $ssh2
        );

        $client->connect();
        self::assertTrue($client->isConnected());
    }

    public function testMakeDirectoryReturnsEarlyWhenPathBecomesEmptyAfterTrimmingSlashes(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('sftp')->willReturn(55);
        $ssh2->method('authPassword')->willReturn(true);

        $ssh2->expects(self::never())->method('sftpStat');
        $ssh2->expects(self::never())->method('sftpMkdir');

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $client->makeDirectory('////', recursive: true);
        self::assertTrue($client->isAuthenticated());
    }

    public function testIsDirectoryThrowsWhenSftpInitFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('authPassword')->willReturn(true);

        $ssh2->method('sftp')->willReturn(false);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Failed to initialize SFTP subsystem');

        $client->isDirectory('/x');
    }

    public function testPutFileThrowsWhenSftpInitFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('authPassword')->willReturn(true);

        $ssh2->method('sftp')->willReturn(false);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('isReadable')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, fs: $fs);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Failed to initialize SFTP subsystem');

        $client->putFile('remote.txt', '/tmp/local.txt');
    }

    public function testDownloadFileThrowsWhenSftpInitFails(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');
        $ssh2->method('authPassword')->willReturn(true);

        $ssh2->method('sftp')->willReturn(false);

        $fs = $this->createMock(FilesystemFunctionsInterface::class);
        $fs->method('dirname')->willReturn('/tmp');
        $fs->method('isDir')->willReturn(true);

        $client = $this->makeClient(ext: $ext, ssh2: $ssh2, fs: $fs);
        $client->connect()->loginWithPassword('u', 'p');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Failed to initialize SFTP subsystem');

        $client->downloadFile('remote.txt', '/tmp/local.txt');
    }

    #[RunInSeparateProcess]
    public function testGetServerFingerprintHexThrowsWhenFingerprintConstantsAreMissing(): void
    {
        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->with('ssh2')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = $this->makeClient(
            options: new ConnectionOptions(
                strictHostKeyChecking: false,
                expectedFingerprint: 'SHA1:' . \str_repeat('A', 40)
            ),
            ext: $ext,
            ssh2: $ssh2
        );

        $this->expectException(MissingExtensionException::class);
        $this->expectExceptionMessage('Fingerprint algorithm "sha1" is not supported');

        $client->connect();
    }

    #[RunInSeparateProcess]
    public function testGetServerFingerprintHexThrowsWhenHexConstantMissing(): void
    {
        if (!\defined('SSH2_FINGERPRINT_MD5')) {
            \define('SSH2_FINGERPRINT_MD5', 1);
        }

        if (!\defined('SSH2_FINGERPRINT_SHA1')) {
            \define('SSH2_FINGERPRINT_SHA1', 2);
        }

        $ext = $this->createMock(ExtensionCheckerInterface::class);
        $ext->method('loaded')->willReturn(true);

        $ssh2 = $this->createMock(Ssh2FunctionsInterface::class);
        $ssh2->method('connect')->willReturn('__conn__');

        $client = new SftpTransport(
            url: $this->makeUrl(),
            options: new ConnectionOptions(),
            logger: new NullLogger(),
            extensions: $ext,
            ssh2: $ssh2,
            streams: $this->createMock(StreamFunctionsInterface::class),
            fs: $this->createMock(FilesystemFunctionsInterface::class),
            warnings: new WarningCatcher()
        );

        $rm = new \ReflectionMethod($client, 'getServerFingerprintHex');
        $rm->setAccessible(true);

        $this->expectException(MissingExtensionException::class);
        $this->expectExceptionMessage('ext-ssh2 is required');

        $rm->invoke($client, SftpTransport::FINGERPRINT_ALGO_MD5);
    }
}
