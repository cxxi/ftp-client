<?php

declare(strict_types=1);

namespace Cxxi\FtpClient\Infrastructure\Native;

use Cxxi\FtpClient\Infrastructure\Port\NativeFunctionInvokerInterface;

/**
 * Production invoker that calls real global PHP functions.
 *
 * This implementation forces global resolution by prefixing the function name
 * with "\" (e.g. "\ftp_connect"), ensuring PHP calls the internal function and
 * not a namespaced fallback.
 *
 * In unit tests, a fake invoker can be injected to return deterministic values
 * and record calls.
 */
final class NativeFunctionInvoker implements NativeFunctionInvokerInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(string $function, array $args): mixed
    {
        /** @var non-empty-string $function */
        $fqn = '\\' . $function;

        if (!\is_callable($fqn)) {
            throw new \InvalidArgumentException(sprintf('Function "%s" is not callable.', $function));
        }

        return \call_user_func_array($fqn, $args);
    }

    /**
     * {@inheritDoc}
     */
    public function functionExists(string $function): bool
    {
        return \function_exists($function);
    }
}
