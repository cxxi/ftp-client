<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Model;

use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Exception\InvalidFtpUrlException;
use Cxxi\FtpClient\Exception\UnsupportedProtocolException;
use Cxxi\FtpClient\Model\FtpUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FtpUrl::class)]
final class FtpUrlTest extends TestCase
{
    public function testParseBuildsValueObjectAndNormalizesPathAndCredentials(): void
    {
        $url = 'ftp://user%40mail.tld:p%3Aass@ftp.example.com:2121//var/www';

        $ftpUrl = FtpUrl::parse($url);

        self::assertSame(Protocol::FTP, $ftpUrl->protocol);
        self::assertSame('ftp.example.com', $ftpUrl->host);
        self::assertSame(2121, $ftpUrl->port);

        self::assertSame('user@mail.tld', $ftpUrl->user);
        self::assertSame('p:ass', $ftpUrl->pass);

        self::assertSame('/var/www', $ftpUrl->path);
    }

    #[DataProvider('pathProvider')]
    public function testParseNormalizesPath(string $url, string $expectedPath): void
    {
        $ftpUrl = FtpUrl::parse($url);
        self::assertSame($expectedPath, $ftpUrl->path);
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function pathProvider(): array
    {
        return [
            'missing path becomes root' => ['ftp://example.com', '/'],
            'empty path becomes root' => ['ftp://example.com/', '/'],
            'single slash path stays root' => ['ftp://example.com/', '/'],

            'path without leading slash gets one' => ['ftp://example.com/dir', '/dir'],
            'path with multiple leading slashes is normalized' => ['ftp://example.com///dir', '/dir'],
            'nested path kept with single leading slash' => ['ftp://example.com//a/b/c', '/a/b/c'],
        ];
    }

    public function testParseWithNoCredentialsKeepsNulls(): void
    {
        $ftpUrl = FtpUrl::parse('sftp://example.com/path');

        self::assertSame(Protocol::SFTP, $ftpUrl->protocol);
        self::assertNull($ftpUrl->user);
        self::assertNull($ftpUrl->pass);
    }

    public function testParseThrowsWhenSchemeIsMissing(): void
    {
        $this->expectException(InvalidFtpUrlException::class);
        $this->expectExceptionMessage('missing scheme');

        FtpUrl::parse('//example.com/path');
    }

    public function testParseThrowsWhenHostIsMissing(): void
    {
        $this->expectException(InvalidFtpUrlException::class);
        $this->expectExceptionMessage('missing host');

        FtpUrl::parse('ftp:///path');
    }

    public function testParseThrowsWhenSchemeIsUnsupported(): void
    {
        $this->expectException(UnsupportedProtocolException::class);
        $this->expectExceptionMessage('Unsupported protocol');

        FtpUrl::parse('gopher://example.com/path');
    }

    public function testParseThrowsWhenUrlCannotBeParsed(): void
    {
        $this->expectException(InvalidFtpUrlException::class);
        $this->expectExceptionMessage('unable to parse');

        FtpUrl::parse('ftp://');
    }

    public function testParseThrowsWhenHostIsMissingButUrlDoesNotMatchDoubleSlashGuard(): void
    {
        $this->expectException(InvalidFtpUrlException::class);
        $this->expectExceptionMessage('missing host');

        FtpUrl::parse('ftp:/path');
    }
}
