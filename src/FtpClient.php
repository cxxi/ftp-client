<?php

declare(strict_types=1);

namespace Cxxi\FtpClient;

use Cxxi\FtpClient\Contracts\ClientTransportInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Service\ClientFactory;
use Psr\Log\LoggerInterface;

/**
 * High-level entry point for creating FTP/FTPS/SFTP clients.
 *
 * This class provides a simple, developer-friendly façade over the internal
 * transport factory. It automatically resolves the appropriate transport
 * (FTP, FTPS or SFTP) based on the URL scheme.
 *
 * Example:
 *
 * <code>
 * use Cxxi\FtpClient\FtpClient;
 *
 * $client = FtpClient::fromUrl('sftp://user:pass@example.com:22/path');
 *
 * $client
 *     ->connect()
 *     ->loginWithPassword()
 *     ->listFiles('.');
 * </code>
 *
 * The returned instance implements {@see ClientTransportInterface}.
 */
final class FtpClient
{
    /**
     * Create a transport client from a connection URL.
     *
     * The transport type is automatically determined from the URL scheme:
     * - ftp://  → FTP
     * - ftps:// → FTPS
     * - sftp:// → SFTP
     *
     * @param string $url
     *      Connection URL including scheme, host, optional credentials and path.
     *
     * @param ConnectionOptions|null $options
     *      Optional connection configuration (timeouts, retry policy,
     *      passive mode, host key verification, etc.).
     *
     * @param LoggerInterface|null $logger
     *      Optional PSR-3 logger used for connection, authentication
     *      and transfer logging.
     *
     * @return ClientTransportInterface
     *      A protocol-specific transport client ready to be connected
     *      and authenticated.
     */
    public static function fromUrl(
        string $url,
        ?ConnectionOptions $options = null,
        ?LoggerInterface $logger = null
    ): ClientTransportInterface {
        return (new ClientFactory())->create($url, $options, $logger);
    }
}
