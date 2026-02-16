<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftp;

use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftp')]
final class FtpDirectoryOpsTest extends FtpIntegrationTestCase
{
    public function testMakeIsDirectoryRemoveDirectory(): void
    {
        $this->requireFtpExt();
        $client = $this->client();

        $dir = $this->uniq('ftp-dir');
        $nested = $dir . '/a/b';

        $client->makeDirectory($nested, recursive: true);

        self::assertTrue($client->isDirectory($dir));
        self::assertTrue($client->isDirectory($nested));

        $file = $nested . '/' . $this->uniq('x') . '.txt';
        $local = \sys_get_temp_dir() . '/' . \basename($file);
        \file_put_contents($local, "hello\n");

        $client->putFile($file, $local);

        try {
            $client->removeDirectory($dir);
            self::fail('Expected removeDirectory() to fail for non-empty directory.');
        } catch (TransferException $e) {
            self::assertInstanceOf(TransferException::class, $e);
        }

        $client->removeDirectoryRecursive($dir);

        self::assertFalse($client->isDirectory($dir));

        $client->closeConnection();
        @\unlink($local);
    }

    public function testRemoveDirectoryRecursiveDeletesTree(): void
    {
        $this->requireFtpExt();
        $client = $this->client();

        $root = $this->uniq('ftp-tree');
        $client->makeDirectory($root . '/d1/d2', recursive: true);

        $local1 = \sys_get_temp_dir() . '/' . $this->uniq('f1') . '.txt';
        $local2 = \sys_get_temp_dir() . '/' . $this->uniq('f2') . '.txt';
        \file_put_contents($local1, "one\n");
        \file_put_contents($local2, "two\n");

        $client->putFile($root . '/a.txt', $local1);
        $client->putFile($root . '/d1/d2/b.txt', $local2);

        self::assertTrue($client->isDirectory($root));
        self::assertTrue($client->isDirectory($root . '/d1'));

        $client->removeDirectoryRecursive($root);

        self::assertFalse($client->isDirectory($root));

        $client->closeConnection();
        @\unlink($local1);
        @\unlink($local2);
    }
}
