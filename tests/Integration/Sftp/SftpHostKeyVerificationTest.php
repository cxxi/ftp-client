<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\Exception\ConnectionException;
use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Model\ConnectionOptions;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpHostKeyVerificationTest extends SftpIntegrationTestCase
{
    public function test_strict_host_key_checking_accepts_matching_fingerprint(): void
    {
        $this->requireSsh2();

        $fingerprint = $_ENV['SFTP_FINGERPRINT'] ?? null;
        if (!\is_string($fingerprint) || $fingerprint === '') {
            self::markTestSkipped('SFTP_FINGERPRINT is not set (should be injected by bin/integration).');
        }

        $options = new ConnectionOptions(
            hostKeyAlgo: $_ENV['SFTP_HOSTKEY_ALGO'] ?? 'ssh-ed25519',
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

    public function test_strict_host_key_checking_rejects_wrong_fingerprint(): void
    {
        $this->requireSsh2();

        $options = new ConnectionOptions(
            hostKeyAlgo: $_ENV['SFTP_HOSTKEY_ALGO'] ?? 'ssh-ed25519',
            expectedFingerprint: 'MD5:00:00:00:00:00:00:00:00:00:00:00:00:00:00:00:00',
            strictHostKeyChecking: true,
        );

        $client = FtpClient::fromUrl($this->sftpUrl('upload'), options: $options);

        $this->expectException(ConnectionException::class);

        $client->connect()->loginWithPassword();
    }
}
