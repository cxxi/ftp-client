# CHANGELOG

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog.
This project adheres to Semantic Versioning.

---

## [1.0.0] - 2026-02-17

### Added
- Initial stable release
- Unified FTP / FTPS / SFTP client API
- Automatic protocol resolution from URL
- Configurable retry strategy (exponential backoff + optional jitter)
- Safe vs unsafe operation handling
- SFTP host key verification (MD5 / SHA1 via ext-ssh2)
- PSR-3 logging support
- Clean architecture (Ports & Adapters)
- 100% unit test coverage
- Integration tests for FTP / FTPS / SFTP (Dockerized)
- PHPStan level max (9) + strict rules

### Security
- Credentials never logged
- Strict host key checking support for SFTP

## [1.0.1] - 2026-02-17

### Fixed
- fix key in connectionOption