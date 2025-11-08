# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
