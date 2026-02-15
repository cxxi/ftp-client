<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Service;

use Cxxi\FtpClient\Contracts\ClientTransportFactoryInterface;
use Cxxi\FtpClient\Contracts\ClientTransportInterface;
use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Infrastructure\Native\NativeExtensionChecker;
use Cxxi\FtpClient\Infrastructure\Native\NativeFilesystemFunctions;
use Cxxi\FtpClient\Infrastructure\Native\NativeFtpFunctions;
use Cxxi\FtpClient\Infrastructure\Native\NativeSsh2Functions;
use Cxxi\FtpClient\Infrastructure\Native\NativeStreamFunctions;
use Cxxi\FtpClient\Infrastructure\Port\ExtensionCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\FilesystemFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\FtpFunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\Ssh2FunctionsInterface;
use Cxxi\FtpClient\Infrastructure\Port\StreamFunctionsInterface;
use Cxxi\FtpClient\Model\ConnectionOptions;
use Cxxi\FtpClient\Model\FtpUrl;
use Cxxi\FtpClient\Service\Ftp\FtpClient;
use Cxxi\FtpClient\Service\Ftp\FtpsClient;
use Cxxi\FtpClient\Service\Sftp\SftpClient;
use Psr\Log\LoggerInterface;

/**
 * Factory responsible for creating transport clients from a URL.
 *
 * This factory parses the provided URL into an {@see FtpUrl} value object and then
 * instantiates the appropriate transport implementation based on {@see Protocol}:
 * - {@see Protocol::FTP}  -> {@see FtpClient}
 * - {@see Protocol::FTPS} -> {@see FtpsClient}
 * - {@see Protocol::SFTP} -> {@see SftpClient}
 *
 * Dependencies are injected via interfaces to allow testing and to decouple from:
 * - native extension availability checks
 * - ext-ftp functions
 * - ext-ssh2 functions
 * - stream wrapper functions
 * - filesystem functions
 */
final class ClientFactory implements ClientTransportFactoryInterface
{
    /**
     * Extension checker shared by created transports.
     */
    private readonly ExtensionCheckerInterface $extensions;

    /**
     * FTP function adapter shared by created FTP-family transports.
     */
    private readonly FtpFunctionsInterface $ftp;

    /**
     * SSH2 function adapter shared by created SFTP transports.
     */
    private readonly Ssh2FunctionsInterface $ssh2;

    /**
     * Stream function adapter shared by created SFTP transports (ssh2.sftp stream wrapper).
     */
    private readonly StreamFunctionsInterface $streams;

    /**
     * Filesystem adapter shared by created transports.
     */
    private readonly FilesystemFunctionsInterface $fs;

    /**
     * @param ExtensionCheckerInterface|null $extensions Extension checker (defaults to {@see NativeExtensionChecker}).
     * @param FtpFunctionsInterface|null $ftp FTP adapter (defaults to {@see NativeFtpFunctions}).
     * @param Ssh2FunctionsInterface|null $ssh2 SSH2 adapter (defaults to {@see NativeSsh2Functions}).
     * @param StreamFunctionsInterface|null $streams Stream adapter (defaults to {@see NativeStreamFunctions}).
     * @param FilesystemFunctionsInterface|null $fs Filesystem adapter (defaults to {@see NativeFilesystemFunctions}).
     */
    public function __construct(
        ?ExtensionCheckerInterface $extensions = null,
        ?FtpFunctionsInterface $ftp = null,
        ?Ssh2FunctionsInterface $ssh2 = null,
        ?StreamFunctionsInterface $streams = null,
        ?FilesystemFunctionsInterface $fs = null
    ) {
        $this->extensions = $extensions ?? new NativeExtensionChecker();
        $this->ftp = $ftp ?? new NativeFtpFunctions();
        $this->ssh2 = $ssh2 ?? new NativeSsh2Functions();
        $this->streams = $streams ?? new NativeStreamFunctions();
        $this->fs = $fs ?? new NativeFilesystemFunctions();
    }

    /**
     * Create a transport client from a connection URL.
     *
     * @param string $url The connection URL (e.g. ftp://, ftps://, sftp://).
     * @param ConnectionOptions|null $options Connection and retry options (defaults to a new instance).
     * @param LoggerInterface|null $logger PSR-3 logger passed to the created transport (optional).
     *
     * @return ClientTransportInterface The concrete transport for the parsed protocol.
     */
    public function create(
        string $url,
        ?ConnectionOptions $options = null,
        ?LoggerInterface $logger = null
    ): ClientTransportInterface {

        $ftpUrl = FtpUrl::parse($url);
        $options ??= new ConnectionOptions();

        $logger?->debug('Transport factory create()', [
            'protocol' => $ftpUrl->protocol->value,
            'host' => $ftpUrl->host,
            'port' => $ftpUrl->port,
            'path' => $ftpUrl->path,
        ]);

        return match ($ftpUrl->protocol) {
            Protocol::FTP => new FtpClient(
                url: $ftpUrl,
                options: $options,
                logger: $logger,
                extensions: $this->extensions,
                ftp: $this->ftp,
                fs: $this->fs
            ),
            Protocol::FTPS => new FtpsClient(
                url: $ftpUrl,
                options: $options,
                logger: $logger,
                extensions: $this->extensions,
                ftp: $this->ftp,
                fs: $this->fs
            ),
            Protocol::SFTP => new SftpClient(
                url: $ftpUrl,
                options: $options,
                logger: $logger,
                extensions: $this->extensions,
                ssh2: $this->ssh2,
                streams: $this->streams,
                fs: $this->fs
            ),
        };
    }
}
