<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Model;

use Cxxi\FtpClient\Enum\Protocol;
use Cxxi\FtpClient\Exception\InvalidFtpUrlException;

/**
 * Immutable value object representing a parsed FTP/FTPS/SFTP URL.
 *
 * This class encapsulates:
 * - The transport protocol ({@see Protocol})
 * - Host and optional port
 * - Optional user credentials
 * - A normalized remote base path
 *
 * Instances are typically created via {@see FtpUrl::parse()}.
 */
final readonly class FtpUrl
{
    /**
     * @param Protocol $protocol Transport protocol (FTP, FTPS, SFTP).
     * @param string $host Remote host.
     * @param int|null $port Remote port (null to use protocol default).
     * @param string|null $user Username (if provided in URL).
     * @param string|null $pass Password (if provided in URL).
     * @param string $path Normalized remote base path (always starts with "/").
     */
    public function __construct(
        public Protocol $protocol,
        public string $host,
        public ?int $port,
        public ?string $user,
        public ?string $pass,
        public string $path
    ) {
    }

    /**
     * Parse a connection URL into an {@see FtpUrl} instance.
     *
     * Supported schemes are typically:
     * - ftp://
     * - ftps://
     * - sftp://
     *
     * The returned path is normalized:
     * - Empty or missing path becomes "/"
     * - Always starts with a leading "/"
     *
     * Username and password (if present) are rawurldecoded.
     *
     * @param string $url Connection URL to parse.
     *
     * @return self
     *
     * @throws InvalidFtpUrlException If the URL cannot be parsed,
     *                                 or if required components (scheme, host) are missing.
     */
    public static function parse(string $url): self
    {
        if (\preg_match('#^(?<scheme>[a-z][a-z0-9+\-.]*):\/\/(?<rest>.*)$#i', $url, $m) === 1) {

            $rest = $m['rest'];

            if ($rest !== '' && ($rest[0] === '/' || $rest[0] === ':' || $rest[0] === '@')) {
                throw new InvalidFtpUrlException(sprintf(
                    'Invalid FTP URL: missing host. URL: "%s".',
                    $url
                ));
            }
        }

        $parts = \parse_url($url);

        if (!\is_array($parts)) {
            throw new InvalidFtpUrlException(sprintf(
                'Invalid FTP URL: unable to parse. URL: "%s".',
                $url
            ));
        }

        if (!isset($parts['scheme']) || $parts['scheme'] === '') {
            throw new InvalidFtpUrlException(sprintf(
                'Invalid FTP URL: missing scheme (expected "ftp", "ftps" or "sftp"). URL: "%s".',
                $url
            ));
        }

        if (!isset($parts['host']) || $parts['host'] === '') {
            throw new InvalidFtpUrlException(sprintf(
                'Invalid FTP URL: missing host. URL: "%s".',
                $url
            ));
        }

        $protocol = Protocol::fromScheme($parts['scheme']);

        $rawPath = $parts['path'] ?? '';
        $path = $rawPath === '' ? '/' : '/' . \ltrim($rawPath, '/');

        return new self(
            protocol: $protocol,
            host: $parts['host'],
            port: $parts['port'] ?? null,
            user: isset($parts['user']) ? \rawurldecode($parts['user']) : null,
            pass: isset($parts['pass']) ? \rawurldecode($parts['pass']) : null,
            path: $path
        );
    }
}
