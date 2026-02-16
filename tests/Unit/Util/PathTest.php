<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Util;

use Cxxi\FtpClient\Util\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Path::class)]
final class PathTest extends TestCase
{
    /**
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function joinRemoteProvider(): array
    {
        return [
            'base and child normal' => ['/var/www', 'file.txt', '/var/www/file.txt'],
            'base trailing slash + child normal' => ['/var/www/', 'file.txt', '/var/www/file.txt'],
            'base normal + child leading slash' => ['/var/www', '/file.txt', '/var/www/file.txt'],
            'base trailing slash + child leading slash' => ['/var/www/', '/file.txt', '/var/www/file.txt'],

            'empty base treated as root + child' => ['', 'dir', '/dir'],
            'empty base treated as root + child leading slash' => ['', '/dir', '/dir'],

            'child empty returns normalized base' => ['/base', '', '/base'],
            'child empty returns normalized base without trailing slash' => ['/base/', '', '/base'],

            'base is root slash + child' => ['/', 'dir', '/dir'],
            'base is root slash + child leading slash' => ['/', '/dir', '/dir'],

            'relative base + child' => ['base', 'dir', 'base/dir'],
            'relative base trailing slash + child' => ['base/', 'dir', 'base/dir'],

            'empty base and empty child returns empty string (current behavior)' => ['', '', ''],
        ];
    }

    #[DataProvider('joinRemoteProvider')]
    public function testJoinRemote(string $basePath, string $childPath, string $expected): void
    {
        self::assertSame($expected, Path::joinRemote($basePath, $childPath));
    }
}
