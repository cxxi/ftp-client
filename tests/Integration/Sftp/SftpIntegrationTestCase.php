<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Model\ConnectionOptions;
use PHPUnit\Framework\TestCase;

abstract class SftpIntegrationTestCase extends TestCase
{
    protected function requireSsh2(): void
    {
        if (!\extension_loaded('ssh2')) {
            self::markTestSkipped('ext-ssh2 (PHP extension) is required to run SFTP integration tests.');
        }
    }

    protected function sftpUrl(string $path = 'upload'): string
    {
        return \sprintf(
            'sftp://%s:%s@%s:%d/%s',
            $_ENV['SFTP_USER'],
            $_ENV['SFTP_PASS'],
            $_ENV['SFTP_HOST'],
            (int) $_ENV['SFTP_PORT'],
            ltrim($path, '/')
        );
    }

    protected function client(?ConnectionOptions $options = null): \Cxxi\FtpClient\Contracts\ClientTransportInterface
    {
        $client = FtpClient::fromUrl($this->sftpUrl('upload'), options: $options);
        $client->connect()->loginWithPassword();

        return $client;
    }

    protected function uniq(string $prefix): string
    {
        return $prefix . '-' . \bin2hex(\random_bytes(8));
    }
}
