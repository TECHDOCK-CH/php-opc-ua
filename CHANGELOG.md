# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - TBD

### Changed
- **BREAKING:** requires `phpseclib/phpseclib` ^4.0 (was ^3.0). phpseclib 3 and 4 use different
  namespaces (`phpseclib3\` vs `phpseclib4\`), so one release cannot support both.
- Allow `symfony/cache` ^8.0 and `brick/date-time` ^0.10.
- **BREAKING:** `CertificateValidator::getSubject()` / `getIssuer()` return phpseclib 4's
  `X509::DN_ARRAY` structure (`['rdnSequence' => ...]` with phpseclib ASN.1 objects as values).
- `CertificateValidator::validateAndGetInfo()` formats `issuer` like `subject` (`CN=..., O=...`).
- `TrustStore::fromPemFile()` / `fromDirectory()` store certificates as DER and load every
  certificate of a PEM bundle, not only the first one.

### Fixed
- Chain validation verifies the certificate signature with the issuer's public key
  (PKCS#1 v1.5 SHA-1/SHA-2 and RSASSA-PSS). Before, phpseclib 3 only checked against its own
  (empty) CA store, so every CA-issued certificate that reached this check was rejected.
- Subject/issuer DN comparison (self-signed detection, `TrustStore::findBySubject()`) compares
  the attribute values. Before, only the number of RDNs was compared.
- Chain validation stops after 10 issuers instead of recursing forever on issuer cycles.
- `RsaCrypto::decrypt()` still throws `RuntimeException('RSA decryption failed')` now that
  phpseclib 4 throws instead of returning `false`.

## [Unreleased]

### Added
- Complete OPC UA binary protocol implementation
- ClientBuilder fluent API for easy client configuration
- Secure channel with Basic256Sha256 encryption support
- Session management with automatic reconnection
- Browse services with continuation point handling
- Read/Write services for multiple nodes
- Subscription and monitored items support
- Event monitoring with filtering
- Method call support
- Historical data access (ReadRaw, ReadProcessed)
- Custom structure type support with dynamic decoding
- ExtensionObject handling with optional fields and unions
- LRU cache for node metadata
- Automatic batch splitting for large operations
- Server capability detection
- Certificate validation and X.509 authentication
- Username/password authentication
- Anonymous authentication with automatic policy selection
- PSR-3 logging integration
- Unix socket support for local connections
- Comprehensive example suite (20+ examples)
- Full test coverage (35 test files)
- GitHub Actions CI/CD pipeline
- Security vulnerability scanning
- Automated release workflow

### Changed
- Moved to PHP 8.4 with strict types throughout
- Migrated to named arguments for better readability
- Improved error messages with context

### Fixed
- ExtensionObject optional field handling
- User token policy automatic ID detection
- Recursive structure decoding
- Connection state management
- Timeout handling in async operations

## [1.0.0] - TBD

Initial production release.

### Core Features
- Binary protocol client (opc.tcp://)
- Security modes: None, Sign, SignAndEncrypt
- All standard OPC UA services
- Type-safe API with PHP 8.4 features
- Production-ready error handling
- Performance optimizations

[Unreleased]: https://github.com/TECHDOCK-CH/php-opc-ua/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/TECHDOCK-CH/php-opc-ua/releases/tag/v1.0.0
