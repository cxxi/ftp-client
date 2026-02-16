<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpDirectoryOpsTest extends SftpIntegrationTestCase
{
    public function test_make_isDirectory_removeDirectory(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $dir = $this->uniq('dir');

        try {
            self::assertFalse($client->isDirectory($dir));

            $client->makeDirectory($dir, recursive: false);
            self::assertTrue($client->isDirectory($dir));

            $client->removeDirectory($dir);
            self::assertFalse($client->isDirectory($dir));
        } finally {
            try {
                $client->removeDirectoryRecursive($dir);
            } catch (\Throwable) {
            }
            $client->closeConnection();
        }
    }

    public function test_removeDirectoryRecursive_deletes_tree(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $root = $this->uniq('tree');
        $sub  = $root . '/a/b';
        $f1   = $root . '/file1.txt';
        $f2   = $sub . '/file2.txt';

        $local1 = \sys_get_temp_dir() . '/' . $this->uniq('sftp') . '.txt';
        $local2 = \sys_get_temp_dir() . '/' . $this->uniq('sftp') . '.txt';

        \file_put_contents($local1, "hello\n");
        \file_put_contents($local2, "world\n");

        try {
            $client->makeDirectory($sub, recursive: true);

            $client->putFile($f1, $local1);
            $client->putFile($f2, $local2);

            self::assertTrue($client->isDirectory($root));

            $client->removeDirectoryRecursive($root);

            self::assertFalse($client->isDirectory($root));
        } finally {
            @\unlink($local1);
            @\unlink($local2);
            try {
                $client->removeDirectoryRecursive($root);
            } catch (\Throwable) {
            }
            $client->closeConnection();
        }
    }

    public function test_removeDirectory_fails_when_not_empty(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $dir = $this->uniq('not-empty');
        $file = $dir . '/x.txt';
        $local = \sys_get_temp_dir() . '/' . $this->uniq('sftp') . '.txt';
        \file_put_contents($local, "data\n");

        try {
            $client->makeDirectory($dir, recursive: false);
            $client->putFile($file, $local);

            $this->expectException(TransferException::class);
            $client->removeDirectory($dir);
        } finally {
            @\unlink($local);
            try {
                $client->removeDirectoryRecursive($dir);
            } catch (\Throwable) {
            }
            $client->closeConnection();
        }
    }
}
