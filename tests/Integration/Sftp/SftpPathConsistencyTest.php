<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\FtpClient;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpPathConsistencyTest extends SftpIntegrationTestCase
{
    public function test_relative_and_absolute_paths_target_the_same_remote_file(): void
    {
        $this->requireSsh2();

        $url = $this->sftpUrl('upload');
        $client = FtpClient::fromUrl($url);

        $remoteRel = $this->uniq('p') . '.txt';
        $remoteAbs = '/upload/' . $remoteRel;

        $localSrc = \sys_get_temp_dir() . '/src-' . $remoteRel;
        $localDst = \sys_get_temp_dir() . '/dst-' . $remoteRel;

        $payload = "payload\n" . \bin2hex(\random_bytes(32)) . "\n";
        \file_put_contents($localSrc, $payload);

        $client->connect()->loginWithPassword();

        try {
            $client->putFile($remoteRel, $localSrc);

            $files = $client->listFiles('.');
            self::assertIsList($files);
            self::assertContains($remoteRel, $files);

            $sizeRel = $client->getSize($remoteRel);
            $sizeAbs = $client->getSize($remoteAbs);

            self::assertNotNull($sizeRel);
            self::assertNotNull($sizeAbs);
            self::assertSame($sizeRel, $sizeAbs);

            $client->downloadFile($remoteAbs, $localDst);

            self::assertFileExists($localDst);
            self::assertSame(\hash_file('sha256', $localSrc), \hash_file('sha256', $localDst));
        } finally {
            try {
                $client->deleteFile($remoteRel);
            } catch (\Throwable) {
            }

            $client->closeConnection();

            @\unlink($localSrc);
            @\unlink($localDst);
        }
    }
}
