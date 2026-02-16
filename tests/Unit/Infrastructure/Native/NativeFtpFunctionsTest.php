<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Native\NativeFtpFunctions;
use Cxxi\FtpClient\Tests\Support\RecordingInvoker;
use Cxxi\FtpClient\Tests\Support\ReturnValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeFtpFunctions::class)]
final class NativeFtpFunctionsTest extends TestCase
{
    public function testNativeAdapterDelegatesToInvoker(): void
    {
        $invoker = new RecordingInvoker(
            returnsByFunction: [
                'ftp_connect' => '__ftp_conn__',
                'ftp_ssl_connect' => '__ftps_conn__',
                'ftp_login' => true,
                'ftp_close' => true,
                'ftp_nlist' => new ReturnValue(['a.txt', 'b.txt']),
                'ftp_get' => true,
                'ftp_put' => true,
                'ftp_pwd' => '/',
                'ftp_chdir' => true,
                'ftp_pasv' => true,
                'ftp_delete' => true,
                'ftp_mkdir' => '/newdir',
                'ftp_rmdir' => true,
                'ftp_rename' => true,
                'ftp_size' => 123,
                'ftp_mdtm' => 456,
                'ftp_chmod' => 0644,
                'ftp_rawlist' => new ReturnValue(['-rw-r--r-- 1 ...']),
                'ftp_mlsd' => new ReturnValue([['name' => 'a.txt']]),
            ],
            exists: [
                'ftp_mlsd' => true,
            ]
        );

        $ftp = new NativeFtpFunctions($invoker);

        self::assertSame('__ftp_conn__', $ftp->connect('h', 21, null));
        self::assertSame('__ftp_conn__', $ftp->connect('h', 21, 10));

        self::assertSame('__ftps_conn__', $ftp->sslConnect('h', 21, null));
        self::assertSame('__ftps_conn__', $ftp->sslConnect('h', 21, 10));

        self::assertTrue($ftp->login('__ftp_conn__', 'u', 'p'));
        self::assertTrue($ftp->close('__ftp_conn__'));

        self::assertSame(['a.txt', 'b.txt'], $ftp->nlist('__ftp_conn__', '/'));

        self::assertTrue($ftp->get('__ftp_conn__', '/local', '/remote', FTP_ASCII));
        self::assertTrue($ftp->get('__ftp_conn__', '/local', '/remote', 999));

        self::assertTrue($ftp->put('__ftp_conn__', '/remote', '/local', FTP_ASCII));
        self::assertTrue($ftp->put('__ftp_conn__', '/remote', '/local', 999));

        self::assertSame('/', $ftp->pwd('__ftp_conn__'));
        self::assertTrue($ftp->chdir('__ftp_conn__', '/x'));
        self::assertTrue($ftp->pasv('__ftp_conn__', true));
        self::assertTrue($ftp->delete('__ftp_conn__', '/x'));

        self::assertSame('/newdir', $ftp->mkdir('__ftp_conn__', '/newdir'));
        self::assertTrue($ftp->rmdir('__ftp_conn__', '/newdir'));
        self::assertTrue($ftp->rename('__ftp_conn__', '/a', '/b'));

        self::assertSame(123, $ftp->size('__ftp_conn__', '/f'));
        self::assertSame(456, $ftp->mdtm('__ftp_conn__', '/f'));
        self::assertSame(0644, $ftp->chmod('__ftp_conn__', 0644, '/f'));

        self::assertIsArray($ftp->rawlist('__ftp_conn__', '/', false));
        self::assertIsArray($ftp->mlsd('__ftp_conn__', '/'));

        self::assertNotEmpty($invoker->calls);
    }

    public function testMlsdReturnsFalseWhenFunctionMissing(): void
    {
        $invoker = new RecordingInvoker(
            returnsByFunction: [],
            exists: ['ftp_mlsd' => false]
        );

        $ftp = new NativeFtpFunctions($invoker);

        self::assertFalse($ftp->mlsd('__conn__', '/'));
    }
}
