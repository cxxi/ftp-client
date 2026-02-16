<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftp;

use Cxxi\FtpClient\Contracts\ClientTransportInterface;
use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Tests\Support\Env;
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
            Env::string('FTP_USER', 'test'),
            Env::string('FTP_PASS', 'test'),
            Env::string('FTP_HOST', 'ftp'),
            Env::int('FTP_PORT', 21),
            \ltrim($path, '/')
        );
    }

    protected function client(?ConnectionOptions $options = null): ClientTransportInterface
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
