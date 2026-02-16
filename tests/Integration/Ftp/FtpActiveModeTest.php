<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftp;

use Cxxi\FtpClient\Enum\PassiveMode;
use Cxxi\FtpClient\Model\ConnectionOptions;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftp')]
final class FtpActiveModeTest extends FtpIntegrationTestCase
{
    public function testPassiveFalseActiveModeAllowsBasicListingAndTransfer(): void
    {
        $client = $this->client(new ConnectionOptions(passive: PassiveMode::FALSE));

        $files = $client->listFiles('.');
        self::assertIsList($files);
        self::assertContainsOnly('string', $files);

        $remote = $this->uniq('ftp-active') . '.txt';
        $localSrc = \sys_get_temp_dir() . '/' . $remote;
        $localDst = \sys_get_temp_dir() . '/dl-' . $remote;

        $payload = "active\n" . \bin2hex(\random_bytes(32)) . "\n";
        \file_put_contents($localSrc, $payload);

        $client->putFile($remote, $localSrc);
        $client->downloadFile($remote, $localDst);

        self::assertFileExists($localDst);
        self::assertSame(\hash_file('sha256', $localSrc), \hash_file('sha256', $localDst));

        $client->deleteFile($remote);
        $client->closeConnection();

        @\unlink($localSrc);
        @\unlink($localDst);
    }
}
