<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Port;

/**
 * Generic invoker for native/global PHP functions.
 *
 * This indirection allows production code to call real PHP internal functions
 * (e.g. \ftp_connect, \ssh2_connect) while keeping unit tests hermetic and
 * deterministic (no network access, no dependency on extensions availability).
 *
 * The invoker is a callable object via {@see __invoke()}.
 * Example usage:
 *   $invoke('ftp_connect', [$host, $port, $timeout]);
 *
 * The invoker is also responsible for exposing function availability checks
 * (e.g. \function_exists('ftp_mlsd')) in a testable way.
 */
interface NativeFunctionInvokerInterface
{
    /**
     * Invoke a native/global function by name with a list of arguments.
     *
     * The function name MUST be provided without a leading backslash.
     * For example: "ftp_connect", "ssh2_connect", "ftp_mlsd".
     *
     * Implementations are expected to call the *global* function (usually by
     * prefixing it with "\" internally).
     *
     * @param non-empty-string $function
     *        Global function name without leading "\".
     * @param array<int, mixed> $args
     *        Positional arguments to pass to the native function.
     *
     * @return mixed The return value of the invoked function.
     */
    public function __invoke(string $function, array $args): mixed;

    /**
     * Check whether a native/global function exists in the current runtime.
     *
     * This is primarily used for optional/ext-dependent functions such as
     * `ftp_mlsd`, which may not exist depending on the PHP build and extension
     * version.
     *
     * @param non-empty-string $function
     *        Global function name without leading "\".
     */
    public function functionExists(string $function): bool;
}
