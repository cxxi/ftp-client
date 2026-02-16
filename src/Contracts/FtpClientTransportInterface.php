<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Contracts;

/**
 * Extended transport interface for FTP/FTPS clients.
 *
 * This interface adds FTP-specific listing capabilities that are not
 * available in SFTP transports.
 *
 * It extends {@see ClientTransportInterface} with lower-level directory
 * listing methods commonly provided by ext-ftp.
 */
interface FtpClientTransportInterface extends ClientTransportInterface
{
    /**
     * Retrieve a raw directory listing using the FTP LIST command.
     *
     * The format of each entry depends on the remote server and is typically
     * similar to the output of the Unix `ls -l` command.
     *
     * @param string $remoteDir Remote directory path (default: current directory).
     * @param bool $recursive Whether to request a recursive listing (if supported).
     *
     * @return array<int, string> Raw listing lines returned by the server.
     */
    public function rawList(string $remoteDir = '.', bool $recursive = false): array;

    /**
     * Retrieve a structured directory listing using the MLSD command.
     *
     * MLSD provides machine-readable entries with standardized facts
     * (size, modify time, type, etc.), when supported by the server.
     *
     * @param string $remoteDir Remote directory path (default: current directory).
     *
     * @return array<int, array<string, mixed>> Structured entries (format depends on implementation).
     */
    public function mlsd(string $remoteDir = '.'): array;
}
