<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\FtpClient;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpPasswordOverrideTest extends SftpIntegrationTestCase
{
    public function testLoginWithPasswordOverrideWorksWhenUrlHasNoCredentials(): void
    {
        $this->requireSsh2();

        $host = $this->requireEnvString('SFTP_HOST');
        $port = $this->requireEnvInt('SFTP_PORT');
        $user = $this->requireEnvString('SFTP_USER');
        $pass = $this->requireEnvString('SFTP_PASS');

        $url = \sprintf('sftp://%s:%d/upload', $host, $port);

        $client = FtpClient::fromUrl($url);

        $client->connect()->loginWithPassword($user, $pass);

        self::assertTrue($client->isConnected());
        self::assertTrue($client->isAuthenticated());

        $client->closeConnection();
    }

    public function testLoginWithPasswordOverrideWinsOverWrongUrlCredentials(): void
    {
        $this->requireSsh2();

        $host = $this->requireEnvString('SFTP_HOST');
        $port = $this->requireEnvInt('SFTP_PORT');
        $user = $this->requireEnvString('SFTP_USER');
        $pass = $this->requireEnvString('SFTP_PASS');

        $url = \sprintf('sftp://wrong:wrong@%s:%d/upload', $host, $port);

        $client = FtpClient::fromUrl($url);

        $client->connect()->loginWithPassword($user, $pass);

        self::assertTrue($client->isConnected());
        self::assertTrue($client->isAuthenticated());

        $client->closeConnection();
    }
}
