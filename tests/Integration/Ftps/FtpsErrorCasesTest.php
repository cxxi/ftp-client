<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftps')]
final class FtpsErrorCasesTest extends FtpsIntegrationTestCase
{
    public function test_delete_nonexistent_throws(): void
    {
        $this->requireFtps();
        $client = $this->client();

        $missing = $this->uniq('missing') . '.txt';

        $this->expectException(TransferException::class);
        $client->deleteFile($missing);
    }

    public function test_rename_nonexistent_throws(): void
    {
        $this->requireFtps();
        $client = $this->client();

        $missing = $this->uniq('missing') . '.txt';
        $to = $this->uniq('to') . '.txt';

        $this->expectException(TransferException::class);
        $client->rename($missing, $to);
    }

    public function test_download_nonexistent_throws(): void
    {
        $this->requireFtps();
        $client = $this->client();

        $missing = $this->uniq('missing') . '.txt';
        $dst = \sys_get_temp_dir() . '/dl-' . $missing;

        try {
            $this->expectException(TransferException::class);
            $client->downloadFile($missing, $dst);
        } finally {
            $client->closeConnection();
            @\unlink($dst);
        }
    }

    public function test_removeDirectory_nonempty_throws(): void
    {
        $this->requireFtps();
        $client = $this->client();

        $dir = $this->uniq('ftps-nonempty');
        $client->makeDirectory($dir, recursive: true);

        $local = \sys_get_temp_dir() . '/' . $this->uniq('payload') . '.txt';
        \file_put_contents($local, "data\n");

        $client->putFile($dir . '/file.txt', $local);

        try {
            $this->expectException(TransferException::class);
            $client->removeDirectory($dir);
        } finally {
            $client->removeDirectoryRecursive($dir);
            $client->closeConnection();
            @\unlink($local);
        }
    }
}
