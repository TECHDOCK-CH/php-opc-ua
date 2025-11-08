# ClientBuilder Guide

The `ClientBuilder` provides a fluent, type-safe API for creating and configuring OPC UA clients with sensible defaults and optional performance optimizations.

## Philosophy

- **Sensible defaults** - Works out of the box for common scenarios
- **Progressive disclosure** - Simple by default, powerful when needed
- **Type safety** - Compile-time checking with PHP 8.4
- **Fail fast** - Clear errors at configuration time

## Basic Usage

```php
use TechDock\OpcUa\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->build();

// Client is now connected with a session
echo "Connected: " . ($client->isConnected() ? 'YES' : 'NO') . "\n";
```

## Complete API Reference

### `create(): self`

Creates a new builder instance.

```php
$builder = ClientBuilder::create();
```

### `endpoint(string $url): self`

**Required.** Sets the OPC UA server endpoint URL.

```php
$builder->endpoint('opc.tcp://192.168.1.100:4840');
```

**Format**: `opc.tcp://host:port[/path]`

### `withEndpoint(EndpointDescription $endpoint): self`

Sets a specific endpoint description (for advanced scenarios).

```php
use TechDock\OpcUa\Core\Messages\EndpointDescription;

// Get endpoints manually
$endpoints = OpcUaClient::getEndpoints($url);
$selected = $endpoints[0];

$builder->withEndpoint($selected);
```

### `application(string $name, ?string $uri = null, ApplicationType $type = ApplicationType::Client): self`

Sets the application description for identification.

```php
// Minimal
$builder->application('My SCADA System');

// With custom URI
$builder->application('My SCADA', 'urn:company:scada');

// With type
use TechDock\OpcUa\Core\Types\ApplicationType;

$builder->application('My Gateway', 'urn:co:gateway', ApplicationType::ClientAndServer);
```

**Default**: `'PHP OPC UA Client'` with auto-generated URI

## Authentication

### `withAnonymousAuth(): self`

Uses anonymous authentication (no credentials).

```php
$builder->withAnonymousAuth();  // Default if nothing specified
```

### `withUsernameAuth(string $username, string $password): self`

Uses username/password authentication.

```php
$builder->withUsernameAuth('operator', 'secret123');
```

**Security**: Use environment variables for credentials in production!

```php
$builder->withUsernameAuth(
    getenv('OPCUA_USERNAME'),
    getenv('OPCUA_PASSWORD')
);
```

### `withUserIdentity(UserIdentity $identity): self`

Uses a custom user identity (for certificate-based auth, etc).

```php
use TechDock\OpcUa\Client\UserIdentity;

// Certificate-based
$cert = file_get_contents('/path/to/cert.pem');
$key = file_get_contents('/path/to/key.pem');
$identity = UserIdentity::x509Certificate($cert, $key);

$builder->withUserIdentity($identity);
```

## Endpoint Discovery

### `withAutoDiscovery(): self`

Automatically fetches and selects the best endpoint.

```php
$builder->withAutoDiscovery();
```

**Behavior**:
1. Calls `GetEndpoints` service
2. Filters by security preferences
3. Selects highest security match
4. Falls back to lower security if preferred unavailable

### `preferSecurityMode(MessageSecurityMode $mode): self`

Prefers a specific security mode during auto-discovery.

```php
use TechDock\OpcUa\Core\Security\MessageSecurityMode;

// Prefer encrypted connections
$builder->preferSecurityMode(MessageSecurityMode::SignAndEncrypt);

// Prefer signing only
$builder->preferSecurityMode(MessageSecurityMode::Sign);
```

### `preferSecurityPolicy(SecurityPolicy $policy): self`

Prefers a specific security policy during auto-discovery.

```php
use TechDock\OpcUa\Core\Security\SecurityPolicy;

$builder->preferSecurityPolicy(SecurityPolicy::Basic256Sha256);
```

### `withNoSecurity(): self`

Convenience method: prefers no security (for testing/development).

```php
$builder->withNoSecurity();  // Equivalent to ->preferSecurityMode(MessageSecurityMode::None)
```

**Warning**: Never use in production!

## Performance Optimizations

### `withCache(int $maxSize = 1000, float $ttl = 300.0): self`

Enables LRU caching for node metadata.

```php
// Default: 1000 entries, 5 minute TTL
$builder->withCache();

// Custom size and TTL
$builder->withCache(maxSize: 5000, ttl: 600.0);  // 5000 entries, 10 min
```

**Benefits**:
- Reduces repeated Browse calls
- Faster attribute lookups
- Lower network traffic

**When to use**:
- Large address spaces
- Repeated browsing
- Stable server structure

### `withCustomCache(INodeCache $cache): self`

Uses a custom cache implementation.

```php
use TechDock\OpcUa\Client\Cache\INodeCache;

class RedisNodeCache implements INodeCache {
    // ... custom implementation
}

$builder->withCustomCache(new RedisNodeCache());
```

### `withAutoBatching(): self`

Enables automatic batch splitting for large operations.

```php
$builder->withAutoBatching();
```

**Behavior**:
1. Detects server capabilities (max nodes per operation)
2. Automatically splits large read/write/browse requests
3. Combines results transparently

**When to use**:
- Reading/writing many nodes at once
- Servers with operation limits
- Large browse operations

## Timeouts

### `operationTimeout(int $timeout): self`

Sets the timeout for individual operations (milliseconds).

```php
$builder->operationTimeout(30000);  // 30 seconds (default)
```

**Applies to**: Read, Write, Browse, Call, etc.

### `sessionTimeout(int $timeout): self`

