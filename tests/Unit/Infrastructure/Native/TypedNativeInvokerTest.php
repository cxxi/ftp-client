<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Unit\Infrastructure\Native;

use Cxxi\FtpClient\Exception\NativeCallTypeMismatchException;
use Cxxi\FtpClient\Infrastructure\Native\TypedNativeInvoker;
use Cxxi\FtpClient\Infrastructure\Port\FtpConnectionTypeCheckerInterface;
use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypedNativeInvoker::class)]
final class TypedNativeInvokerTest extends TestCase
{
    public function testItAcceptsExpectedTypes(): void
    {
        $res1 = \fopen('php://temp', 'r+');
        self::assertIsResource($res1);

        $res2 = \fopen('php://temp', 'r+');
        self::assertIsResource($res2);

        $ftpConnLike = (object) ['__marker' => 'ftp-conn'];

        $base = new FakeInvoker([
            'mixed_ok' => new \stdClass(),
            'string_ok' => 'hello',

            'nullable_string_ok_null' => null,
            'nullable_string_ok_string' => 'x',

            'string_or_false_ok_string' => 'x',
            'string_or_false_ok_false' => false,

            'int_ok' => 123,

            'int_or_false_ok_int' => 456,
            'int_or_false_ok_false' => false,

            'nullable_int_ok_null' => null,
            'nullable_int_ok_int' => 7,

            'bool_ok' => true,

            'array_or_false_ok_array' => ['a' => 1],
            'array_or_false_ok_false' => false,

            'resource_or_false_ok_res' => $res1,
            'resource_or_false_ok_false' => false,

            'ftp_connection_or_false_ok_res' => $res2,
            'ftp_connection_or_false_ok_false' => false,
            'ftp_connection_or_false_ok_obj' => $ftpConnLike,

            'resource_or_false_or_null_ok_res' => $res2,
            'resource_or_false_or_null_ok_false' => false,
            'resource_or_false_or_null_ok_null' => null,
        ]);

        $typed = new TypedNativeInvoker($base, new FakeFtpConnectionTypeChecker());

        self::assertInstanceOf(\stdClass::class, $typed->mixed('mixed_ok', []));

        self::assertSame('hello', $typed->string('string_ok', []));

        self::assertNull($typed->nullableString('nullable_string_ok_null', []));
        self::assertSame('x', $typed->nullableString('nullable_string_ok_string', []));

        self::assertSame('x', $typed->stringOrFalse('string_or_false_ok_string', []));
        self::assertFalse($typed->stringOrFalse('string_or_false_ok_false', []));

        self::assertSame(123, $typed->int('int_ok', []));

        self::assertSame(456, $typed->intOrFalse('int_or_false_ok_int', []));
        self::assertFalse($typed->intOrFalse('int_or_false_ok_false', []));

        self::assertNull($typed->nullableInt('nullable_int_ok_null', []));
        self::assertSame(7, $typed->nullableInt('nullable_int_ok_int', []));

        self::assertTrue($typed->bool('bool_ok', []));

        self::assertSame(['a' => 1], $typed->arrayOrFalse('array_or_false_ok_array', []));
        self::assertFalse($typed->arrayOrFalse('array_or_false_ok_false', []));

        $out1 = $typed->resourceOrFalse('resource_or_false_ok_res', []);
        self::assertIsResource($out1);

        self::assertFalse($typed->resourceOrFalse('resource_or_false_ok_false', []));

        $ftpOutRes = $typed->ftpConnectionOrFalse('ftp_connection_or_false_ok_res', []);
        self::assertIsResource($ftpOutRes);

        self::assertFalse($typed->ftpConnectionOrFalse('ftp_connection_or_false_ok_false', []));

        $ftpOutObj = $typed->ftpConnectionOrFalse('ftp_connection_or_false_ok_obj', []);
        self::assertIsObject($ftpOutObj);

        $out2 = $typed->resourceOrFalseOrNull('resource_or_false_or_null_ok_res', []);
        self::assertIsResource($out2);

        self::assertFalse($typed->resourceOrFalseOrNull('resource_or_false_or_null_ok_false', []));
        self::assertNull($typed->resourceOrFalseOrNull('resource_or_false_or_null_ok_null', []));

        self::assertNotEmpty($base->calls);
    }

