# Getting Started with PHP OPC UA Client

This guide will help you install and configure the PHP OPC UA client library and connect to your first OPC UA server.

## Prerequisites

### System Requirements

- **PHP 8.4 or higher**
- **ext-sockets** - Socket extension for network communication
- **OpenSSL support** - For secure connections (typically included)
- **Composer** - For dependency management

### Verify PHP Installation

```bash
# Check PHP version
php -v
# Should show: PHP 8.4.x or higher

# Check for sockets extension
php -m | grep sockets
# Should show: sockets

# Check for OpenSSL
php -m | grep openssl
# Should show: openssl
```

## Installation

### Using Composer (Recommended)

```bash
composer require techdock/opcua
```

### Manual Installation

1. Download the latest release from GitHub
2. Extract to your project directory
3. Include the autoloader:

```php
require_once 'vendor/autoload.php';
```

## Your First Connection

### Minimal Example

Create a file `test-connection.php`:

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Types\NodeId;

// Connect to server
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->build();

echo "Connected: " . ($client->isConnected() ? 'YES' : 'NO') . "\n";

// Read server's current time (standard node)
$results = $client->session->read([NodeId::numeric(0, 2258)]);
$serverTime = $results[0] ?? null;

if ($serverTime !== null && $serverTime->isGood() && $serverTime->value !== null) {
    echo "Server time: {$serverTime->value}\n";
} else {
    $status = $serverTime?->statusCode?->toString() ?? 'Unknown';
    echo "Failed to read server time (status: {$status})\n";
}

// Clean up
$client->disconnect();
echo "Disconnected\n";
```

Run it:

```bash
php test-connection.php
```

Expected output:
```
Connected: YES
Server time: DateTime: 2024-11-08T20:30:45.123Z
Disconnected
```

## Configuration Options

### ClientBuilder API

The `ClientBuilder` provides a fluent API for configuration:

```php
$client = ClientBuilder::create()
    // Required: Server endpoint
    ->endpoint('opc.tcp://localhost:4840')

    // Optional: Application identity
    ->application('My App', 'urn:mycompany:myapp')

    // Optional: Authentication (defaults to anonymous)
    ->withAnonymousAuth()
    // or ->withUsernameAuth('user', 'password')

    // Optional: Performance features
    ->withCache(maxSize: 1000, ttl: 300.0)
    ->withAutoBatching()

    // Optional: Timeouts
    ->operationTimeout(30000)  // 30 seconds
    ->sessionTimeout(60000)    // 60 seconds

    // Build and connect
    ->build();
```

### Endpoint Discovery

Let the client automatically discover and select the best endpoint:

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withAutoDiscovery()
    ->withNoSecurity()  // Prefer no encryption (for testing)
    ->build();
```

### Authentication Methods

#### Anonymous (Default)

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withAnonymousAuth()  // Optional, this is the default
    ->build();
```

#### Username/Password

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withUsernameAuth('operator', 'password123')
    ->build();
```

#### Certificate-based (Advanced)

```php
use TechDock\OpcUa\Client\UserIdentity;

$clientCert = file_get_contents('/path/to/client-cert.pem');
$privateKey = file_get_contents('/path/to/client-key.pem');

$identity = UserIdentity::x509Certificate($clientCert, $privateKey);

$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withUserIdentity($identity)
    ->build();
```

## Basic Operations

### Reading Values

```php
use TechDock\OpcUa\Core\Types\NodeId;

// Read single value
$nodeId = NodeId::numeric(2, 1001);  // namespace 2, identifier 1001
$value = $client->session->read([$nodeId])[0];

echo "Value: {$value->value}\n";
echo "Timestamp: {$value->serverTimestamp}\n";
echo "Status: {$value->statusCode}\n";
```

`Session::read()` accepts an array of nodes and returns an array of `DataValue` results in the same order.

### Writing Values

```php
use TechDock\OpcUa\Core\Types\{NodeId, Variant, VariantType};

$nodeId = NodeId::numeric(2, 1001);
$newValue = new Variant(VariantType::Double, 123.45);

$client->session->write($nodeId, $newValue);
echo "Value written successfully\n";
```

### Browsing the Address Space

```php
use TechDock\OpcUa\Core\Types\NodeId;

// Browse the Objects folder (standard node)
$objectsFolder = NodeId::numeric(0, 85);
$references = $client->browser->browse($objectsFolder);

echo "Found " . count($references) . " child nodes:\n";

foreach ($references as $ref) {
    echo "- {$ref->displayName->text} ({$ref->nodeId})\n";
}
```

## Testing Your Connection

### Test Connection Without Full Setup

```php
// Quick connection test
$result = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->testConnection();

echo "Connection successful!\n";
echo "Found " . count($result['endpoints']) . " endpoints:\n";

foreach ($result['endpoints'] as $i => $endpoint) {
    $mode = $endpoint->securityMode->name;
    $policy = basename($endpoint->securityPolicyUri);
    echo "  " . ($i + 1) . ". $mode / $policy\n";
}

// Clean up
$result['session']->close();
$result['client']->disconnect();
```

## Common Connection Issues

### Issue: "Connection refused"

**Cause**: Server not running or wrong port

**Solution**:
```bash
# Verify server is running
# Check firewall rules
# Verify endpoint URL and port
```

### Issue: "No endpoint found"

**Cause**: Server doesn't support requested security policy

**Solution**:
```php
// List available endpoints first
$endpoints = OpcUaClient::getEndpoints('opc.tcp://localhost:4840');

foreach ($endpoints as $ep) {
    echo "Mode: {$ep->securityMode->name}\n";
    echo "Policy: {$ep->securityPolicyUri}\n";
}
```

### Issue: "Authentication failed"

**Cause**: Wrong credentials or unsupported auth method

**Solution**:
```php
// Check what auth methods the endpoint supports
$endpoint = $result['endpoints'][0];
foreach ($endpoint->userIdentityTokens as $token) {
    echo "Supported: {$token->tokenType->name}\n";
}
```

## Next Steps

Now that you have a working connection, explore:

- **[ClientBuilder Guide](client-builder.md)** - Complete configuration reference
- **[Browsing](browsing.md)** - Explore the server's address space
- **[Reading & Writing](reading-writing.md)** - Data operations
- **[Subscriptions](subscriptions.md)** - Monitor real-time data changes
- **[Security](security.md)** - Set up secure connections
- **[Examples](../examples/)** - 20+ working examples

## Need Help?

- Check the [examples/](../examples/) directory for working code
- Review [common issues](production/troubleshooting.md)
- Open an issue on GitHub
- Contact: info@techdock.ch
