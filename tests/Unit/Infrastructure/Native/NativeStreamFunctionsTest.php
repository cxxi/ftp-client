<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Native\NativeStreamFunctions;
use Cxxi\FtpClient\Tests\Support\RecordingInvoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeStreamFunctions::class)]
final class NativeStreamFunctionsTest extends TestCase
{
    public function testStreamAdapterDelegatesToInvokerWithSequentialReturns(): void
    {
        $dirHandle = new \stdClass();
        $fromHandle = new \stdClass();
        $toHandle = new \stdClass();

        $invoker = new RecordingInvoker(
            returnsByFunction: [
                'opendir' => $dirHandle,
                'readdir' => ['.', '..', 'a.txt', false],
                'closedir' => null,
                'fopen' => [$fromHandle, $toHandle],
                'fclose' => null,
                'stream_set_timeout' => true,
                'stream_copy_to_stream' => 10,
            ]
        );

        $streams = new NativeStreamFunctions($invoker);

        $h = $streams->opendir('/tmp');
        self::assertSame($dirHandle, $h);

        $seen = [];
        while (true) {
            $entry = $streams->readdir($h);
            if ($entry === false) {
                break;
            }
            $seen[] = $entry;
        }

        $streams->closedir($h);

        self::assertContains('a.txt', $seen);

        $from = $streams->fopen('/tmp/src.txt', 'r');
        $to = $streams->fopen('/tmp/dst.txt', 'w');

        self::assertSame($fromHandle, $from);
        self::assertSame($toHandle, $to);

        self::assertTrue($streams->streamSetTimeout($from, 1));
        self::assertTrue($streams->streamSetTimeout($to, 1));

        $copied = $streams->streamCopyToStream($from, $to);
        self::assertSame(10, $copied);

        $streams->fclose($from);
        $streams->fclose($to);

        self::assertNotEmpty($invoker->calls);

        self::assertSame(
            ['opendir', ['/tmp']],
            $invoker->calls[0]
        );
    }
}
