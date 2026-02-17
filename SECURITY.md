# Security Policy

## Supported Versions

Security fixes are provided for the latest stable release only.

| Version | Supported |
| ------- | --------- |
| 1.x     | ✅        |
| < 1.0   | ❌        |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it privately.

### Preferred method (GitHub Security Advisory)

1. Go to the repository **Security** tab.
2. Click **Report a vulnerability**.
3. Provide as much detail as possible:
   - affected version(s)
   - steps to reproduce
   - potential impact
   - any suggested fix or patch

### What to expect

- You will receive an acknowledgment within a reasonable time.
- We will investigate and, if confirmed, publish a fix as soon as possible.
- Please do not disclose the issue publicly until a fix is released.

## Scope

This project is a PHP FTP / FTPS / SFTP client library. Typical security-relevant areas include:

- credential handling and logging
- connection security (TLS / host key verification)
- retry behavior for destructive operations
- path handling and filesystem operations