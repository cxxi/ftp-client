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
        $ftpConn = \fopen('php://temp', 'r+');
        self::assertIsResource($ftpConn);

        $ftpsConn = \fopen('php://temp', 'r+');
        self::assertIsResource($ftpsConn);

        $invoker = new RecordingInvoker(
            returnsByFunction: [
                'ftp_connect' => $ftpConn,
                'ftp_ssl_connect' => $ftpsConn,
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

        self::assertSame($ftpConn, $ftp->connect('h', 21, null));
        self::assertSame($ftpConn, $ftp->connect('h', 21, 10));

        self::assertSame($ftpsConn, $ftp->sslConnect('h', 21, null));
        self::assertSame($ftpsConn, $ftp->sslConnect('h', 21, 10));

        self::assertTrue($ftp->login($ftpConn, 'u', 'p'));
        self::assertTrue($ftp->close($ftpConn));

        self::assertSame(['a.txt', 'b.txt'], $ftp->nlist($ftpConn, '/'));

        self::assertTrue($ftp->get($ftpConn, '/local', '/remote', FTP_ASCII));
        self::assertTrue($ftp->get($ftpConn, '/local', '/remote', 999));

        self::assertTrue($ftp->put($ftpConn, '/remote', '/local', FTP_ASCII));
        self::assertTrue($ftp->put($ftpConn, '/remote', '/local', 999));

        self::assertSame('/', $ftp->pwd($ftpConn));
        self::assertTrue($ftp->chdir($ftpConn, '/x'));
        self::assertTrue($ftp->pasv($ftpConn, true));
        self::assertTrue($ftp->delete($ftpConn, '/x'));

        self::assertSame('/newdir', $ftp->mkdir($ftpConn, '/newdir'));
        self::assertTrue($ftp->rmdir($ftpConn, '/newdir'));
        self::assertTrue($ftp->rename($ftpConn, '/a', '/b'));

        self::assertSame(123, $ftp->size($ftpConn, '/f'));
        self::assertSame(456, $ftp->mdtm($ftpConn, '/f'));
        self::assertSame(0644, $ftp->chmod($ftpConn, 0644, '/f'));

        self::assertIsArray($ftp->rawlist($ftpConn, '/', false));
        self::assertIsArray($ftp->mlsd($ftpConn, '/'));

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
