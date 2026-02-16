<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftp;

use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Model\ConnectionOptions;
use PHPUnit\Framework\TestCase;

abstract class FtpIntegrationTestCase extends TestCase
{
    protected function requireFtpExt(): void
    {
        if (!\extension_loaded('ftp')) {
            self::markTestSkipped('ext-ftp (PHP extension) is required to run FTP integration tests.');
        }
    }

    protected function ftpUrl(string $path = ''): string
    {
        return \sprintf(
            'ftp://%s:%s@%s:%d/%s',
            $_ENV['FTP_USER'] ?? 'test',
            $_ENV['FTP_PASS'] ?? 'test',
            $_ENV['FTP_HOST'] ?? 'ftp',
            (int) ($_ENV['FTP_PORT'] ?? 21),
            ltrim($path, '/')
        );
    }

    protected function client(?ConnectionOptions $options = null): \Cxxi\FtpClient\Contracts\ClientTransportInterface
    {
        $client = FtpClient::fromUrl($this->ftpUrl(''), options: $options);
        $client->connect()->loginWithPassword();

        return $client;
    }

    protected function uniq(string $prefix): string
    {
        return $prefix . '-' . \bin2hex(\random_bytes(8));
    }
}
