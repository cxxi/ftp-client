<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Enum;

use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Exception\UnsupportedProtocolException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Protocol::class)]
final class ProtocolTest extends TestCase
{
    #[DataProvider('supportedSchemesProvider')]
    public function testFromSchemeSupportsAndNormalizes(string $scheme, Protocol $expected): void
    {
        self::assertSame($expected, Protocol::fromScheme($scheme));
    }

    /**
     * @return array<string, array{0:string, 1:Protocol}>
     */
    public static function supportedSchemesProvider(): array
    {
        return [
            'ftp exact' => ['ftp', Protocol::FTP],
            'ftps exact' => ['ftps', Protocol::FTPS],
            'sftp exact' => ['sftp', Protocol::SFTP],

            'whitespace trimmed' => ['  ftp  ', Protocol::FTP],
            'uppercased gets lowered' => ['FTPS', Protocol::FTPS],
        ];
    }

    public function testFromSchemeThrowsForUnsupported(): void
    {
        $this->expectException(UnsupportedProtocolException::class);
        $this->expectExceptionMessage('Unsupported protocol');

        Protocol::fromScheme('http');
    }
}
