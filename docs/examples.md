# Examples Index

This library includes 20+ working examples demonstrating all major features.

## Running Examples

```bash
cd examples
php example_name.php
```

**Note**: Most examples assume an OPC UA server at `opc.tcp://localhost:4840`. Use the open62541 demo server or modify the endpoint URL.

## Basic Examples

### `client_builder_demo.php`
Demonstrates the ClientBuilder fluent API for creating clients with various configurations.

**Features**:
- Minimal configuration
- Full configuration with caching
- Auto-discovery
- Connection testing
- Production-ready setup

### `browse_local.php`
Basic browsing of the address space starting from the Objects folder.

### `browse_demo_server.php`
Browse a demo server's structure with formatted output.

### `node_operations.php`
Read and write operations on nodes with type conversion.

## Advanced Browsing

### `browser_helper_demo.php`
Advanced browsing patterns including recursive browsing and filtering.

### `browse_large_address_space.php`
Techniques for efficiently browsing large address spaces with caching.

## Subscriptions

### `subscription_example.php`
Basic subscription setup for monitoring data changes.

**Learn**:
- Creating subscriptions
- Adding monitored items
- Processing notifications
- Clean shutdown

### `subscription_advanced.php`
Advanced subscription features including filters and batching.

**Learn**:
- Data change filters (deadband)
- Queue sizes
- Multiple monitored items
- Error handling

### `event_monitoring.php`
Monitor alarms and events with filtering.

**Learn**:
- Event filters
- Alarm conditions
- Event fields
- Complex filtering

### `monitored_item_filters_demo.php`
Demonstrates all types of monitored item filters.

## Security

### `secure_connection_with_validation.php`
Secure connections with certificate validation.

**Learn**:
- Certificate validation
- Trust stores
- Security policies
- X.509 authentication

### `auto_policy_comparison.php`
Compare security policies and select the best one.

## Discovery

### `server_discovery.php`
Discover OPC UA servers on the network.

**Learn**:
- FindServers service
- FindServersOnNetwork service
- Endpoint discovery

## Performance

### `stage2_performance_features.php`
Performance optimizations for production use.

**Learn**:
- Caching
- Auto-batching
- Server capability detection
- Bulk operations

## Advanced Features

### `alarm_condition_server_demo.php`
Working with alarm conditions and acknowledgments.

### `debug_connection.php`
Low-level connection debugging.

### `debug_hex.php`
Binary protocol hex dump for troubleshooting.

### `debug_response.php`
Response message debugging.

## Special Transports

### `unix_socket_connection.php`
Connect via Unix domain sockets (local connections).

## Running All Examples

```bash
# Install dependencies first
composer install

# Run each example
for f in examples/*.php; do
    echo "Running $f..."
    php "$f"
    echo "---"
done
```

## Example Template

Use this template for your own applications:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Types\NodeId;

try {
    // 1. Connect
    $client = ClientBuilder::create()
        ->endpoint('opc.tcp://localhost:4840')
        ->withAnonymousAuth()
        ->build();

    echo "Connected\n";

    // 2. Your code here
    $value = $client->session->read(NodeId::numeric(0, 2258));
    echo "Server time: {$value->value}\n";

    // 3. Clean up
    $client->disconnect();
    echo "Disconnected\n";

} catch (Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
```

## See Also

- [Getting Started](getting-started.md)
- [API Reference](api/)
- [GitHub Repository](https://github.com/TECHDOCK-CH/php-opc-ua/tree/main/examples)
