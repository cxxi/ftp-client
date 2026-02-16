<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\FtpClient;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpPasswordOverrideTest extends SftpIntegrationTestCase
{
    private function envString(string $key): string
    {
        $v = $_ENV[$key] ?? null;
        if (!\is_string($v) || $v === '') {
            self::markTestSkipped(sprintf('%s is not set (should be injected by bin/integration).', $key));
        }

        return $v;
    }

    private function envInt(string $key): int
    {
        $v = $_ENV[$key] ?? null;
        if (!\is_string($v) || $v === '') {
            self::markTestSkipped(sprintf('%s is not set (should be injected by bin/integration).', $key));
        }

        return (int) $v;
    }

    public function test_login_with_password_override_works_when_url_has_no_credentials(): void
    {
        $this->requireSsh2();

        $host = $this->envString('SFTP_HOST');
        $port = $this->envInt('SFTP_PORT');
        $user = $this->envString('SFTP_USER');
        $pass = $this->envString('SFTP_PASS');

        $url = sprintf('sftp://%s:%d/upload', $host, $port);

        $client = FtpClient::fromUrl($url);

        $client->connect()->loginWithPassword($user, $pass);

        self::assertTrue($client->isConnected());
        self::assertTrue($client->isAuthenticated());

        $client->closeConnection();
    }

    public function test_login_with_password_override_wins_over_wrong_url_credentials(): void
    {
        $this->requireSsh2();

        $host = $this->envString('SFTP_HOST');
        $port = $this->envInt('SFTP_PORT');
        $user = $this->envString('SFTP_USER');
        $pass = $this->envString('SFTP_PASS');

        $url = sprintf('sftp://wrong:wrong@%s:%d/upload', $host, $port);

        $client = FtpClient::fromUrl($url);

        $client->connect()->loginWithPassword($user, $pass);

        self::assertTrue($client->isConnected());
        self::assertTrue($client->isAuthenticated());

        $client->closeConnection();
    }
}