Sets the session timeout (milliseconds).

```php
$builder->sessionTimeout(60000);  // 60 seconds (default)
```

**Note**: Actual timeout negotiated with server (may be shorter).

## Building

### `build(): ConnectedClient`

Builds and connects the client, creates a session, and returns a `ConnectedClient`.

```php
$client = $builder->build();

// Use the client
$client->session->read($nodeId);
$client->browser->browse($folderId);

// Check status
if ($client->isConnected()) {
    // ...
}

// Clean up
$client->disconnect();
```

**Throws**:
- `RuntimeException` if endpoint URL not set
- `RuntimeException` if connection fails
- `RuntimeException` if session creation fails

### `testConnection(): array`

Tests the connection without full setup (useful for validation).

```php
$result = $builder->testConnection();

// Returns:
// [
//   'client' => OpcUaClient,
//   'session' => Session,
//   'endpoints' => EndpointDescription[]
// ]

echo "Connection OK! Found " . count($result['endpoints']) . " endpoints\n";

// Must clean up manually
$result['session']->close();
$result['client']->disconnect();
```

## Complete Examples

### Development Configuration

Simple, insecure setup for local testing:

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withNoSecurity()
    ->withAnonymousAuth()
    ->build();
```

### Production Configuration

Secure, optimized setup for production:

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://prod-server:4840')
    ->application('Production SCADA', 'urn:company:scada')
    ->withAutoDiscovery()
    ->preferSecurityMode(MessageSecurityMode::SignAndEncrypt)
    ->withUsernameAuth(
        getenv('OPCUA_USERNAME'),
        getenv('OPCUA_PASSWORD')
    )
    ->withCache(maxSize: 5000, ttl: 600.0)
    ->withAutoBatching()
    ->operationTimeout(60000)
    ->sessionTimeout(300000)
    ->build();
```

### High-Performance Configuration

Optimized for large address spaces and bulk operations:

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://server:4840')
    ->withCache(maxSize: 10000, ttl: 900.0)  // Large cache, 15 min
    ->withAutoBatching()                      // Auto-split operations
    ->operationTimeout(120000)                // 2 minute timeout
    ->build();
```

### Discovery and Selection

Automatically discover and select endpoints:

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://server:4840')
    ->withAutoDiscovery()
    ->preferSecurityMode(MessageSecurityMode::SignAndEncrypt)
    ->build();

// Or manually:
$endpoints = OpcUaClient::getEndpoints('opc.tcp://server:4840');

foreach ($endpoints as $i => $ep) {
    echo "$i: {$ep->securityMode->name} / " .
         basename($ep->securityPolicyUri) . "\n";
}

$selected = $endpoints[2];  // Choose one

$client = ClientBuilder::create()
    ->withEndpoint($selected)
    ->build();
```

## ConnectedClient API

The `build()` method returns a `ConnectedClient` with these properties:

```php
$connected = $builder->build();

// Public properties
$connected->client;   // OpcUaClient - Low-level client
$connected->session;  // Session - Service operations
$connected->browser;  // Browser - Address space navigation
$connected->cache;    // INodeCache|null - Cache if enabled

// Methods
$connected->disconnect();                // Close session and connection
$connected->isConnected(): bool;         // Check connection status
$connected->getServerCapabilities();     // Get server capabilities
$connected->getCacheStats(): ?array;     // Get cache statistics
$connected->clearCache(): void;          // Clear cache
```

## Error Handling

```php
use TechDock\OpcUa\Exceptions\StatusCodeException;
use RuntimeException;

try {
    $client = ClientBuilder::create()
        ->endpoint('opc.tcp://server:4840')
        ->build();

} catch (RuntimeException $e) {
    // Connection or configuration error
    echo "Failed to connect: {$e->getMessage()}\n";

} catch (StatusCodeException $e) {
    // OPC UA status code error
    echo "OPC UA error: {$e->getStatusCode()}\n";
}
```

## Best Practices

### 1. Use Environment Variables for Configuration

```php
$client = ClientBuilder::create()
    ->endpoint(getenv('OPCUA_ENDPOINT'))
    ->withUsernameAuth(
        getenv('OPCUA_USERNAME'),
        getenv('OPCUA_PASSWORD')
    )
    ->build();
```

### 2. Always Clean Up Resources

```php
$client = null;
try {
    $client = ClientBuilder::create()
        ->endpoint($url)
        ->build();

    // ... use client ...

} finally {
    $client?->disconnect();
}
```

### 3. Test Endpoints Before Building

```php
// Test first
$result = ClientBuilder::create()
    ->endpoint($url)
    ->testConnection();

echo "Server supports " . count($result['endpoints']) . " endpoints\n";

// Clean up test
$result['session']->close();
$result['client']->disconnect();

// Now build for real
$client = ClientBuilder::create()
    ->endpoint($url)
    ->build();
```

### 4. Use Auto-Discovery in Production

```php
// Let the client choose the best endpoint
$client = ClientBuilder::create()
    ->endpoint($url)
    ->withAutoDiscovery()
    ->preferSecurityMode(MessageSecurityMode::SignAndEncrypt)
    ->build();
```

### 5. Enable Performance Features

```php
// For large-scale operations
$client = ClientBuilder::create()
    ->endpoint($url)
    ->withCache()           // Enable caching
    ->withAutoBatching()    // Enable auto-batching
    ->build();
```

## See Also

- [Getting Started](getting-started.md) - Installation and first steps
- [Security Guide](security.md) - Certificate setup and authentication
- [Performance Guide](performance.md) - Optimization strategies
- [API Reference](api/client.md) - Complete API documentation
