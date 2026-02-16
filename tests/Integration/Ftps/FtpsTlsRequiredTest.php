<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use Cxxi\FtpClient\Exception\FtpClientException;
use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Tests\Support\Env;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftps')]
final class FtpsTlsRequiredTest extends FtpsIntegrationTestCase
{
    public function testPlainFtpSchemeFailsAgainstTlsRequiredServer(): void
    {
        $this->requireFtps();

        $url = \sprintf(
            'ftp://%s:%s@%s:%d/%s',
            Env::string('FTP_USER', 'test'),
            Env::string('FTP_PASS', 'test'),
            Env::string('FTP_HOST', 'ftp'),
            Env::int('FTP_PORT', 21),
            'upload'
        );

        $client = FtpClient::fromUrl($url);

        $this->expectException(FtpClientException::class);
        $client->connect()->loginWithPassword();
    }
}
