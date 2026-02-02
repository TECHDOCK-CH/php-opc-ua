# OPC UA PHP Client - Examples

This directory contains comprehensive examples demonstrating all features of the PHP OPC UA client library.

## Quick Start Examples

### Basic Connection

```bash
php examples/simple_connection.php
```

Simple connection, session creation, and basic read/write operations.

### Client Builder (Recommended)

```bash
php examples/client_builder_demo.php
```

Modern fluent API for client configuration with performance features.

## Core Features

### Server Discovery

```bash
php examples/server_discovery.php
```

**Features:**

- FindServers service
- GetEndpoints retrieval
- EndpointSelector for automatic endpoint selection
- FindServersOnNetwork for network-wide discovery

### Browsing

```bash
php examples/browser_helper_demo.php
```

**Features:**

- Simple browsing with automatic continuation points
- Pattern matching by browse name (wildcards)
- Filtered browsing (objects, variables, methods)
- Recursive browsing with depth limits
- Circular reference protection

### Node Operations

```bash
php examples/node_operations.php
```

**Features:**

- TranslateBrowsePaths (navigate by string paths)
- RegisterNodes (optimize repeated access)
- UnregisterNodes (cleanup)
- Multiple path translation

## Subscriptions & Monitoring

### Basic Subscription

```bash
php examples/subscription_example.php
```

Simple subscription with data change monitoring.

### Advanced Subscription

```bash
php examples/subscription_advanced.php
```

**Features:**

- Multiple subscriptions
- Priority handling
- Publishing intervals
- Keep-alive configuration

### Event Monitoring

```bash
php examples/event_monitoring.php
```

**Features:**

- Event filtering with WHERE clauses
- Field selection (Time, Message, Severity)
- Complex filter expressions (AND, OR, comparisons)
- Alarm monitoring

### Monitored Item Filters

```bash
php examples/monitored_item_filters_demo.php
```

**Features:**

- DataChangeFilter (status, value, timestamp triggers)
- Deadband filtering (absolute, percent)
- AggregateFilter (avg, min, max, count, total)
- 50-90% notification reduction

## Performance Features

### Performance Optimization

```bash
php examples/stage2_performance_features.php
```

**Features:**

- Node caching (LRU with statistics)
- Server capability detection
- Automatic batch splitting
- Progress callbacks for large operations

### Large Address Space

```bash
php examples/browse_large_address_space.php
```

Efficient browsing of large address spaces with batching.

## Security

### Secure Connection

```bash
php examples/secure_connection_with_validation.php
```

**Features:**

- Certificate-based security
- Certificate validation
- Security policy selection
- Username authentication

## Advanced Connection Types

### Unix Socket Connection

```bash
php examples/unix_socket_connection.php
```

**Features:**

- Unix domain socket connections
- Local inter-process communication (IPC)
- Container-friendly deployments
- Enhanced security (no network exposure)
- Lower latency for local connections

**Test Server:**

```bash
# Terminal 1: Start test server
php examples/unix_socket_test_server.php

# Terminal 2: Run example
php examples/unix_socket_connection.php
```

## Debugging

### Debug Connection

```bash
php examples/debug_connection.php
```

Debug connection issues with detailed logging.

### Debug Session

```bash
php examples/debug_session.php
```

Debug session creation and activation.

### Debug Subscription

```bash
php examples/debug_subscription.php
```

Debug subscription and monitored item creation.

## Example Categories

### 🚀 Getting Started (Start Here)

1. `client_builder_demo.php` - Modern client setup
2. `simple_connection.php` - Basic connection
3. `browse_local.php` - Browse server address space

### 🔍 Discovery & Navigation

- `server_discovery.php` - Find servers and endpoints
- `browser_helper_demo.php` - Advanced browsing
- `node_operations.php` - Path translation and node registration

### 📊 Monitoring & Events

