<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftps')]
final class FtpsDirectoryOpsTest extends FtpsIntegrationTestCase
{
    public function test_make_isDirectory_removeDirectory(): void
    {
        $this->requireFtps();
        $client = $this->client();

        $dir = $this->uniq('ftps-dir');
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

    public function test_removeDirectoryRecursive_deletes_tree(): void
    {
        $this->requireFtps();
        $client = $this->client();

        $root = $this->uniq('ftps-tree');
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
