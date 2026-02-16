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
        $invoker = new RecordingInvoker(
            returnsByFunction: [
                'ssh2_connect' => '__conn__',
                'ssh2_auth_password' => true,
                'ssh2_auth_pubkey_file' => false,
                'ssh2_sftp' => 123,
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

        $conn = $ssh2->connect('h', 22, ['hostkey' => 'ssh-rsa'], []);
        self::assertSame('__conn__', $conn);

        self::assertTrue($ssh2->authPassword($conn, 'u', 'p'));
        self::assertFalse($ssh2->authPubkeyFile($conn, 'u', '/pub', '/priv'));

        $sftp = $ssh2->sftp($conn);
        self::assertSame(123, $sftp);

        self::assertIsArray($ssh2->sftpStat($sftp, '/x'));
        self::assertTrue($ssh2->sftpUnlink($sftp, '/x'));
        self::assertTrue($ssh2->sftpMkdir($sftp, '/d', 0775, false));
        self::assertFalse($ssh2->sftpRmdir($sftp, '/d'));
        self::assertTrue($ssh2->sftpRename($sftp, '/a', '/b'));
        self::assertTrue($ssh2->sftpChmod($sftp, '/x', 0644));
        self::assertSame('FP', $ssh2->fingerprint($conn, 2));

        self::assertNotEmpty($invoker->calls);
    }
}
