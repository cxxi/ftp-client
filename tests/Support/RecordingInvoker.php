<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Tests\Support;

use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;

/**
 * Test invoker that records all calls and returns predefined values.
 *
 * Supports:
 * - Single return value per function name (default behavior)
 * - Sequential return values per function name (when configured as a list)
 *
 * Example:
 *  $invoker = new RecordingInvoker(
 *      returnsByFunction: [
 *          'readdir' => ['a.txt', false],
 *          'fopen' => [$h1, $h2], // first call returns $h1, second returns $h2
 *          'stream_copy_to_stream' => 10,
 *      ]
 *  );
 */
final class RecordingInvoker implements NativeFunctionInvokerInterface
{
    /**
     * Recorded calls: each entry is [functionName, args].
     *
     * @var array<int, array{0:string, 1:array<int, mixed>}>
     */
    public array $calls = [];

    /**
     * Return map:
     * - value can be a scalar/object/etc (single return)
     * - or a list (sequential returns)
     *
     * @var array<string, mixed>
     */
    private array $returnsByFunction = [];

    /**
     * Tracks how many times a given function has been called, to support sequences.
     *
     * @var array<string, int>
     */
    private array $callIndexByFunction = [];

    /**
     * Function existence map for NativeFunctionInvokerInterface::functionExists().
     *
     * @var array<string, bool>
     */
    private array $exists = [];

    /**
     * @param array<string, mixed> $returnsByFunction
     *        Map of function name => return value OR list of return values.
     *        If the configured value is a list (array with sequential integer keys),
     *        each call consumes the next value.
     * @param array<string, bool> $exists
     *        Map of function name => existence boolean.
     */
    public function __construct(array $returnsByFunction = [], array $exists = [])
    {
        $this->returnsByFunction = $returnsByFunction;
        $this->exists = $exists;
    }

    /**
     * Invoke a native function (fake) and return the configured value.
     *
     * @param string $function
     * @param array<int, mixed> $args
     *
     * @return mixed
     */
    public function __invoke(string $function, array $args): mixed
    {
        $this->calls[] = [$function, $args];

        if (!\array_key_exists($function, $this->returnsByFunction)) {
            throw new \RuntimeException("Unexpected native call: {$function}");
        }

        $configured = $this->returnsByFunction[$function];

        if ($configured instanceof ReturnValue) {
            return $configured->value;
        }

        // If configured as a sequential list, consume next element.
        if (\is_array($configured) && $this->isSequentialList($configured)) {
            $i = $this->callIndexByFunction[$function] ?? 0;

            if (!\array_key_exists($i, $configured)) {
                throw new \RuntimeException(
                    "No more queued return values for {$function} (index {$i})"
                );
            }

            $this->callIndexByFunction[$function] = $i + 1;

            return $configured[$i];
        }

        // Otherwise return the configured value as-is.
        return $configured;
    }

    /**
     * Check whether a native function exists (fake).
     *
     * @param string $function
     */
    public function functionExists(string $function): bool
    {
        return $this->exists[$function] ?? false;
    }

    /**
     * Returns true when the given array is a "list" (0..n-1 integer keys).
     *
     * We intentionally treat only true lists as sequences. Associative arrays
     * (e.g. ['size' => 1]) are treated as single return values.
     *
     * @param array<mixed> $value
     */
    private function isSequentialList(array $value): bool
    {
        // PHP 8.1+ provides array_is_list().
        return \array_is_list($value);
    }
}
