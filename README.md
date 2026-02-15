
# cxxi/ftp-client

[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://php.net)
[![CI](https://github.com/cxxi/ftp-client/actions/workflows/ci.yml/badge.svg)](https://github.com/cxxi/ftp-client/actions)
[![PHPStan Level](https://img.shields.io/badge/phpstan-level%208-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/cxxi/ftp-client.svg)](https://packagist.org/packages/cxxi/ftp-client)
[![Downloads](https://img.shields.io/packagist/dt/cxxi/ftp-client.svg)](https://packagist.org/packages/cxxi/ftp-client)

Pure PHP **FTP / FTPS / SFTP** client (framework agnostic).

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
* SHA256 fingerprint verification
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

---

## Quick Start

```php
use Cxxi\FtpClient\Service\ClientFactory;

$factory = new ClientFactory();

$client = $factory->create('ftp://user:pass@example.com:21/path');

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

$client = $factory->create(
    'ftps://user:pass@example.com:21/path',
    options: $options
);
```

Or build from an array:

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
]);
```

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

SFTP supports strict host key verification using SHA256 fingerprint.

```php
$options = new ConnectionOptions(
    hostKeyAlgo: 'ssh-ed25519',
    expectedFingerprint: 'SHA256:xxxxxx...',
    strictHostKeyChecking: true
);
```

If `strictHostKeyChecking` is enabled and fingerprint does not match,
connection will fail.

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
$factory = new ClientFactory(logger: $logger);
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

## Tests

```bash
composer test
```

Static analysis:

```bash
composer phpstan
```

---

## License

MIT.

---