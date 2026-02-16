<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\Contracts\SftpClientTransportInterface;
use Cxxi\FtpClient\FtpClient;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpPubkeyAuthTest extends SftpIntegrationTestCase
{
    public function test_login_with_pubkey_works(): void
    {
        $this->requireSsh2();

        $pub = '/state/keys/id_ed25519.pub';
        $priv = '/state/keys/id_ed25519';

        if (!\is_file($pub) || !\is_file($priv)) {
            self::markTestSkipped('Ephemeral pubkey files are missing in /state/keys (docker integration setup issue).');
        }

        $client = FtpClient::fromUrl($this->sftpUrl('upload'));

        try {
            $client = $client->connect();
            self::assertInstanceOf(SftpClientTransportInterface::class, $client);

            $client->loginWithPubkey($pub, $priv, user: $_ENV['SFTP_USER']);

            $files = $client->listFiles('.');
            self::assertContainsOnly('string', $files);
        } finally {
            $client->closeConnection();
        }
    }
}