    /**
     * @return iterable<string, array{
     *   0: non-empty-string,
     *   1: mixed,
     *   2: \Closure(\Cxxi\FtpClient\Infrastructure\Native\TypedNativeInvoker): mixed
     * }>
     */
    public static function invalidReturnTypesProvider(): iterable
    {
        yield 'nullableString throws' => [
            'string|null',
            123,
            static fn (TypedNativeInvoker $typed) => $typed->nullableString('f', []),
        ];

        yield 'stringOrFalse throws' => [
            'string|false',
            123,
            static fn (TypedNativeInvoker $typed) => $typed->stringOrFalse('f', []),
        ];

        yield 'int throws' => [
            'int',
            '123',
            static fn (TypedNativeInvoker $typed) => $typed->int('f', []),
        ];

        yield 'intOrFalse throws' => [
            'int|false',
            '123',
            static fn (TypedNativeInvoker $typed) => $typed->intOrFalse('f', []),
        ];

        yield 'nullableInt throws' => [
            'int|null',
            '123',
            static fn (TypedNativeInvoker $typed) => $typed->nullableInt('f', []),
        ];

        yield 'bool throws' => [
            'bool',
            1,
            static fn (TypedNativeInvoker $typed) => $typed->bool('f', []),
        ];

        yield 'arrayOrFalse throws' => [
            'array|false',
            new \stdClass(),
            static fn (TypedNativeInvoker $typed) => $typed->arrayOrFalse('f', []),
        ];

        yield 'resourceOrFalse throws' => [
            'resource|false',
            '__nope__',
            static fn (TypedNativeInvoker $typed) => $typed->resourceOrFalse('f', []),
        ];

        yield 'ftpConnectionOrFalse throws' => [
            'resource|FTP\\Connection|false',
            123,
            static fn (TypedNativeInvoker $typed) => $typed->ftpConnectionOrFalse('f', []),
        ];

        yield 'resourceOrFalseOrNull throws' => [
            'resource|false|null',
            123,
            static fn (TypedNativeInvoker $typed) => $typed->resourceOrFalseOrNull('f', []),
        ];

        yield 'string throws' => [
            'string',
            123,
            static fn (TypedNativeInvoker $typed) => $typed->string('f', []),
        ];
    }

    #[DataProvider('invalidReturnTypesProvider')]
    public function testEachHelperThrowsOnInvalidReturnType(
        string $expected,
        mixed $actual,
        callable $call
    ): void {
        $typed = new TypedNativeInvoker(
            new FakeInvoker(['f' => $actual]),
            new FakeFtpConnectionTypeChecker(),
        );

        $this->expectException(NativeCallTypeMismatchException::class);
        $this->expectExceptionMessageMatches(
            '/^Native function "f" must return ' . \preg_quote($expected, '/') . ', got /'
        );

        $call($typed);
    }
}

/**
 * @internal
 */
final class FakeInvoker implements NativeFunctionInvokerInterface
{
    /** @var array<int, array{0: non-empty-string, 1: array<int, mixed>}> */
    public array $calls = [];

    /**
     * @param array<non-empty-string, mixed> $returnsByFunction
     */
    public function __construct(private array $returnsByFunction)
    {
    }

    public function __invoke(string $function, array $args): mixed
    {
        $this->calls[] = [$function, $args];

        if (!\array_key_exists($function, $this->returnsByFunction)) {
            return null;
        }

        return $this->returnsByFunction[$function];
    }

    public function functionExists(string $function): bool
    {
        return true;
    }
}

/**
 * @internal
 */
final class FakeFtpConnectionTypeChecker implements FtpConnectionTypeCheckerInterface
{
    public function isFtpConnection(mixed $value): bool
    {
        return \is_object($value) && \property_exists($value, '__marker');
    }
}
