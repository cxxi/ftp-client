<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use PHPUnit\Framework\Attributes\Group;

#[Group('ftps')]
final class FtpsSmokeTest extends FtpsIntegrationTestCase
{
    public function test_connect_login_and_list(): void
    {
        $this->requireFtps();

        $client = $this->client();

        $files = $client->listFiles('.');
        self::assertIsList($files);
        self::assertContainsOnly('string', $files);

        $client->closeConnection();
    }
}
