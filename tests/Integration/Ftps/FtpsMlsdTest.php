<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use Cxxi\FtpClient\Contracts\FtpClientTransportInterface;
use Cxxi\FtpClient\Exception\TransferException;
use PHPUnit\Framework\Attributes\Group;

#[Group('ftps')]
final class FtpsMlsdTest extends FtpsIntegrationTestCase
{
    public function testRawListReturnsLines(): void
    {
        $this->requireFtps();

        $client = $this->client();
        self::assertInstanceOf(FtpClientTransportInterface::class, $client);

        $raw = $client->rawList('.', recursive: false);

        self::assertIsList($raw);
        self::assertContainsOnly('string', $raw);

        $client->closeConnection();
    }

    public function testMlsdReturnsStructuredEntriesOrIsSkippedIfNotSupported(): void
    {
        $this->requireFtps();

        $client = $this->client();
        self::assertInstanceOf(FtpClientTransportInterface::class, $client);

        try {
            $mlsd = $client->mlsd('.');
        } catch (TransferException) {
            self::markTestSkipped('MLSD not supported by server or ext-ftp runtime.');
        }

        self::assertIsList($mlsd);
        foreach ($mlsd as $entry) {
            self::assertNotEmpty($entry);
        }

        $client->closeConnection();
    }
}
