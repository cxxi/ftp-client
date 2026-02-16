# cxxi/ftp-client – Roadmap

This document outlines the planned evolution of the library.

The goal is to keep `cxxi/ftp-client`:

* Production-grade
* Security-oriented
* Protocol-agnostic
* Clean-architecture compliant
* Fully testable

This roadmap focuses on stability, safety, and advanced transport capabilities.

---

## Philosophy

The library aims to:

* Provide a unified API across FTP / FTPS / SFTP
* Offer strong safety guarantees
* Avoid global state
* Maintain strict static analysis compliance
* Remain framework agnostic

Future features must align with:

* Deterministic behavior
* Explicit configuration
* Transport abstraction purity
* Testability

---

## SFTP Improvements

### 1. phpseclib Backend (Major Feature)

Add optional support for `phpseclib` as an alternative SFTP transport.

#### Motivation

The current SFTP implementation relies on `ext-ssh2`, which has limitations:

* No SHA256 fingerprint exposure
* Limited host key control
* System-level dependency
* Limited portability in some environments

#### Benefits

* Native SHA256 fingerprint verification
* OpenSSH-compatible fingerprint format
* No dependency on `ext-ssh2`
* Greater portability
* Better stream control
* Potential async compatibility in the future

#### Design

* Keep the existing contract
* Introduce a backend strategy
* Select backend via configuration
* Maintain 100% compatibility with current API

---

### 2. OpenSSH known_hosts Support

Add support for OpenSSH-style `known_hosts` verification.

#### Features

* Parse existing `known_hosts` file
* Automatic host key matching
* Strict verification mode
* Optional host key persistence

#### Optional Modes

* Strict mode (fail if unknown)
* TOFU (Trust On First Use)
* Read-only known_hosts

---

### 3. Extended Fingerprint Support

Expose SHA256 fingerprint verification when available.

* Via phpseclib backend
* Future ext-ssh2 improvements
* Support for OpenSSH base64 format

---

### 4. Strict Algorithm Enforcement

Allow enforcing specific host key algorithms:

```php
hostKeyAlgo: 'ssh-ed25519'
```

Fail connection if server uses a different algorithm.

---

## FTPS Improvements

FTPS is currently supported via `ext-ftp` and TLS.

Future improvements aim to enhance security and flexibility.

---

### 1. TLS Certificate Verification Controls

Provide explicit configuration for:

* Certificate verification enable/disable
* Custom CA file
* Custom CA path
* Allow self-signed certificates

This depends on what `ext-ftp` exposes and may require an alternative backend.

---

### 2. Certificate Pinning (Future Advanced)

Allow verifying the server certificate fingerprint directly.

Example:

```php
expectedCertificateFingerprint: 'SHA256:...'
```

May require stream-based implementation instead of raw `ext-ftp`.

---

### 3. Explicit vs Implicit FTPS Mode

Support configuration of:

* Explicit FTPS (AUTH TLS)
* Implicit FTPS (port 990)

Even if both are internally similar, exposing the intent improves clarity.

---

### 4. PROT Level Control

Expose control over data channel protection:

* PROT P (Protected)
* PROT C (Clear)

Useful for legacy or mixed environments.

---

### 5. EPSV / PASV Control

Expose fine-grained passive mode control:

* Force EPSV
* Disable EPSV fallback
* Strict PASV mode

---

## Cross-Protocol Enhancements

These features apply to FTP / FTPS / SFTP.

---

### 1. Atomic Upload Helper

Provide a safe upload mechanism:

```php
$client->putFileAtomic('file.csv', '/local/file.csv');
```

Implementation:

* Upload to temporary name (`.part`)
* Rename after successful transfer

This improves safety in production environments.

---

### 2. Stream-Based API

Add support for stream operations:

```php
$client->putStream($resource, 'remote.txt');
$client->getStream('remote.txt');
```

Benefits:

* No temporary file required
* Better memory control
* Framework integration
* Streaming HTTP responses
* CLI pipelines

---

### 3. Resumable Transfers

Support transfer resuming:

* FTP REST command
* SFTP offset-based resume

Useful for large file transfers.

---

### 4. Transfer Progress Callback

Allow monitoring transfer progress:

```php
$client->putFile(
    'file.csv',
    '/local/file.csv',
    progress: function (int $bytesTransferred) {}
);
```

Useful for:

* CLI tools
* Worker logs
* Monitoring
* UX feedback

---

### 5. Advanced Retry Safety

Enhance retry system with:

* Idempotency awareness
* Atomic rename detection
* Retry context propagation
* Retry metadata inspection

---

### 6. Long-Running Worker Stability

Improve behavior for:

* Stale connections
* Automatic reconnection
* Idle timeout detection
* Keep-alive strategy

---

## Observability & Diagnostics

---

### 1. Structured Event Hooks

Allow registering listeners for:

* Connection start
* Transfer start
* Transfer end
* Retry attempt
* Failure

---

### 2. Extended Logging Context

Add optional structured metadata:

* Protocol
* Retry attempt count
* Operation type
* Duration

---

## Developer Experience

---

### 1. Credential Providers

Allow deferred credentials:

```php
passwordProvider: fn () => getenv('FTP_PASSWORD')
```

Avoid storing secrets in memory longer than needed.

---

### 2. Enhanced Configuration Validation

Improve error messages for:

* Invalid URL schemes
* Missing required extensions
* Misconfigured retry policies

---

### 3. Better Error Taxonomy

Introduce more granular exception types:

* ConnectionException
* AuthenticationException
* CertificateException
* FingerprintMismatchException

---

## Performance & Scalability

---

### 1. Connection Pooling (Advanced)

Optional pooling for:

* High-throughput workers
* Batch processing
* Background jobs

---

### 2. Async Compatibility (Exploratory)

Evaluate compatibility with:

* ReactPHP
* Amp

Only if architecture remains clean.

---

## Quality Goals

The roadmap will maintain:

* 100% unit test coverage on core domain
* Integration coverage for each protocol
* PHPStan level 8 (or higher if stable)
* No global state
* Backward compatibility guarantees

---

## Stability Commitment

Features in this roadmap:

* Are evaluated for backward compatibility
* Will not break existing API contracts without major version bump
* Will preserve deterministic behavior

---

## Contributing to the Roadmap

Suggestions and discussions are welcome via:

* GitHub Issues
* GitHub Discussions

Features should align with:

* Safety
* Predictability
* Clean architecture
* Protocol abstraction integrity

---

## Long-Term Vision

`cxxi/ftp-client` aims to become:

* A reference-grade transport abstraction for PHP
* A production-safe alternative to raw extensions
* A security-conscious FTP/SFTP client
* A foundation for framework integration
