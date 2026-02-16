<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use Cxxi\FtpClient\Enum\PassiveMode;
use Cxxi\FtpClient\Model\ConnectionOptions;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftps')]
final class FtpsPassiveModeTest extends FtpsIntegrationTestCase
{
    public function test_passive_true_allows_data_operations(): void
    {
        $client = $this->client(new ConnectionOptions(passive: PassiveMode::TRUE));

        $remote = $this->uniq('ftps-passive') . '.txt';
        $localSrc = \sys_get_temp_dir() . '/' . $remote;
        $localDst = \sys_get_temp_dir() . '/dl-' . $remote;

        $payload = "hello\n" . \bin2hex(\random_bytes(64)) . "\n";
        \file_put_contents($localSrc, $payload);

        $client->putFile($remote, $localSrc);

        $files = $client->listFiles('.');
        self::assertContains($remote, $files);

        $client->downloadFile($remote, $localDst);
        self::assertFileExists($localDst);
        self::assertSame(\hash_file('sha256', $localSrc), \hash_file('sha256', $localDst));

        $client->deleteFile($remote);
        $client->closeConnection();

        @\unlink($localSrc);
        @\unlink($localDst);
    }
}
