<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Enum;

use Cxxi\FtpClient\Exception\UnsupportedProtocolException;

/**
 * Supported transport protocols.
 *
 * This enum represents the protocol extracted from a connection URL.
 * It is used by the transport factory to determine which concrete
 * client implementation should be instantiated.
 *
 * Supported schemes:
 * - ftp
 * - ftps
 * - sftp
 */
enum Protocol: string
{
    /**
     * FTP (File Transfer Protocol).
     */
    case FTP = 'ftp';

    /**
     * FTPS (FTP over SSL/TLS).
     */
    case FTPS = 'ftps';

    /**
     * SFTP (SSH File Transfer Protocol).
     */
    case SFTP = 'sftp';

    /**
     * Create a Protocol enum from a URL scheme.
     *
     * The scheme is normalized to lowercase before matching.
     *
     * @param string $scheme URL scheme (e.g. "ftp", "ftps", "sftp").
     *
     * @return self
     *
     * @throws UnsupportedProtocolException If the scheme is not supported.
     */
    public static function fromScheme(string $scheme): self
    {
        $scheme = strtolower(trim($scheme));

        return match ($scheme) {
            'ftp' => self::FTP,
            'ftps' => self::FTPS,
            'sftp' => self::SFTP,
            default => throw new UnsupportedProtocolException(
                sprintf('Unsupported protocol: "%s"', $scheme)
            ),
        };
    }
}
