<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpEmptyDirectoryListTest extends SftpIntegrationTestCase
{
    public function testListFilesReturnsEmptyArrayOnEmptyDirectory(): void
    {
        $this->requireSsh2();

        $client = $this->client();

        $dir = $this->uniq('empty');

        try {
            $client->makeDirectory($dir, recursive: true);

            $files = $client->listFiles($dir);

            self::assertIsList($files);
            self::assertSame([], $files);
        } finally {
            try {
                $client->removeDirectory($dir);
            } catch (\Throwable) {
            }

            $client->closeConnection();
        }
    }
}
