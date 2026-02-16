<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Native\NativeSsh2Functions;
use Cxxi\FtpClient\Tests\Support\RecordingInvoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeSsh2Functions::class)]
final class NativeSsh2FunctionsTest extends TestCase
{
    public function testNativeAdapterDelegatesToInvoker(): void
    {
        $conn = \fopen('php://temp', 'r+');
        self::assertIsResource($conn);

        $sftp = \fopen('php://temp', 'r+');
        self::assertIsResource($sftp);

        $invoker = new RecordingInvoker(
            returnsByFunction: [
                'ssh2_connect' => $conn,
                'ssh2_auth_password' => true,
                'ssh2_auth_pubkey_file' => false,
                'ssh2_sftp' => $sftp,
                'ssh2_sftp_stat' => ['size' => 1],
                'ssh2_sftp_unlink' => true,
                'ssh2_sftp_mkdir' => true,
                'ssh2_sftp_rmdir' => false,
                'ssh2_sftp_rename' => true,
                'ssh2_sftp_chmod' => true,
                'ssh2_fingerprint' => 'FP',
            ]
        );

        $ssh2 = new NativeSsh2Functions($invoker);

        $outConn = $ssh2->connect('h', 22, ['hostkey' => 'ssh-rsa'], []);
        self::assertSame($conn, $outConn);

        self::assertTrue($ssh2->authPassword($outConn, 'u', 'p'));
        self::assertFalse($ssh2->authPubkeyFile($outConn, 'u', '/pub', '/priv'));

        $outSftp = $ssh2->sftp($outConn);
        self::assertSame($sftp, $outSftp);

        self::assertIsArray($ssh2->sftpStat($outSftp, '/x'));
        self::assertTrue($ssh2->sftpUnlink($outSftp, '/x'));
        self::assertTrue($ssh2->sftpMkdir($outSftp, '/d', 0775, false));
        self::assertFalse($ssh2->sftpRmdir($outSftp, '/d'));
        self::assertTrue($ssh2->sftpRename($outSftp, '/a', '/b'));
        self::assertTrue($ssh2->sftpChmod($outSftp, '/x', 0644));
        self::assertSame('FP', $ssh2->fingerprint($outConn, 2));

        self::assertNotEmpty($invoker->calls);
    }
}
