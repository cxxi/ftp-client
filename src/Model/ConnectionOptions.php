<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Model;

use Cxxi\FtpClient\Enum\HostKeyAlgo;
use Cxxi\FtpClient\Enum\PassiveMode;

/**
 * Immutable value object holding connection and retry configuration.
 *
 * This class centralizes all tunable options for FTP, FTPS and SFTP transports:
 * - Network timeout
 * - Passive mode (FTP/FTPS)
 * - SFTP host key algorithm and fingerprint validation
 * - Retry strategy (max attempts, delay, backoff, jitter, unsafe operations)
 *
 * Instances can be created directly or via {@see ConnectionOptions::fromArray()}
 * to parse user-provided configuration arrays.
 */
final readonly class ConnectionOptions
{
    /**
     * @param int|null $timeout Connection/stream timeout in seconds (null to use default).
     * @param PassiveMode $passive Passive mode configuration for FTP/FTPS.
     * @param HostKeyAlgo|string|null $hostKeyAlgo SFTP host key algorithm (enum or raw string).
     * @param string|null $expectedFingerprint Expected SFTP server fingerprint.
     *            Supported with ext-ssh2: MD5 / SHA1 (e.g. "MD5:aa:bb:..." or "SHA1:...").
     *            Note: SHA256 fingerprints (OpenSSH default) are not supported via ext-ssh2.
     * @param bool $strictHostKeyChecking Whether strict host key checking is enabled (SFTP).
     * @param int $retryMax Maximum number of retry attempts (0 = no retries).
     * @param int $retryDelayMs Initial retry delay in milliseconds.
     * @param float $retryBackoff Backoff multiplier (exponential factor).
     * @param bool $retryJitter Whether to apply random jitter to retry delay.
     * @param bool $retryUnsafeOperations Whether unsafe (non-idempotent) operations may be retried.
     */
    public function __construct(
        public ?int $timeout = null,
        public PassiveMode $passive = PassiveMode::AUTO,
        public HostKeyAlgo|string|null $hostKeyAlgo = null,
        public ?string $expectedFingerprint = null,
        public bool $strictHostKeyChecking = false,
        public int $retryMax = 0,
        public int $retryDelayMs = 0,
        public float $retryBackoff = 2.0,
        public bool $retryJitter = false,
        public bool $retryUnsafeOperations = false,
    ) {
    }

    /**
     * Create a {@see ConnectionOptions} instance from an associative array.
     *
     * Supported keys:
     *
     * - timeout (int|string)
     * - passive (bool|int|string)
     * - ssh:
     *     - host_key_algo (HostKeyAlgo|string)
     *     - expected_fingerprint (string) (MD5/SHA1 recommended: "MD5:..", "SHA1:..")
     *     - strict_host_key_checking (bool|int|string)
     * - retry:
     *     - max (int|string)
     *     - delay_ms (int|string)
     *     - backoff (float|int|string)
     *     - jitter (bool|int|string)
     *     - unsafe_operations (bool|int|string)
     *
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            timeout: self::parseTimeout($options),
            passive: self::parsePassive($options),
            hostKeyAlgo: self::parseHostKeyAlgo($options),
            expectedFingerprint: self::parseExpectedFingerprint($options),
            strictHostKeyChecking: self::parseStrictHostKeyChecking($options),
            retryMax: self::parseRetryMax($options),
            retryDelayMs: self::parseRetryDelayMs($options),
            retryBackoff: self::parseRetryBackoff($options),
            retryJitter: self::parseRetryJitter($options),
            retryUnsafeOperations: self::parseRetryUnsafeOperations($options),
        );
    }

    /**
     * Normalize a potentially mixed-key array into an array with string keys.
     *
     * PHPStan cannot infer string keys from a runtime \is_array() check alone.
     * This helper ensures the returned array satisfies `array<string, mixed>`.
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    private static function normalizeStringKeyedArray(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $k => $v) {
            if (\is_string($k)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseTimeout(array $options): ?int
    {
        $timeout = null;

        if (\array_key_exists('timeout', $options)) {
            $raw = $options['timeout'];

            if (\is_int($raw)) {
                $timeout = $raw;
            } elseif (\is_string($raw) && $raw !== '' && \ctype_digit($raw)) {
                $timeout = (int) $raw;
            }
        }

        if ($timeout !== null && $timeout <= 0) {
            $timeout = null;
        }

        return $timeout;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parsePassive(array $options): PassiveMode
    {
        $passiveRaw = $options['passive'] ?? 'auto';

        return match (true) {
            $passiveRaw === true,
            $passiveRaw === 1,
            $passiveRaw === '1' => PassiveMode::TRUE,

            $passiveRaw === false,
            $passiveRaw === 0,
            $passiveRaw === '0' => PassiveMode::FALSE,

            \is_string($passiveRaw) => PassiveMode::tryFrom(\strtolower($passiveRaw)) ?? PassiveMode::AUTO,

            default => PassiveMode::AUTO,
        };
    }

    /**
     * Extract the "ssh" sub-array.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function getSshArray(array $options): array
    {
        return self::normalizeStringKeyedArray($options['ssh'] ?? null);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseHostKeyAlgo(array $options): HostKeyAlgo|string|null
    {
        $ssh = self::getSshArray($options);

        if (!\array_key_exists('host_key_algo', $ssh)) {
            return null;
        }

        $raw = $ssh['host_key_algo'];

        if ($raw instanceof HostKeyAlgo) {
            return $raw;
        }

        if (\is_string($raw)) {
            $value = \trim($raw);
            if ($value === '') {
                return null;
            }

            return HostKeyAlgo::tryFrom($value) ?? $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseExpectedFingerprint(array $options): ?string
    {
        $ssh = self::getSshArray($options);

        if (!\array_key_exists('expected_fingerprint', $ssh)) {
            return null;
        }

        $raw = $ssh['expected_fingerprint'];

        if (!\is_string($raw)) {
            return null;
        }

        $v = \trim($raw);
        return $v === '' ? null : $v;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseStrictHostKeyChecking(array $options): bool
    {
        $ssh = self::getSshArray($options);

        $raw = $ssh['strict_host_key_checking'] ?? false;

        if (\is_bool($raw)) {
            return $raw;
        }

        if ($raw === 1 || $raw === '1') {
            return true;
        }

        if ($raw === 0 || $raw === '0') {
            return false;
        }

        if (\is_string($raw)) {
            $v = \strtolower(\trim($raw));
            if (\in_array($v, ['true', 'yes', 'on'], true)) {
                return true;
            }
            if (\in_array($v, ['false', 'no', 'off'], true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Extract the "retry" sub-array.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function getRetryArray(array $options): array
    {
        return self::normalizeStringKeyedArray($options['retry'] ?? null);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseRetryMax(array $options): int
    {
        $retry = self::getRetryArray($options);

        $max = 0;

        if (\array_key_exists('max', $retry)) {
            $raw = $retry['max'];

            if (\is_int($raw)) {
                $max = $raw;
            } elseif (\is_string($raw) && $raw !== '' && \ctype_digit($raw)) {
                $max = (int) $raw;
            }
        }

        return \max(0, $max);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseRetryDelayMs(array $options): int
    {
        $retry = self::getRetryArray($options);

        $delay = 0;

        if (\array_key_exists('delay_ms', $retry)) {
            $raw = $retry['delay_ms'];

            if (\is_int($raw)) {
                $delay = $raw;
            } elseif (\is_string($raw) && $raw !== '' && \ctype_digit($raw)) {
                $delay = (int) $raw;
            }
        }

        return \max(0, $delay);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseRetryBackoff(array $options): float
    {
        $retry = self::getRetryArray($options);

        $backoff = 2.0;

        if (\array_key_exists('backoff', $retry)) {
            $raw = $retry['backoff'];

            if (\is_float($raw) || \is_int($raw)) {
                $backoff = (float) $raw;
            } elseif (\is_string($raw)) {
                $value = \trim($raw);
                if ($value !== '' && \is_numeric($value)) {
                    $backoff = (float) $value;
                }
            }
        }

        if ($backoff <= 0) {
            $backoff = 2.0;
        }

        return $backoff;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseRetryJitter(array $options): bool
    {
        $retry = self::getRetryArray($options);

        $jitter = false;

        if (\array_key_exists('jitter', $retry)) {
            $raw = $retry['jitter'];

            if (\is_bool($raw)) {
                $jitter = $raw;
            } elseif ($raw === 1 || $raw === '1') {
                $jitter = true;
            } elseif ($raw === 0 || $raw === '0') {
                $jitter = false;
            } elseif (\is_string($raw)) {
                $v = \strtolower(\trim($raw));
                if (\in_array($v, ['true', 'yes', 'on'], true)) {
                    $jitter = true;
                } elseif (\in_array($v, ['false', 'no', 'off'], true)) {
                    $jitter = false;
                }
            }
        }

        return $jitter;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function parseRetryUnsafeOperations(array $options): bool
    {
        $retry = self::getRetryArray($options);

        $unsafe = false;

        if (\array_key_exists('unsafe_operations', $retry)) {
            $raw = $retry['unsafe_operations'];

            if (\is_bool($raw)) {
                $unsafe = $raw;
            } elseif ($raw === 1 || $raw === '1') {
                $unsafe = true;
            } elseif ($raw === 0 || $raw === '0') {
                $unsafe = false;
            } elseif (\is_string($raw)) {
                $v = \strtolower(\trim($raw));
                if (\in_array($v, ['true', 'yes', 'on'], true)) {
                    $unsafe = true;
                } elseif (\in_array($v, ['false', 'no', 'off'], true)) {
                    $unsafe = false;
                }
            }
        }

        return $unsafe;
    }
}
