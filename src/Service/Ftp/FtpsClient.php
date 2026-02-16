<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Service\Ftp;

use Cxxi\FtpClient\Contracts\FtpClientTransportInterface;
use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\FtpFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Util\WarningCatcher;
use Psr\Log\LoggerInterface;

/**
 * FTPS transport client implementation (FTP over SSL/TLS).
 *
 * This client uses PHP's ext-ftp through an {@see FtpFunctionsInterface} adapter
 * and establishes the connection using {@see FtpFunctionsInterface::sslConnect()}.
 *
 * Note: This class focuses on establishing the FTPS connection. TLS negotiation
 * details are handled by ext-ftp / the underlying OpenSSL stack and server settings.
 */
final class FtpsClient extends AbstractFtpClient implements FtpClientTransportInterface
{
    /**
     * @param FtpUrl $url Parsed FTP URL (protocol, host, port, credentials, path).
     * @param ConnectionOptions|null $options Transfer and retry options.
     * @param LoggerInterface|null $logger PSR-3 logger.
     * @param ExtensionCheckerInterface|null $extensions Extension checker (ext-ftp).
     * @param FtpFunctionsInterface|null $ftp FTP functions adapter.
     * @param FilesystemFunctionsInterface|null $fs Filesystem adapter.
     * @param WarningCatcher|null $warnings Warning catcher for native warnings.
     */
    public function __construct(
        FtpUrl $url,
        ?ConnectionOptions $options = null,
        ?LoggerInterface $logger = null,
        ?ExtensionCheckerInterface $extensions = null,
        ?FtpFunctionsInterface $ftp = null,
        ?FilesystemFunctionsInterface $fs = null,
        ?WarningCatcher $warnings = null
    ) {
        parent::__construct($url, $options, $logger, $extensions, $ftp, $fs, $warnings);
    }

    /**
     * Create an FTPS connection handle.
     *
     * @param int|null $timeout Connection timeout in seconds (null to use ext default).
     *
     * @return resource|false|null
     *
     * @phpstan-return resource|false|null
     */
    protected function doConnectFtp(?int $timeout)
    {
        $port = $this->port ?? 21;

        return $this->ftp->sslConnect($this->host, $port, $timeout);
    }
}
