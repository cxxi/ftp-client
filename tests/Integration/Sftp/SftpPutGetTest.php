<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\FtpClient;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpPutGetTest extends SftpIntegrationTestCase
{
    public function testPutThenDownloadHasSameContent(): void
    {
        $this->requireSsh2();

        $client = FtpClient::fromUrl($this->sftpUrl('upload'));

        $remote = $this->uniq('integration') . '.txt';
        $localSrc = \sys_get_temp_dir() . '/' . $remote;
        $localDst = \sys_get_temp_dir() . '/dl-' . $remote;

        $payload = "hello\n" . \bin2hex(\random_bytes(64)) . "\n";
        \file_put_contents($localSrc, $payload);

        try {
            $client->connect()->loginWithPassword();

            $client->putFile($remote, $localSrc);
            $client->downloadFile($remote, $localDst);

            self::assertFileExists($localDst);
            self::assertSame(\hash_file('sha256', $localSrc), \hash_file('sha256', $localDst));

            $client->deleteFile($remote);
        } finally {
            $client->closeConnection();
            @\unlink($localSrc);
            @\unlink($localDst);
        }
    }
}
