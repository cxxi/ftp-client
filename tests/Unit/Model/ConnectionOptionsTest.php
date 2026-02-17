<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Model;

use Cxxi\FtpClient\Enum\HostKeyAlgo;
use Cxxi\FtpClient\Enum\PassiveMode;
use Cxxi\FtpClient\Model\ConnectionOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionOptions::class)]
final class ConnectionOptionsTest extends TestCase
{
    public function testFromArrayDefaultsWhenEmpty(): void
    {
        $opt = ConnectionOptions::fromArray([]);

        self::assertNull($opt->timeout);
        self::assertSame(PassiveMode::AUTO, $opt->passive);

        self::assertNull($opt->hostKeyAlgo);
        self::assertNull($opt->expectedFingerprint);
        self::assertFalse($opt->strictHostKeyChecking);

        self::assertSame(0, $opt->retryMax);
        self::assertSame(0, $opt->retryDelayMs);
        self::assertSame(2.0, $opt->retryBackoff);
        self::assertFalse($opt->retryJitter);
        self::assertFalse($opt->retryUnsafeOperations);
    }

    public function testTimeoutParsesIntAndNumericStringAndRejectsNonPositive(): void
    {
        self::assertSame(10, ConnectionOptions::fromArray(['timeout' => 10])->timeout);
        self::assertSame(15, ConnectionOptions::fromArray(['timeout' => '15'])->timeout);

        self::assertNull(ConnectionOptions::fromArray(['timeout' => ''])->timeout);
        self::assertNull(ConnectionOptions::fromArray(['timeout' => ' 15 '])->timeout);
        self::assertNull(ConnectionOptions::fromArray(['timeout' => '15s'])->timeout);

        self::assertNull(ConnectionOptions::fromArray(['timeout' => 0])->timeout);
        self::assertNull(ConnectionOptions::fromArray(['timeout' => -5])->timeout);
        self::assertNull(ConnectionOptions::fromArray(['timeout' => '0'])->timeout);
    }

    public function testPassiveParsingCoversBooleansIntsStringsAndFallback(): void
    {
        self::assertSame(PassiveMode::TRUE, ConnectionOptions::fromArray(['passive' => true])->passive);
        self::assertSame(PassiveMode::TRUE, ConnectionOptions::fromArray(['passive' => 1])->passive);
        self::assertSame(PassiveMode::TRUE, ConnectionOptions::fromArray(['passive' => '1'])->passive);

        self::assertSame(PassiveMode::FALSE, ConnectionOptions::fromArray(['passive' => false])->passive);
        self::assertSame(PassiveMode::FALSE, ConnectionOptions::fromArray(['passive' => 0])->passive);
        self::assertSame(PassiveMode::FALSE, ConnectionOptions::fromArray(['passive' => '0'])->passive);

        self::assertSame(PassiveMode::AUTO, ConnectionOptions::fromArray(['passive' => 'AUTO'])->passive);
        self::assertSame(PassiveMode::TRUE, ConnectionOptions::fromArray(['passive' => 'true'])->passive);
        self::assertSame(PassiveMode::FALSE, ConnectionOptions::fromArray(['passive' => 'false'])->passive);

        self::assertSame(PassiveMode::AUTO, ConnectionOptions::fromArray(['passive' => 'wat'])->passive);

        self::assertSame(PassiveMode::AUTO, ConnectionOptions::fromArray(['passive' => ['x']])->passive);
    }

    public function testSftpArrayNonArrayIsIgnored(): void
    {
        $opt = ConnectionOptions::fromArray(['ssh' => 'nope']);

        self::assertNull($opt->hostKeyAlgo);
        self::assertNull($opt->expectedFingerprint);
        self::assertFalse($opt->strictHostKeyChecking);
    }

