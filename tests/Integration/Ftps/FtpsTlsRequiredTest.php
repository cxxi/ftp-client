<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use Cxxi\FtpClient\Exception\FtpClientException;
use Cxxi\FtpClient\FtpClient;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftps')]
final class FtpsTlsRequiredTest extends FtpsIntegrationTestCase
{
    public function test_plain_ftp_scheme_fails_against_tls_required_server(): void
    {
        $this->requireFtps();

        $url = \sprintf(
            'ftp://%s:%s@%s:%d/%s',
            $_ENV['FTPS_USER'],
            $_ENV['FTPS_PASS'],
            $_ENV['FTPS_HOST'],
            (int) $_ENV['FTPS_PORT'],
            'upload'
        );

        $client = FtpClient::fromUrl($url);

        $this->expectException(FtpClientException::class);
        $client->connect()->loginWithPassword();
    }
}
