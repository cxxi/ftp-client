<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Tests\Support\Env;
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
            Env::string('SFTP_USER', 'test'),
            Env::string('SFTP_PASS', 'test'),
            Env::string('SFTP_HOST', 'sftp'),
            Env::int('SFTP_PORT', 22),
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

    protected function requireEnvString(string $key): string
    {
        $v = Env::stringOrNull($key);
        if ($v === null) {
            self::markTestSkipped(sprintf('%s is not set (should be injected by bin/integration).', $key));
        }

        return $v;
    }

    protected function requireEnvInt(string $key): int
    {
        $v = Env::int($key, 0);
        if ($v <= 0) {
            self::markTestSkipped(sprintf('%s is not set (should be injected by bin/integration).', $key));
        }

        return $v;
    }

    protected function requireEnvBool(string $key): bool
    {
        $raw = Env::stringOrNull($key);
        if ($raw === null) {
            self::markTestSkipped(sprintf('%s is not set (should be injected by bin/integration).', $key));
        }

        return Env::bool($key, false);
    }
}
