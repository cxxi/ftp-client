<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Enum;

use Cxxi\FtpClient\Enum\HostKeyAlgo;
use Cxxi\FtpClient\Enum\PassiveMode;
use Cxxi\FtpClient\Enum\Protocol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HostKeyAlgo::class)]
#[CoversClass(PassiveMode::class)]
#[CoversClass(Protocol::class)]
final class EnumsSmokeTest extends TestCase
{
    public function testHostKeyAlgoValues(): void
    {
        self::assertSame('ssh-rsa', HostKeyAlgo::SSH_RSA->value);
        self::assertSame('ssh-ed25519', HostKeyAlgo::SSH_ED25519->value);
        self::assertSame('ecdsa-sha2-nistp256', HostKeyAlgo::ECDSA_SHA2_NISTP256->value);
        self::assertSame('ecdsa-sha2-nistp384', HostKeyAlgo::ECDSA_SHA2_NISTP384->value);
        self::assertSame('ecdsa-sha2-nistp521', HostKeyAlgo::ECDSA_SHA2_NISTP521->value);
    }

    public function testPassiveModeValues(): void
    {
        self::assertSame('auto', PassiveMode::AUTO->value);
        self::assertSame('true', PassiveMode::TRUE->value);
        self::assertSame('false', PassiveMode::FALSE->value);
    }

    public function testProtocolValues(): void
    {
        self::assertSame('ftp', Protocol::FTP->value);
        self::assertSame('ftps', Protocol::FTPS->value);
        self::assertSame('sftp', Protocol::SFTP->value);
    }
}
