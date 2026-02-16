# cxxi/ftp-client

[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://php.net)
[![CI](https://github.com/cxxi/ftp-client/actions/workflows/ci.yml/badge.svg)](https://github.com/cxxi/ftp-client/actions)
[![Tests](https://img.shields.io/badge/tests-unit%20%2B%20integration-brightgreen.svg)](https://github.com/cxxi/ftp-client/actions)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](https://github.com/cxxi/ftp-client)
[![PHPStan Level](https://img.shields.io/badge/phpstan-level%208-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/cxxi/ftp-client.svg)](https://packagist.org/packages/cxxi/ftp-client)
[![Downloads](https://img.shields.io/packagist/dt/cxxi/ftp-client.svg)](https://packagist.org/packages/cxxi/ftp-client)

Pure PHP **FTP / FTPS / SFTP** client (framework agnostic).

Simple, expressive API:

```php
$client = FtpClient::fromUrl('ftp://user:pass@example.com:22/path');
```

A clean, testable and production-ready transport layer designed for:

* Modern PHP applications (8.2+)
* CLI tools & workers
* Framework integration ([Symfony bundle available](https://github.com/cxxi/ftp-client-bundle))
* High reliability environments

Supports:

* Automatic protocol resolution (`ftp://`, `ftps://`, `sftp://`)
* Safe retry policies with exponential backoff & jitter
* SFTP host key verification (SHA256)
* PSR-3 logging
* Clean architecture (Ports & Adapters)

---

## Table of Contents

* [Why this library?](#why-this-library)
* [Installation](#installation)
* [Requirements](#requirements)
* [Quick Start](#quick-start)
* [URL Format](#url-format)
* [Using Connection Options](#using-connection-options)
* [Supported Options](#supported-options)
* [Retry Policy](#retry-policy)
* [Common Operations](#common-operations)
* [Authentication](#authentication)
* [Logging](#logging)
* [Connection Lifecycle](#connection-lifecycle)
* [Architecture](#architecture)
* [Quality & Tests](#quality--tests)
* [Troubleshooting](#troubleshooting)
* [Contributing](#contributing)
* [Security](#security)
* [Roadmap](#roadmap)
* [License](#license)

---

## Why this library?

PHP already provides `ext-ftp` and `ext-ssh2`.
So why wrap them?

Because raw extensions:

* Expose global functions
* Mix transport logic with application logic
* Lack retry mechanisms
* Provide no structured error handling
* Are difficult to unit test
* Offer no safety model for destructive operations

This library adds:

### Unified API

Same interface for FTP, FTPS and SFTP.

Switch protocol by changing the URL scheme.

---

### Built-in Retry Strategy

* Configurable retry count
* Exponential backoff
* Optional jitter
* Safe vs unsafe operation handling

Designed for unstable networks and production systems.

---

### Secure SFTP Support

* Host key algorithm control
* MD5 / SHA1 fingerprint verification (via ext-ssh2)
* Strict host key checking option

No silent trust of unknown hosts.

---

### Clean Architecture

* Ports & adapters
* Fully mockable infrastructure layer
* Decoupled from PHP global functions
* Designed for static analysis (PHPStan level 8 clean)

---

### Framework Agnostic

Works in:

* Plain PHP scripts
* Symfony ([via bundle](https://github.com/cxxi/ftp-client-bundle))
* Laravel
* CLI workers
* Cron jobs

No framework dependency.

---

### Production Ready

This library is:

* Fully covered by unit tests (100%)
* Integration tested against real FTP / FTPS / SFTP servers
* Static-analysis clean (PHPStan level 8)
* Free of global state
* Designed for deterministic error handling

It is built to be used in critical environments where network instability and safe retries matter.

---

## Installation

```bash
composer require cxxi/ftp-client
```

---

## Requirements

* PHP **8.2+**
* `psr/log` ^3.0

Optional extensions:

* `ext-ftp` → required for FTP / FTPS
* `ext-ssh2` → required for SFTP

Note: FTPS requires `ext-ftp` with SSL support (`ftp_ssl_connect()` available).

---

## Quick Start

```php
use Cxxi\FtpClient\FtpClient;

$client = FtpClient::fromUrl('ftp://user:pass@example.com:21/path');

$client
    ->connect()
    ->loginWithPassword()
    ->listFiles('.');
```

---

## URL Format

```text
ftp://user:pass@host:21/path
ftps://user:pass@host:21/path
sftp://user:pass@host:22/path
```

Notes:

* Credentials may be URL-encoded.
* Transport is resolved automatically from the scheme.
* Path becomes the working directory after connection.

---

## Using Connection Options

You can pass a `ConnectionOptions` instance:

```php
use Cxxi\FtpClient\Model\ConnectionOptions;

$options = new ConnectionOptions(
    timeout: 15,
    retryMax: 3,
    retryDelayMs: 500,
    retryBackoff: 2.0,
    retryJitter: true
);

$client = FtpClient::fromUrl(
    'ftps://user:pass@example.com:21/path',
    options: $options
);
```

### Array format (canonical structure)

You can also build options from an array.
The canonical structure groups protocol-specific options under dedicated keys:

```php
$options = ConnectionOptions::fromArray([
    'timeout' => 15,
    'passive' => 'auto',

    'retry' => [
        'max' => 3,
        'delay_ms' => 500,
        'backoff' => 2.0,
        'jitter' => true,
        'unsafe_operations' => false,
    ],

    'sftp' => [
        'host_key_algo' => 'ssh-ed25519',
        'expected_fingerprint' => 'MD5:aa:bb:cc:dd:...',
        'strict_host_key_checking' => true,
    ],
]);
```

Protocol-specific keys are ignored when not applicable
(e.g. `passive` is ignored for SFTP).

---

## Supported Options

### timeout

Type: `int|null`
Applies to: FTP / FTPS / SFTP

* FTP/FTPS → connect timeout
* SFTP → stream timeout for transfers

---

### passive (FTP / FTPS only)

Type: `auto | true | false`

* `true` → force passive mode
* `false` → active mode
* `auto` → try passive, fallback to active

Ignored for SFTP.

---

### SFTP Host Key Verification

SFTP supports strict host key verification using MD5 or SHA1 fingerprints
(as supported by ext-ssh2).

```php
$options = new ConnectionOptions(
    hostKeyAlgo: 'ssh-ed25519',
    expectedFingerprint: 'MD5:aa:bb:cc:dd:...',
    strictHostKeyChecking: true
);
```

If `strictHostKeyChecking` is enabled and fingerprint does not match,
connection will fail.

---

## SFTP Fingerprint Limitations (ext-ssh2)

When using the `ext-ssh2` extension (PECL ssh2), only the following
fingerprint algorithms are available via `ssh2_fingerprint()`:

* `MD5`
* `SHA1`

The extension does **not** expose SHA256 fingerprints, even though
the underlying libssh2 library supports it.

As a consequence:

* `SHA256:` fingerprints (OpenSSH default format) cannot be verified
  when using ext-ssh2.
* Only `MD5:` and `SHA1:` prefixed fingerprints are supported.
* There is no automatic fallback between algorithms.

If a `SHA256:` fingerprint is provided, the connection will fail
when strict host key checking is enabled.

This limitation comes from the PHP extension API, not from the
library itself.

---

## Retry Policy

Retry is **disabled by default** (`retryMax = 0`).

When enabled:

Safe operations are retried:

* connect
* login
* listFiles
* downloadFile
* getSize
* getMTime
* isDirectory

Unsafe operations are not retried unless explicitly allowed:

* deleteFile
* rename
* chmod
* removeDirectory
* removeDirectoryRecursive
* makeDirectory

Enable unsafe retries:

```php
$options = new ConnectionOptions(
    retryMax: 3,
    retryUnsafeOperations: true
);
```

---

## Common Operations

### Upload / Download

```php
$client->putFile('remote.csv', '/local/file.csv');

$client->downloadFile('remote.csv', '/tmp/remote.csv');
```

---

### Directory Utilities

```php
$client->isDirectory('subdir');

$client->makeDirectory('subdir', recursive: true);

$client->removeDirectory('empty-dir');

$client->removeDirectoryRecursive('dir-to-delete');
```

---

### File Utilities

```php
$client->deleteFile('old.csv');

$client->rename('old.csv', 'new.csv');

$size = $client->getSize('file.csv');

$mtime = $client->getMTime('file.csv');

$client->chmod('file.csv', 0644);
```

---

## FTP-only Advanced Listing

Available only on FTP / FTPS:

```php
$raw = $client->rawList('.', recursive: false);

$mlsd = $client->mlsd('.');
```

---

## Authentication

### Password

```php
$client
    ->connect()
    ->loginWithPassword();
```

Override credentials:

```php
$client->loginWithPassword('user', 'pass');
```

---

### SFTP Public Key

```php
$client
    ->connect()
    ->loginWithPubkey(
        '/home/me/.ssh/id_rsa.pub',
        '/home/me/.ssh/id_rsa',
        user: 'my-user'
    );
```

Only valid for SFTP connections.

---

## Logging

Pass a `Psr\Log\LoggerInterface` to the factory:

```php
use Cxxi\FtpClient\FtpClient;

$client = FtpClient::fromUrl(
    'ftp://user:pass@example.com:21/path',
    logger: $logger
);
```

Logged events include:

* connection attempts
* authentication
* transfers
* destructive operations

Credentials are never logged.

---

## Connection Lifecycle

Connections auto-close on destruction.

For long-running scripts:

```php
$client->closeConnection();
```

Safe to call even if not connected.

---

## Architecture

The library follows a clean architecture approach:

* Transport contracts (`Contracts`)
* Protocol-specific services (FTP / SFTP)
* Infrastructure ports (filesystem, streams, ssh2, ftp)
* Native adapters
* Retry wrapper with safe/unsafe semantics

This design allows easy mocking and full unit testing.

---

## Quality & Tests

This library is designed for reliability in production environments.

### Unit Tests

* 100% code coverage on the core domain and infrastructure
* Strict PHPUnit 11 configuration
* Full mocking of native adapters
* Error handling and retry semantics fully tested
* PHPStan level 8 clean (source + tests)

Run unit tests:

```bash
composer test:unit
```

Generate coverage report:

```bash
composer test:coverage
```

---

### Integration Tests

All protocols are tested against real Dockerized servers.

| Protocol | Server                   | Tested Features                                              |
| -------- | ------------------------ | ------------------------------------------------------------ |
| FTP      | pure-ftpd                | Active / Passive / Auto mode, transfers, directory ops, MLSD |
| FTPS     | pure-ftpd (TLS required) | Explicit TLS, transfers, error cases                         |
| SFTP     | OpenSSH                  | Password auth, public key auth, fingerprint verification     |

Each integration stack is fully isolated and reproducible.

Run individual protocol tests:

```bash
composer test:integration:ftp
composer test:integration:ftps
composer test:integration:sftp
```

Run all integration tests:

```bash
composer test:integration
```

Run everything (unit + integration):

```bash
composer test:all
```

---

### Static Analysis

PHPStan level 8 enforced:

```bash
composer phpstan
```

---

### Code Style

```bash
composer cs
composer cs:check
```

---

### CI

All checks are enforced in CI:

* Unit tests (100% coverage)
* FTP integration tests
* FTPS (TLS) integration tests
* SFTP integration tests
* PHPStan level 8
* Coding standards

Every protocol feature documented in this README is covered by automated tests.

---

## Troubleshooting

### FTPS: `ftp_ssl_connect()` not available

Make sure `ext-ftp` is compiled with SSL support.
`ftp_ssl_connect()` must be available.

### SFTP: host key verification fails

* Ensure the fingerprint prefix matches (`MD5:` or `SHA1:`).
* SHA256 fingerprints are not supported by `ext-ssh2`.

### Passive mode issues (FTP/FTPS)

If transfers hang behind NAT/firewalls:

* Try forcing passive mode (`passive: true`)
* Or use `passive: auto`

### Connection timeouts

Adjust the `timeout` option depending on network conditions.

---

## Contributing

Contributions are welcome.

Before submitting a pull request:

1. Ensure all unit tests pass.
2. Ensure all integration tests pass.
3. Run PHPStan (level 8 must remain clean).
4. Follow existing coding standards.

Useful commands:

```bash
composer test:all
composer phpstan
composer cs
```

Please open an issue first for significant changes or architectural discussions.

---

## Security

If you discover a security vulnerability, please open a GitHub Security Advisory
or contact the maintainer privately before disclosing it publicly.

Credentials are never logged by design.

---

## Roadmap

The project roadmap is maintained separately to keep this README focused and concise.

**Full roadmap available here:**
**[docs/ROADMAP.md](docs/ROADMAP.md)**

The roadmap covers:

* SFTP backend improvements (phpseclib support, SHA256 fingerprints)
* OpenSSH `known_hosts` integration
* FTPS security enhancements (TLS controls, certificate validation)
* Stream-based API & atomic uploads
* Transfer progress callbacks
* Resumable transfers
* Long-running worker stability improvements
* Observability & structured events
* Advanced retry safety
* Performance & scalability explorations

All roadmap items follow the project principles:

* Clean architecture
* Deterministic behavior
* Strong safety guarantees
* Backward compatibility (unless major version bump)

Community feedback is welcome via GitHub Issues and Discussions.

---

## License

MIT — see [LICENSE](LICENSE).
