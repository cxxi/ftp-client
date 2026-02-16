<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Sftp;

use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('sftp')]
final class SftpErrorCasesTest extends SftpIntegrationTestCase
{
    public function test_delete_nonexistent_throws(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $missing = $this->uniq('missing') . '.txt';

        try {
            $this->expectException(TransferException::class);
            $client->deleteFile($missing);
        } finally {
            $client->closeConnection();
        }
    }

    public function test_rename_nonexistent_throws(): void
    {
        $this->requireSsh2();
        $client = $this->client();

        $from = $this->uniq('missing') . '.txt';
        $to = $this->uniq('dest') . '.txt';

        try {
            $this->expectException(TransferException::class);
            $client->rename($from, $to);
        } finally {
            $client->closeConnection();
        }
    }
}
