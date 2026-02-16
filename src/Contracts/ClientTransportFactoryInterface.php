<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Contracts;

use Cxxi\FtpClient\Model\ConnectionOptions;
use Psr\Log\LoggerInterface;

/**
 * Factory contract for creating transport clients from a connection URL.
 *
 * Implementations are responsible for:
 * - Parsing the provided URL
 * - Determining the appropriate protocol (FTP, FTPS, SFTP, etc.)
 * - Instantiating and returning the corresponding {@see ClientTransportInterface}
 */
interface ClientTransportFactoryInterface
{
    /**
     * Create a transport client from a connection URL.
     *
     * @param string $url Connection URL (e.g. ftp://, ftps://, sftp://).
     * @param ConnectionOptions|null $options Optional connection and retry configuration.
     * @param LoggerInterface|null $logger Optional PSR-3 logger passed to the transport.
     *
     * @return ClientTransportInterface Concrete transport implementation
     *                                  matching the protocol in the URL.
     */
    public function create(
        string $url,
        ?ConnectionOptions $options = null,
        ?LoggerInterface $logger = null
    ): ClientTransportInterface;
}
