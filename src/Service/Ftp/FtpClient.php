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
 * FTP transport client implementation (plain FTP).
 *
 * This client uses PHP's ext-ftp through an {@see FtpFunctionsInterface} adapter.
 * It implements {@see FtpClientTransportInterface} and provides the concrete
 * connection routine using {@see FtpFunctionsInterface::connect()}.
 *
 * For FTPS (explicit/implicit) variants, use a dedicated implementation that
 * overrides {@see AbstractFtpClient::doConnectFtp()} accordingly.
 */
final class FtpClient extends AbstractFtpClient implements FtpClientTransportInterface
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
     * Create an FTP connection handle.
     *
     * @param int|null $timeout Connection timeout in seconds (null to use ext default).
     *
     * @return resource|\FTP\Connection|false
     * @phpstan-return resource|\FTP\Connection|false
     */
    protected function doConnectFtp(?int $timeout)
    {
        $port = $this->port ?? 21;

        return $this->ftp->connect($this->host, $port, $timeout);
    }
}
