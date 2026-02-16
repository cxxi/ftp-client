<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpFileOpsTest extends SftpIntegrationTestCase
{
    public function testGetSizeGetMTimeRenameDelete(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $base = $this->uniq('fileops');
        $from = $base . '.txt';
        $to   = $base . '-renamed.txt';

        $local = \sys_get_temp_dir() . '/' . $from;
        $payload = "hello\n" . \bin2hex(\random_bytes(16)) . "\n";
        \file_put_contents($local, $payload);

        try {
            $client->putFile($from, $local);

            $size = $client->getSize($from);
            self::assertIsInt($size);
            self::assertSame(\strlen($payload), $size);

            $mtime = $client->getMTime($from);
            self::assertIsInt($mtime);
            self::assertGreaterThan(0, $mtime);

            $client->rename($from, $to);

            self::assertNull($client->getSize($from));

            self::assertSame(\strlen($payload), $client->getSize($to));

            $client->deleteFile($to);

            self::assertNull($client->getSize($to));
        } finally {
            @\unlink($local);
            try {
                $client->deleteFile($from);
            } catch (\Throwable) {
            }
            try {
                $client->deleteFile($to);
            } catch (\Throwable) {
            }
            $client->closeConnection();
        }
    }

    public function testDownloadNonexistentThrows(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $remote = $this->uniq('missing') . '.txt';
        $localDst = \sys_get_temp_dir() . '/' . $remote;

        try {
            $this->expectException(TransferException::class);
            $client->downloadFile($remote, $localDst);
        } finally {
            @\unlink($localDst);
            $client->closeConnection();
        }
    }
}