    public function testHostKeyAlgoAcceptsEnumStringKnownStringUnknownAndBlank(): void
    {
        $optEnum = ConnectionOptions::fromArray([
            'ssh' => ['host_key_algo' => HostKeyAlgo::SSH_RSA],
        ]);
        self::assertInstanceOf(HostKeyAlgo::class, $optEnum->hostKeyAlgo);
        self::assertSame(HostKeyAlgo::SSH_RSA, $optEnum->hostKeyAlgo);

        $optKnown = ConnectionOptions::fromArray([
            'ssh' => ['host_key_algo' => HostKeyAlgo::SSH_RSA->value],
        ]);
        self::assertSame(HostKeyAlgo::SSH_RSA, $optKnown->hostKeyAlgo);

        $optUnknown = ConnectionOptions::fromArray([
            'ssh' => ['host_key_algo' => 'ssh-something-else'],
        ]);
        self::assertIsString($optUnknown->hostKeyAlgo);
        self::assertSame('ssh-something-else', $optUnknown->hostKeyAlgo);

        $optBlank = ConnectionOptions::fromArray([
            'ssh' => ['host_key_algo' => '   '],
        ]);
        self::assertNull($optBlank->hostKeyAlgo);

        $optBadType = ConnectionOptions::fromArray([
            'ssh' => ['host_key_algo' => 123],
        ]);
        self::assertNull($optBadType->hostKeyAlgo);
    }

    public function testExpectedFingerprintIsTrimmedAndNullWhenBlankOrNotString(): void
    {
        self::assertSame(
            'SHA256:abc',
            ConnectionOptions::fromArray(['ssh' => ['expected_fingerprint' => '  SHA256:abc  ']])->expectedFingerprint
        );

        self::assertNull(ConnectionOptions::fromArray(['ssh' => ['expected_fingerprint' => '   ']])->expectedFingerprint);
        self::assertNull(ConnectionOptions::fromArray(['ssh' => ['expected_fingerprint' => 123]])->expectedFingerprint);
    }

