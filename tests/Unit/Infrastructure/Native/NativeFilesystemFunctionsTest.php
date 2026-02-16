<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Native\NativeFilesystemFunctions;
use Cxxi\FtpClient\Tests\Support\RecordingInvoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeFilesystemFunctions::class)]
final class NativeFilesystemFunctionsTest extends TestCase
{
    public function testJoinPathIsPureAndIgnoresEmptySegments(): void
    {
        $fs = new NativeFilesystemFunctions(new RecordingInvoker(['sys_get_temp_dir' => '/tmp']));

        self::assertSame('', $fs->joinPath());
        self::assertSame('a' . DIRECTORY_SEPARATOR . 'b', $fs->joinPath('a', '', 'b'));
        self::assertSame('a' . DIRECTORY_SEPARATOR . 'b', $fs->joinPath('a/', '/b'));
    }

    public function testFilesystemDelegatesToInvoker(): void
    {
        $invoker = new RecordingInvoker(
            returnsByFunction: [
                'sys_get_temp_dir' => '/tmp',
                'mkdir' => true,
                'is_dir' => true,
                'file_exists' => true,
                'is_file' => true,
                'is_readable' => true,
                'basename' => 'file.txt',
                'dirname' => '/tmp/dir',
                'unlink' => true,
                'rmdir' => true,
                'is_link' => false,
            ]
        );

        $fs = new NativeFilesystemFunctions($invoker);

        self::assertSame('/tmp', $fs->sysGetTempDir());

        self::assertTrue($fs->mkdir('/tmp/dir', 0775, true));
        self::assertTrue($fs->isDir('/tmp/dir'));

        self::assertTrue($fs->fileExists('/tmp/dir/file.txt'));
        self::assertTrue($fs->isFile('/tmp/dir/file.txt'));
        self::assertTrue($fs->isReadable('/tmp/dir/file.txt'));

        self::assertSame('file.txt', $fs->basename('/tmp/dir/file.txt'));
        self::assertSame('/tmp/dir', $fs->dirname('/tmp/dir/file.txt'));

        self::assertTrue($fs->unlink('/tmp/dir/file.txt'));
        self::assertTrue($fs->rmdir('/tmp/dir'));

        self::assertFalse($fs->isLink('/tmp/dir/file.txt'));

        self::assertNotEmpty($invoker->calls);
    }
}
