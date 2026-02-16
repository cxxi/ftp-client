<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftp;

use PHPUnit\Framework\Attributes\Group;

#[Group('ftp')]
final class FtpSmokeTest extends FtpIntegrationTestCase
{
    public function testConnectLoginAndList(): void
    {
        $this->requireFtpExt();

        $client = $this->client();

        $files = $client->listFiles('.');
        self::assertIsList($files);
        self::assertContainsOnly('string', $files);

        $client->closeConnection();
    }
}
