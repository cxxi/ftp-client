<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Support;

final class Env
{
    public static function string(string $key, string $default): string
    {
        $v = $_ENV[$key] ?? null;

        if (\is_string($v)) {
            return $v;
        }

        if (\is_int($v) || \is_float($v) || \is_bool($v)) {
            return (string) $v;
        }

        return $default;
    }

    public static function stringOrNull(string $key): ?string
    {
        $value = $_ENV[$key] ?? null;

        if (!\is_string($value)) {
            return null;
        }

        $trimmed = \trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function int(string $key, int $default): int
    {
        $v = $_ENV[$key] ?? null;

        if (\is_int($v)) {
            return $v;
        }

        if (\is_string($v)) {
            $vv = \trim($v);
            if ($vv !== '' && \ctype_digit($vv)) {
                return (int) $vv;
            }
        }

        return $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $v = $_ENV[$key] ?? null;

        if (\is_bool($v)) {
            return $v;
        }

        if ($v === 1 || $v === '1') {
            return true;
        }

        if ($v === 0 || $v === '0') {
            return false;
        }

        if (\is_string($v)) {
            $vv = \strtolower(\trim($v));
            if (\in_array($vv, ['true', 'yes', 'on'], true)) {
                return true;
            }
            if (\in_array($vv, ['false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }
}
