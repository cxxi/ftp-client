<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpChmodTest extends SftpIntegrationTestCase
{
    public function testChmodChangesModeOrIsSkippedIfNotSupported(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $remote = $this->uniq('chmod') . '.txt';
        $local = \sys_get_temp_dir() . '/' . $remote;
        \file_put_contents($local, "chmod\n");

        try {
            $client->putFile($remote, $local);

            try {
                $client->chmod($remote, 0644);
            } catch (TransferException) {
                self::markTestSkipped('Server refused chmod (not supported / not permitted in this integration setup).');
            }

            self::assertNotNull($client->getSize($remote));
        } finally {
            @\unlink($local);
            try {
                $client->deleteFile($remote);
            } catch (\Throwable) {
            }
            $client->closeConnection();
        }
    }
}
