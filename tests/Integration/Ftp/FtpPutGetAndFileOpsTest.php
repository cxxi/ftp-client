<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftp;

use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftp')]
final class FtpPutGetAndFileOpsTest extends FtpIntegrationTestCase
{
    public function testPutDownloadGetSizeGetMTimeRenameDelete(): void
    {
        $this->requireFtpExt();

        $client = $this->client();

        $remote = $this->uniq('ftp') . '.txt';
        $remote2 = $remote . '.renamed';

        $localSrc = \sys_get_temp_dir() . '/' . $remote;
        $localDst = \sys_get_temp_dir() . '/dl-' . $remote;

        $payload = "hello\n" . \bin2hex(\random_bytes(64)) . "\n";
        \file_put_contents($localSrc, $payload);

        $client->putFile($remote, $localSrc);
        $client->downloadFile($remote, $localDst);

        self::assertFileExists($localDst);
        self::assertSame(\hash_file('sha256', $localSrc), \hash_file('sha256', $localDst));

        $size = $client->getSize($remote);
        self::assertIsInt($size);
        self::assertSame(\strlen($payload), $size);

        $mtime = $client->getMTime($remote);
        self::assertIsInt($mtime);
        self::assertGreaterThan(0, $mtime);

        $client->rename($remote, $remote2);
        $files = $client->listFiles('.');
        self::assertContains($remote2, $files);

        $client->deleteFile($remote2);

        $this->expectException(TransferException::class);
        $client->deleteFile($remote2);
    }
}
