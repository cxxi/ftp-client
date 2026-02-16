<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpPathNormalizationTest extends SftpIntegrationTestCase
{
    public function test_put_and_stat_work_with_relative_and_absolute_paths(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $name = $this->uniq('paths') . '.txt';
        $local = \sys_get_temp_dir() . '/' . $name;
        \file_put_contents($local, "paths\n");

        $abs = '/upload/' . $name;

        try {
            $client->putFile($name, $local);
            self::assertNotNull($client->getSize($name));
            self::assertNotNull($client->getSize($abs));

            $renamed = $this->uniq('paths-renamed') . '.txt';
            $client->rename($abs, $renamed);
            self::assertNull($client->getSize($abs));
            self::assertNotNull($client->getSize($renamed));

            $files = $client->listFiles('.');
            self::assertContains($renamed, $files);

            $client->deleteFile($renamed);
        } finally {
            @\unlink($local);
            try {
                $client->deleteFile($name);
            } catch (\Throwable) {
            }
            try {
                $client->deleteFile($abs);
            } catch (\Throwable) {
            }
            $client->closeConnection();
        }
    }

    public function test_listFiles_dot_and_absolute_upload_are_consistent(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        try {
            $a = $client->listFiles('.');
            $b = $client->listFiles('/upload');

            self::assertContainsOnly('string', $a);
            self::assertContainsOnly('string', $b);

            sort($a);
            sort($b);
            self::assertSame($a, $b);
        } finally {
            $client->closeConnection();
        }
    }
}
