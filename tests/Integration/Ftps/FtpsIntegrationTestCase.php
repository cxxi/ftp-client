<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Model\ConnectionOptions;
use PHPUnit\Framework\TestCase;

abstract class FtpsIntegrationTestCase extends TestCase
{
    protected function requireFtps(): void
    {
        if (!\extension_loaded('ftp')) {
            self::markTestSkipped('ext-ftp (PHP extension) is required to run FTPS integration tests.');
        }

        if (!\function_exists('ftp_ssl_connect')) {
            self::markTestSkipped('ftp_ssl_connect() is required to run FTPS integration tests (FTPS support missing).');
        }
    }

    protected function ftpsUrl(string $path = ''): string
    {
        return \sprintf(
            'ftps://%s:%s@%s:%d/%s',
            $_ENV['FTPS_USER'],
            $_ENV['FTPS_PASS'],
            $_ENV['FTPS_HOST'],
            (int) $_ENV['FTPS_PORT'],
            ltrim($path, '/')
        );
    }

    protected function client(?ConnectionOptions $options = null): \Cxxi\FtpClient\Contracts\ClientTransportInterface
    {
        $this->requireFtps();

        $client = FtpClient::fromUrl($this->ftpsUrl(''), options: $options);
        $client->connect()->loginWithPassword();

        return $client;
    }

    protected function uniq(string $prefix): string
    {
        return $prefix . '-' . \bin2hex(\random_bytes(8));
    }
}
