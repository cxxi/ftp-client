<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpSmokeTest extends SftpIntegrationTestCase
{
    public function test_connect_and_list(): void
    {
        $this->requireSsh2();

        $client = $this->client();

        try {
            $files = $client->listFiles('.');
            self::assertContainsOnly('string', $files);
        } finally {
            $client->closeConnection();
        }
    }
}
