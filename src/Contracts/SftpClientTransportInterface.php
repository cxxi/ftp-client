<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Contracts;

/**
 * Extended transport interface for SFTP clients.
 *
 * This interface adds SFTP-specific authentication capabilities
 * on top of the generic {@see ClientTransportInterface}.
 */
interface SftpClientTransportInterface extends ClientTransportInterface
{
    /**
     * Authenticate using a public/private key pair.
     *
     * Implementations are expected to:
     * - Verify that the connection has been established
     * - Ensure the key files exist and are readable
     * - Attempt authentication via SSH public key mechanism
     *
     * @param string $pubkeyFile  Path to the public key file.
     * @param string $privkeyFile Path to the private key file.
     * @param string|null $user   Username override (if null, implementation may use URL username).
     *
     * @return static
     */
    public function loginWithPubkey(string $pubkeyFile, string $privkeyFile, ?string $user = null): static;
}
