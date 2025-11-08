# PHP OPC UA Client

[![CI](https://github.com/TECHDOCK-CH/php-opc-ua/workflows/CI/badge.svg)](https://github.com/TECHDOCK-CH/php-opc-ua/actions)
[![Security](https://github.com/TECHDOCK-CH/php-opc-ua/workflows/Security/badge.svg)](https://github.com/TECHDOCK-CH/php-opc-ua/actions)
[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Modern, production-ready OPC UA (Unified Architecture) client library for PHP 8.4+. This library is a **PHP implementation** of OPC UA client functionality based on the architecture and design of the [OPC Foundation UA-.NETStandard](https://github.com/OPCFoundation/UA-.NETStandard) implementation. While maintaining wire-protocol compatibility and following the same design patterns, this is an independent implementation adapted for PHP 8.4+ with full type safety and modern language features.

## Features

- **Complete OPC UA Client Implementation**
  - Binary protocol (opc.tcp://) with full UA services
  - Secure channel with encryption (Basic256Sha256)
  - Session management with automatic reconnection
  - Certificate-based authentication

- **High-Level API**
  - Fluent ClientBuilder for easy configuration
  - Browse address space with filtering
  - Read/write nodes with type safety
  - Subscriptions and monitored items
  - Event monitoring and alarms

- **Performance Optimizations**
  - LRU caching for node metadata
  - Automatic batch splitting for large operations
  - Server capability detection
  - Connection pooling ready

- **Production Ready**
  - PSR-3 logging integration
  - Comprehensive error handling
  - Type-safe throughout (PHP 8.4 strict types)
  - PHPStan level 9 compliant
  - Extensive test coverage

## Quick Start

### Installation

```bash
composer require techdock/opcua
```

### Basic Usage

```php
<?php

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Types\NodeId;

// Connect to server
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withAnonymousAuth()
    ->build();

// Read a value
$serverTime = $client->session->read(NodeId::numeric(0, 2258));
echo "Server time: {$serverTime->value}\n";

// Browse nodes
$objectsFolder = NodeId::numeric(0, 85);
$references = $client->browser->browse($objectsFolder);

foreach ($references as $ref) {
    echo "- {$ref->displayName->text}\n";
}

// Clean up
$client->disconnect();
```

### With Performance Features

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://production-server:4840')
    ->application('My App', 'urn:mycompany:app')
    ->withUsernameAuth('operator', 'password')
    ->withCache(maxSize: 5000, ttl: 600.0)  // 10min cache
    ->withAutoBatching()                     // Auto-split large requests
    ->operationTimeout(60000)                // 60s timeout
    ->build();
```

### Monitoring Data Changes

```php
// Create subscription
$subscription = $client->session->createSubscription(
    publishingInterval: 1000.0,  // 1 second
);

// Monitor a node
$nodeId = NodeId::numeric(2, 1001);
$subscription->createMonitoredItem(
    nodeId: $nodeId,
    samplingInterval: 500.0,
    callback: function ($value, $timestamp) {
        echo "Value changed: {$value} at {$timestamp}\n";
    }
);

// Process notifications
while (true) {
    $subscription->publishAsync();
    usleep(100000); // 100ms
}
```

## Documentation

- **[Getting Started](docs/getting-started.md)** - Installation and first steps
- **[ClientBuilder Guide](docs/client-builder.md)** - Configuration API reference
- **[Browsing](docs/browsing.md)** - Exploring the address space
- **[Reading & Writing](docs/reading-writing.md)** - Data operations
- **[Subscriptions](docs/subscriptions.md)** - Real-time data monitoring
- **[Security](docs/security.md)** - Certificates and authentication
- **[Performance](docs/performance.md)** - Optimization strategies
- **[API Reference](docs/api/)** - Complete API documentation
- **[Examples](examples/)** - 20+ working examples

## Requirements

- PHP 8.4 or higher
- ext-sockets - Socket communication
- OpenSSL support - For secure connections

## Supported Features

| Feature | Status |
|---------|--------|
| TCP Binary Protocol | ✅ Full |
| Secure Channel | ✅ Basic256Sha256, None |
| Session Management | ✅ Create, Activate, Close |
| Browse Services | ✅ Full with continuation |
| Read/Write Services | ✅ Multiple nodes |
| Subscriptions | ✅ Data change, Events |
| Method Calls | ✅ Full support |
| Historical Data | ✅ ReadRaw, ReadProcessed |
| Type System | ✅ Built-in + Custom structures |
| Certificate Auth | ✅ X.509 certificates |
| Username Auth | ✅ Username/password |
| Anonymous Auth | ✅ Full support |

## Architecture

```
┌─────────────────────────────────────────┐
│         ClientBuilder (Fluent API)      │
├─────────────────────────────────────────┤
│  ConnectedClient                        │
│  ├─ Session (Service Calls)             │
│  ├─ Browser (Address Space)             │
│  └─ Cache (Optional)                    │
├─────────────────────────────────────────┤
│  Core Protocol Layer                    │
│  ├─ SecureChannel (Encryption)          │
│  ├─ BinaryEncoder/Decoder               │
│  └─ TcpConnection                       │
└─────────────────────────────────────────┘
```

## Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Static analysis
composer analyse

# Code style
composer cs-check
composer cs-fix
```

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Security

For security vulnerabilities, please see [SECURITY.md](SECURITY.md).

## License

This project is licensed under the MIT License - see [LICENSE](LICENSE) file for details.

For complete attribution and licensing information, see [NOTICE](NOTICE).

## Credits

Developed by [TechDock](https://techdock.ch)

### Attribution

This library is a **PHP implementation** of OPC UA client functionality based on the architecture and design of the [OPC Foundation UA-.NETStandard](https://github.com/OPCFoundation/UA-.NETStandard) implementation. The original C#/.NET implementation provides the architectural foundation and design patterns for this PHP adaptation.

**Original Project:**
- **Repository**: [OPCFoundation/UA-.NETStandard](https://github.com/OPCFoundation/UA-.NETStandard)
- **License**: Dual-license (RCL for OPC Foundation members, GPL 2.0 for non-members)
- **Copyright**: OPC Foundation

**This PHP Implementation:**
- **Scope**: Client-only implementation (no server functionality)
- **Approach**: Independent implementation following the same architectural patterns
- **Protocol**: 100% wire-compatible with OPC UA binary protocol specification
- **Code**: Adapted to PHP 8.4+ idioms, type system, and modern language features
- **Size**: Approximately 35-40% of the .NET core stack code volume
- **License**: MIT License (as an independent implementation)

**Key Differences:**
- Client-only (UA-.NETStandard includes both client and server)
- Hand-written message classes (vs. code-generated from XML schemas)
- Focused on core client features (Browse, Read, Write, Subscribe, Call)
- PHP-native approach using readonly classes, union types, and match expressions

We are deeply grateful to the OPC Foundation and the UA-.NETStandard contributors for their excellent reference implementation that made this PHP client possible.

Special thanks to:
- The OPC Foundation for the OPC UA specification and reference implementations
- The UA-.NETStandard development team for their well-architected codebase
- All contributors to open-source OPC UA projects

See [NOTICE](NOTICE) for complete attribution and licensing details.

## Resources

- [OPC Foundation](https://opcfoundation.org/)
- [OPC UA Specification](https://reference.opcfoundation.org/)
- [UA-.NETStandard (Original Implementation)](https://github.com/OPCFoundation/UA-.NETStandard)
- [Unified Architecture](https://en.wikipedia.org/wiki/OPC_Unified_Architecture)

## Support

- **Issues**: [GitHub Issues](https://github.com/TECHDOCK-CH/php-opc-ua/issues)
- **Discussions**: [GitHub Discussions](https://github.com/TECHDOCK-CH/php-opc-ua/discussions)
- **Email**: info@techdock.ch
