# Documentation Index

Complete documentation for the PHP OPC UA Client library.

## Getting Started

Start here if you're new to the library:

1. **[Getting Started](getting-started.md)** - Installation, first connection, basic operations
2. **[Client Builder](client-builder.md)** - Complete guide to the fluent configuration API
3. **[Examples](examples.md)** - Index of 20+ working examples

## Core Features

### Basic Operations
- **[Browsing](browsing.md)** - Navigate the OPC UA address space
- **[Reading & Writing](reading-writing.md)** - Read and write node values
- **[Subscriptions](subscriptions.md)** - Monitor real-time data changes

### Advanced Topics
- **[Security](security.md)** - Certificates, authentication, encryption
- **[Performance](performance.md)** - Caching, batching, optimization strategies
- **Error Handling** - See examples for patterns

## API Reference

Detailed API documentation:

- **[OpcUaClient API](api/client.md)** - Low-level client
- **[ConnectedClient API](api/client.md)** - High-level wrapper
- **[Session API](api/session.md)** - Service operations
- **[Browser API](api/browser.md)** - Address space navigation
- **[Types Reference](api/types.md)** - OPC UA data types

## Architecture

Understanding the internals:

- **[Architecture Overview](architecture/overview.md)** - High-level design
- **[Transport Layer](architecture/transport.md)** - TCP communication
- **[Security Architecture](architecture/security.md)** - Encryption and authentication
- **[Encoding System](architecture/encoding.md)** - Binary protocol
- **[Extensibility](architecture/extensibility.md)** - Custom types and caching

## Production Deployment

Guides for production use:

- **[Deployment Guide](production/deployment.md)** - Docker, configuration, monitoring
- **[Troubleshooting](production/troubleshooting.md)** - Common issues and solutions
- **[Monitoring Guide](production/monitoring.md)** - Metrics and health checks
- **[Performance Tuning](production/performance-tuning.md)** - Optimization strategies

## Contributing

Help improve the library:

- **[Contributing Guide](../CONTRIBUTING.md)** - How to contribute
- **[Security Policy](../SECURITY.md)** - Security practices and reporting
- **[Code of Conduct](../CONTRIBUTING.md#code-of-conduct)** - Community standards

## Additional Resources

- **[Changelog](../CHANGELOG.md)** - Version history
- **[License](../LICENSE)** - MIT License
- **[Notice](../NOTICE)** - Attribution and credits

## Quick Links

### By Role

**Application Developer**:
1. [Getting Started](getting-started.md)
2. [Client Builder](client-builder.md)
3. [Reading & Writing](reading-writing.md)
4. [Examples](examples.md)

**DevOps Engineer**:
1. [Deployment Guide](production/deployment.md)
2. [Security Guide](security.md)
3. [Monitoring Guide](production/monitoring.md)
4. [Troubleshooting](production/troubleshooting.md)

**Library Contributor**:
1. [Architecture Overview](architecture/overview.md)
2. [Contributing Guide](../CONTRIBUTING.md)
3. [Testing Strategy](architecture/overview.md#testing-strategy)

### By Task

**Connecting to a server**:
- [Getting Started](getting-started.md#your-first-connection)
- [Client Builder](client-builder.md)

**Securing connections**:
- [Security Guide](security.md)
- [Certificate Example](../examples/secure_connection_with_validation.php)

**Reading data**:
- [Reading & Writing](reading-writing.md#reading-values)
- [Node Operations Example](../examples/node_operations.php)

**Monitoring changes**:
- [Subscriptions Guide](subscriptions.md)
- [Subscription Example](../examples/subscription_example.php)

**Browsing address space**:
- [Browsing Guide](browsing.md)
- [Browser Example](../examples/browser_helper_demo.php)

**Optimizing performance**:
- [Performance Guide](performance.md)
- [Stage 2 Features Example](../examples/stage2_performance_features.php)

**Troubleshooting issues**:
- [Troubleshooting Guide](production/troubleshooting.md)
- [Debug Examples](../examples/debug_connection.php)

## External Resources

### OPC UA Specification
- [OPC Foundation](https://opcfoundation.org/)
- [UA Specification](https://reference.opcfoundation.org/)
- [UA Part 6: Mappings](https://reference.opcfoundation.org/Core/Part6/) - Binary protocol

### OPC UA Servers for Testing
- [open62541](https://www.open62541.org/) - Open-source C implementation
- [Prosys OPC UA Simulation Server](https://www.prosysopc.com/products/opc-ua-simulation-server/) - Free simulation server
- [OPC PLC Simulator](https://github.com/Azure/iot-edge-opc-plc) - Microsoft's test server (used in CI)

### Related Projects
- [node-opcua](https://github.com/node-opcua/node-opcua) - Node.js implementation
- [python-opcua](https://github.com/FreeOpcUa/python-opcua) - Python implementation
- [OPC UA .NET Standard](https://github.com/OPCFoundation/UA-.NETStandard) - Official .NET implementation

## Need Help?

1. **Check the documentation** - Most questions are answered here
2. **Review examples** - 20+ working examples in [examples/](../examples/)
3. **Search issues** - [GitHub Issues](https://github.com/TECHDOCK-CH/php-opc-ua/issues)
4. **Ask a question** - [GitHub Discussions](https://github.com/TECHDOCK-CH/php-opc-ua/discussions)
5. **Report a bug** - [Bug Report Template](../.github/ISSUE_TEMPLATE/bug_report.md)
6. **Request a feature** - [Feature Request Template](../.github/ISSUE_TEMPLATE/feature_request.md)

## Documentation Feedback

Found an error in the documentation? Please [open an issue](https://github.com/TECHDOCK-CH/php-opc-ua/issues/new).

Want to improve the documentation? See [Contributing Guide](../CONTRIBUTING.md).
