<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Enum;

/**
 * Supported SSH host key algorithms for SFTP connections.
 *
 * These values are passed to ext-ssh2 when establishing a connection
 * to control which host key algorithm should be negotiated with the server.
 *
 * Example usage (via ConnectionOptions):
 * - HostKeyAlgo::SSH_RSA
 * - HostKeyAlgo::SSH_ED25519
 *
 * If not explicitly configured, a default algorithm may be used
 * by the transport implementation.
 */
enum HostKeyAlgo: string
{
    /**
     * RSA host key algorithm.
     */
    case SSH_RSA = 'ssh-rsa';

    /**
     * Ed25519 host key algorithm.
     */
    case SSH_ED25519 = 'ssh-ed25519';

    /**
     * ECDSA with NIST P-256 curve.
     */
    case ECDSA_SHA2_NISTP256 = 'ecdsa-sha2-nistp256';

    /**
     * ECDSA with NIST P-384 curve.
     */
    case ECDSA_SHA2_NISTP384 = 'ecdsa-sha2-nistp384';

    /**
     * ECDSA with NIST P-521 curve.
     */
    case ECDSA_SHA2_NISTP521 = 'ecdsa-sha2-nistp521';
}
