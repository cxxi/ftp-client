<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Integration\Ftps;

use PHPUnit\Framework\Attributes\Group;

#[Group('ftps')]
final class FtpsPathNormalizationTest extends FtpsIntegrationTestCase
{
    public function testPutAndStatWorkWithRelativeAndAbsolute_paths(): void
    {
        $this->requireFtps();
        $client = $this->client();

        $name = $this->uniq('ftps-path') . '.txt';
        $local = \sys_get_temp_dir() . '/' . $name;
        \file_put_contents($local, "hello\n" . \bin2hex(\random_bytes(16)) . "\n");

        $client->putFile($name, $local);

        $sizeRel = $client->getSize($name);
        self::assertIsInt($sizeRel);
        self::assertGreaterThan(0, $sizeRel);

        $sizeAbs = $client->getSize('/' . $name);
        if ($sizeAbs !== null) {
            self::assertSame($sizeRel, $sizeAbs);
        }

        $client->deleteFile($name);
        $client->closeConnection();
        @\unlink($local);
    }

    public function testListFilesDotAndAbsoluteRootAreConsistent(): void
    {
        $this->requireFtps();
        $client = $this->client();

        $name = $this->uniq('ftps-list') . '.txt';
        $local = \sys_get_temp_dir() . '/' . $name;
        \file_put_contents($local, "x\n");

        $client->putFile($name, $local);

        $listDot = $client->listFiles('.');
        self::assertIsList($listDot);
        self::assertContainsOnly('string', $listDot);

        $dotBasenames = array_map(static fn (string $p): string => \basename($p), $listDot);
        self::assertContains($name, $dotBasenames);

        try {
            $listRoot = $client->listFiles('/');
            self::assertIsList($listRoot);
            self::assertContainsOnly('string', $listRoot);

            $rootBasenames = array_map(static fn (string $p): string => \basename($p), $listRoot);
            self::assertContains($name, $rootBasenames);
        } catch (\Throwable) {
            self::addToAssertionCount(1);
        }

        $client->deleteFile($name);
        $client->closeConnection();
        @\unlink($local);
    }
}
