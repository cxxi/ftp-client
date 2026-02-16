<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit;

use Cxxi\FtpClient\Contracts\ClientTransportFactoryInterface;
use Cxxi\FtpClient\Contracts\ClientTransportInterface;
use Cxxi\FtpClient\FtpClient;
use Cxxi\FtpClient\Model\ConnectionOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(FtpClient::class)]
final class FtpClientTest extends TestCase
{
    protected function tearDown(): void
    {
        FtpClient::_setFactoryForTests(null);
    }

    public function testFromUrlDelegatesToInjectedFactory(): void
    {
        $url = 'sftp://user:pass@example.com:22/path';
        $options = new ConnectionOptions();
        $logger = new NullLogger();

        $transport = $this->createMock(ClientTransportInterface::class);

        $factory = new class ($transport) implements ClientTransportFactoryInterface {
            public function __construct(private ClientTransportInterface $transport)
            {
            }

            public ?string $lastUrl = null;
            public ?ConnectionOptions $lastOptions = null;
            public ?\Psr\Log\LoggerInterface $lastLogger = null;

            public function create(
                string $url,
                ?ConnectionOptions $options = null,
                ?\Psr\Log\LoggerInterface $logger = null
            ): ClientTransportInterface {
                $this->lastUrl = $url;
                $this->lastOptions = $options;
                $this->lastLogger = $logger;

                return $this->transport;
            }
        };

        FtpClient::_setFactoryForTests($factory);

        $result = FtpClient::fromUrl($url, $options, $logger);

        self::assertSame($transport, $result);
        self::assertSame($url, $factory->lastUrl);
        self::assertSame($options, $factory->lastOptions);
        self::assertSame($logger, $factory->lastLogger);
    }
}