- `subscription_example.php` - Basic subscriptions
- `subscription_advanced.php` - Advanced subscription patterns
- `event_monitoring.php` - Event filtering
- `monitored_item_filters_demo.php` - All filter types

### ⚡ Performance

- `stage2_performance_features.php` - Caching and batching
- `browse_large_address_space.php` - Large datasets

### 🔒 Security

- `secure_connection_with_validation.php` - Certificate security

### 🔧 Advanced

- `unix_socket_connection.php` - Unix domain sockets
- `unix_socket_test_server.php` - Test socket server

### 🐛 Debugging

- `debug_connection.php` - Connection debugging
- `debug_session.php` - Session debugging
- `debug_subscription.php` - Subscription debugging

## Prerequisites

All examples require:

- PHP 8.4+
- Composer dependencies installed: `composer install`
- OPC UA server running (use a test server like Prosys OPC UA Simulation Server)

## Configuration

Most examples use default connection parameters:

- **Host:** `localhost`
- **Port:** `4840`
- **Endpoint:** `opc.tcp://localhost:4840`

Modify the configuration variables at the top of each example file to match your server.

## Running Examples

### With Default Server

```bash
php examples/example_name.php
```

### With Custom Server

Edit the example file and change:

```php
$host = 'your-server-hostname';
$port = 4840;
$endpointUrl = 'opc.tcp://your-server-hostname:4840';
```

## Testing Without a Server

Some examples can run without a live server:

- `client_builder_demo.php` - Has offline mode
- `server_discovery.php` - Can demonstrate API without server
- `unix_socket_connection.php` - Can demonstrate patterns

## Example Output

Examples provide detailed output including:

- Step-by-step operation descriptions
- Success/failure indicators (✓/✗)
- Performance metrics (time, cache hit rates)
- Data values and node information
- Error messages with troubleshooting hints

## Learning Path

**Beginner:**

1. Start with `client_builder_demo.php` - Learn modern client setup
2. Run `simple_connection.php` - Understand basic operations
3. Try `browser_helper_demo.php` - Learn address space navigation

**Intermediate:**

4. Explore `subscription_example.php` - Real-time monitoring
5. Use `monitored_item_filters_demo.php` - Advanced filtering
6. Try `stage2_performance_features.php` - Optimization

**Advanced:**

7. Study `event_monitoring.php` - Complex event filtering
8. Explore `unix_socket_connection.php` - Alternative transports
9. Review `secure_connection_with_validation.php` - Production security

## Troubleshooting

### "Failed to connect"

- Verify server is running
- Check host/port configuration
- Ensure firewall allows connection
- Try `server_discovery.php` to find available endpoints

### "No endpoints available"

- Server may not be running
- Check endpoint URL format
- Verify security policy compatibility

### "Permission denied" (Unix sockets)

- Check socket file permissions
- Ensure user has read/write access
- Verify socket path is correct

### "Operation timeout"

- Increase timeout in client configuration
- Check server responsiveness
- Verify network connectivity

## Additional Resources

- **Documentation:** See `/docs` directory
- **API Reference:** See source code PHPDoc comments
- **OPC UA Specification:** https://opcfoundation.org/
- **Test Servers:**
    - Prosys OPC UA Simulation Server: https://www.prosysopc.com/
    - UA-.NETStandard Reference Server: https://github.com/OPCFoundation/UA-.NETStandard

## Contributing Examples

When adding new examples:

1. Include comprehensive documentation comments
2. Use clear variable names and step-by-step sections
3. Provide error handling with helpful messages
4. Add example to this README
5. Test with real OPC UA server
6. Follow PSR-12 coding standards

## Support

For issues or questions:

- Check example comments for inline documentation
- Review `PROGRESS.md` for feature status
- See `COMPLETE_CLIENT_PORT_PLAN.md` for roadmap
- Open GitHub issue with example name and error message

---

**Total Examples:** 17 files demonstrating all client features
**Last Updated:** 2025-11-05
