<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\Exception\ConnectionException;
use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Tests\Support\Env;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpHostKeyVerificationTest extends SftpIntegrationTestCase
{
    public function testStrictHostKeyCheckingAcceptsMatchingFingerprint(): void
    {
        $this->requireSsh2();

        $fingerprint = Env::stringOrNull('SFTP_FINGERPRINT');
        if ($fingerprint === null) {
            self::markTestSkipped('SFTP_FINGERPRINT is not set.');
        }

        $options = new ConnectionOptions(
            hostKeyAlgo: Env::string('SFTP_HOSTKEY_ALGO', 'ssh-ed25519'),
            expectedFingerprint: $fingerprint,
            strictHostKeyChecking: true,
        );

        $client = FtpClient::fromUrl($this->sftpUrl('upload'), options: $options);

        $client->connect()->loginWithPassword();

        $files = $client->listFiles('.');
        self::assertIsList($files);
        self::assertContainsOnly('string', $files);

        $client->closeConnection();
    }

    public function testStrictHostKeyCheckingRejectsWrongFingerprint(): void
    {
        $this->requireSsh2();

        $options = new ConnectionOptions(
            hostKeyAlgo: Env::string('SFTP_HOSTKEY_ALGO', 'ssh-ed25519'),
            expectedFingerprint: 'MD5:00:00:00:00:00:00:00:00:00:00:00:00:00:00:00:00',
            strictHostKeyChecking: true,
        );

        $client = FtpClient::fromUrl($this->sftpUrl('upload'), options: $options);

        $this->expectException(ConnectionException::class);

        $client->connect()->loginWithPassword();
    }
}
