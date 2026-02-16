<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Exception\NativeCallTypeMismatchException;
use Cxxi\FtpClient\Infrastructure\Port\FtpConnectionTypeCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;

final class TypedNativeInvoker
{
    private readonly FtpConnectionTypeCheckerInterface $ftpConnectionTypeChecker;

    public function __construct(
        private readonly NativeFunctionInvokerInterface $invoke,
        ?FtpConnectionTypeCheckerInterface $ftpConnectionTypeChecker = null,
    ) {
        $this->ftpConnectionTypeChecker = $ftpConnectionTypeChecker ?? new NativeFtpConnectionTypeChecker();
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     */
    public function mixed(string $function, array $args = []): mixed
    {
        return ($this->invoke)($function, $args);
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     */
    public function string(string $function, array $args = []): string
    {
        $result = $this->mixed($function, $args);

        if (\is_string($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'string', $result);
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     */
    public function nullableString(string $function, array $args = []): ?string
    {
        $result = $this->mixed($function, $args);

        if ($result === null || \is_string($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'string|null', $result);
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     *
     * @return string|false
     * @phpstan-return string|false
     */
    public function stringOrFalse(string $function, array $args = []): string|false
    {
        $result = $this->mixed($function, $args);

        if ($result === false || \is_string($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'string|false', $result);
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     */
    public function int(string $function, array $args = []): int
    {
        $result = $this->mixed($function, $args);

        if (\is_int($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'int', $result);
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     *
     * @return int|false
     * @phpstan-return int|false
     */
    public function intOrFalse(string $function, array $args = []): int|false
    {
        $result = $this->mixed($function, $args);

        if ($result === false || \is_int($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'int|false', $result);
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     */
    public function nullableInt(string $function, array $args = []): ?int
    {
        $result = $this->mixed($function, $args);

        if ($result === null || \is_int($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'int|null', $result);
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     */
    public function bool(string $function, array $args = []): bool
    {
        $result = $this->mixed($function, $args);

        if (\is_bool($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'bool', $result);
    }

    /**
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     *
     * @return array<mixed>|false
     * @phpstan-return array<mixed>|false
     */
    public function arrayOrFalse(string $function, array $args = []): array|false
    {
        $result = $this->mixed($function, $args);

        if ($result === false || \is_array($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'array|false', $result);
    }

    /**
     * Typique des fonctions ftp_* : resource|false
     *
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     *
     * @return resource|false
     * @phpstan-return resource|false
     */
    public function resourceOrFalse(string $function, array $args = [])
    {
        $result = $this->mixed($function, $args);

        if ($result === false || \is_resource($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'resource|false', $result);
    }

    /**
     * Typique ftp_* :
     * - PHP < 8.1 : resource|false
     * - PHP >= 8.1 : FTP\Connection|false
     *
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     *
     * @return resource|\FTP\Connection|false
     * @phpstan-return resource|\FTP\Connection|false
     */
    public function ftpConnectionOrFalse(string $function, array $args = []): mixed
    {
        $result = $this->mixed($function, $args);

        if ($result === false || \is_resource($result)) {
            return $result;
        }

        if ($this->ftpConnectionTypeChecker->isFtpConnection($result)) {
            /** @var \FTP\Connection $result */
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'resource|FTP\\Connection|false', $result);
    }

    /**
     * Typique ssh2_* (selon ton design) : resource|false|null
     *
     * @param non-empty-string $function
     * @param array<int, mixed> $args
     *
     * @return resource|false|null
     * @phpstan-return resource|false|null
     */
    public function resourceOrFalseOrNull(string $function, array $args = [])
    {
        $result = $this->mixed($function, $args);

        if ($result === null || $result === false || \is_resource($result)) {
            return $result;
        }

        throw $this->unexpectedReturnType($function, 'resource|false|null', $result);
    }

    /**
     * @param non-empty-string $function
     * @param non-empty-string $expected
     * @param mixed $actual
     */
    private function unexpectedReturnType(string $function, string $expected, mixed $actual): NativeCallTypeMismatchException
    {
        return new NativeCallTypeMismatchException(\sprintf(
            'Native function "%s" must return %s, got %s.',
            $function,
            $expected,
            \get_debug_type($actual),
        ));
    }
}