    public function testStrictHostKeyCheckingParsesBoolIntAndStringKeywords(): void
    {
        self::assertTrue(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => true]])->strictHostKeyChecking);
        self::assertTrue(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => 1]])->strictHostKeyChecking);
        self::assertTrue(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => '1']])->strictHostKeyChecking);

        self::assertFalse(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => false]])->strictHostKeyChecking);
        self::assertFalse(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => 0]])->strictHostKeyChecking);
        self::assertFalse(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => '0']])->strictHostKeyChecking);

        self::assertTrue(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => 'YES']])->strictHostKeyChecking);
        self::assertTrue(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => ' on ']])->strictHostKeyChecking);
        self::assertTrue(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => 'true']])->strictHostKeyChecking);

        self::assertFalse(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => 'NO']])->strictHostKeyChecking);
        self::assertFalse(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => ' off ']])->strictHostKeyChecking);
        self::assertFalse(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => 'false']])->strictHostKeyChecking);

        self::assertFalse(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => 'maybe']])->strictHostKeyChecking);

        self::assertFalse(ConnectionOptions::fromArray(['ssh' => ['strict_host_key_checking' => ['x']]])->strictHostKeyChecking);
    }

    public function testRetryArrayNonArrayIsIgnoredAndDefaultsKept(): void
    {
        $opt = ConnectionOptions::fromArray(['retry' => 'nope']);

        self::assertSame(0, $opt->retryMax);
        self::assertSame(0, $opt->retryDelayMs);
        self::assertSame(2.0, $opt->retryBackoff);
        self::assertFalse($opt->retryJitter);
        self::assertFalse($opt->retryUnsafeOperations);
    }

    public function testRetryMaxAndDelayMsParseIntsAndNumericStringsAndClampToZero(): void
    {
        self::assertSame(3, ConnectionOptions::fromArray(['retry' => ['max' => 3]])->retryMax);
        self::assertSame(4, ConnectionOptions::fromArray(['retry' => ['max' => '4']])->retryMax);
        self::assertSame(0, ConnectionOptions::fromArray(['retry' => ['max' => -2]])->retryMax);
        self::assertSame(0, ConnectionOptions::fromArray(['retry' => ['max' => '']])->retryMax);
        self::assertSame(0, ConnectionOptions::fromArray(['retry' => ['max' => '4x']])->retryMax);

        self::assertSame(250, ConnectionOptions::fromArray(['retry' => ['delay_ms' => 250]])->retryDelayMs);
        self::assertSame(300, ConnectionOptions::fromArray(['retry' => ['delay_ms' => '300']])->retryDelayMs);
        self::assertSame(0, ConnectionOptions::fromArray(['retry' => ['delay_ms' => -10]])->retryDelayMs);
        self::assertSame(0, ConnectionOptions::fromArray(['retry' => ['delay_ms' => '']])->retryDelayMs);
        self::assertSame(0, ConnectionOptions::fromArray(['retry' => ['delay_ms' => '300ms']])->retryDelayMs);
    }

    public function testRetryBackoffParsesFloatIntNumericStringAndResetsWhenNonPositive(): void
    {
        self::assertSame(1.5, ConnectionOptions::fromArray(['retry' => ['backoff' => 1.5]])->retryBackoff);
        self::assertSame(3.0, ConnectionOptions::fromArray(['retry' => ['backoff' => 3]])->retryBackoff);
        self::assertSame(2.5, ConnectionOptions::fromArray(['retry' => ['backoff' => ' 2.5 ']])->retryBackoff);

        self::assertSame(2.0, ConnectionOptions::fromArray(['retry' => ['backoff' => 'nope']])->retryBackoff);

        self::assertSame(2.0, ConnectionOptions::fromArray(['retry' => ['backoff' => 0]])->retryBackoff);
        self::assertSame(2.0, ConnectionOptions::fromArray(['retry' => ['backoff' => -1]])->retryBackoff);
        self::assertSame(2.0, ConnectionOptions::fromArray(['retry' => ['backoff' => '0']])->retryBackoff);
    }

    public function testRetryJitterAndUnsafeOperationsParseBoolIntAndStringKeywords(): void
    {
        $opt = ConnectionOptions::fromArray([
            'retry' => [
                'jitter' => 'yes',
                'unsafe_operations' => 'on',
            ],
        ]);

        self::assertTrue($opt->retryJitter);
        self::assertTrue($opt->retryUnsafeOperations);

        $opt2 = ConnectionOptions::fromArray([
            'retry' => [
                'jitter' => 'off',
                'unsafe_operations' => 'no',
            ],
        ]);

        self::assertFalse($opt2->retryJitter);
        self::assertFalse($opt2->retryUnsafeOperations);

        $opt3 = ConnectionOptions::fromArray([
            'retry' => [
                'jitter' => 1,
                'unsafe_operations' => 0,
            ],
        ]);

        self::assertTrue($opt3->retryJitter);
        self::assertFalse($opt3->retryUnsafeOperations);

        $opt4 = ConnectionOptions::fromArray([
            'retry' => [
                'jitter' => 'maybe',
                'unsafe_operations' => 'maybe',
            ],
        ]);

        self::assertFalse($opt4->retryJitter);
        self::assertFalse($opt4->retryUnsafeOperations);
    }

    public function testRetryJitterAcceptsBooleanRaw(): void
    {
        self::assertTrue(ConnectionOptions::fromArray([
            'retry' => ['jitter' => true],
        ])->retryJitter);

        self::assertFalse(ConnectionOptions::fromArray([
            'retry' => ['jitter' => false],
        ])->retryJitter);
    }

    public function testRetryUnsafeOperationsAcceptsBooleanRaw(): void
    {
        self::assertTrue(ConnectionOptions::fromArray([
            'retry' => ['unsafe_operations' => true],
        ])->retryUnsafeOperations);

        self::assertFalse(ConnectionOptions::fromArray([
            'retry' => ['unsafe_operations' => false],
        ])->retryUnsafeOperations);
    }

    public function testRetryUnsafeOperationsTrueWhenRawIsOneOrStringOne(): void
    {
        self::assertTrue(ConnectionOptions::fromArray([
            'retry' => ['unsafe_operations' => 1],
        ])->retryUnsafeOperations);

        self::assertTrue(ConnectionOptions::fromArray([
            'retry' => ['unsafe_operations' => '1'],
        ])->retryUnsafeOperations);
    }

    public function testRetryJitterFalseWhenRawIsZeroOrStringZero(): void
    {
        self::assertFalse(ConnectionOptions::fromArray([
            'retry' => ['jitter' => 0],
        ])->retryJitter);

        self::assertFalse(ConnectionOptions::fromArray([
            'retry' => ['jitter' => '0'],
        ])->retryJitter);
    }
}
