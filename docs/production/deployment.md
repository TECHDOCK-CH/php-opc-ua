# Production Deployment Guide

Guidelines for deploying PHP OPC UA Client in production environments.

## Pre-Deployment Checklist

### Security
- [ ] Certificate validation enabled
- [ ] Strong authentication configured
- [ ] Credentials stored in environment variables
- [ ] Private keys have proper permissions (chmod 600)
- [ ] Security mode set to SignAndEncrypt
- [ ] Firewall rules configured

### Performance
- [ ] Caching enabled with appropriate TTL
- [ ] Auto-batching enabled for bulk operations
- [ ] Appropriate timeouts configured
- [ ] Connection pooling strategy defined

### Monitoring
- [ ] PSR-3 logger configured
- [ ] Error tracking enabled
- [ ] Performance metrics collected
- [ ] Health checks implemented

### Reliability
- [ ] Error handling comprehensive
- [ ] Retry logic implemented
- [ ] Connection recovery tested
- [ ] Resource cleanup verified

## Configuration

### Environment Variables

```bash
# .env file
OPCUA_ENDPOINT=opc.tcp://production-server:4840
OPCUA_USERNAME=production_user
OPCUA_PASSWORD=secure_password_here
OPCUA_CERT_PATH=/path/to/certs
OPCUA_CACHE_SIZE=5000
OPCUA_CACHE_TTL=600
```

### Production Configuration

```php
$client = ClientBuilder::create()
    ->endpoint(getenv('OPCUA_ENDPOINT'))
    ->application('Production App', 'urn:company:prod-app')
    ->withAutoDiscovery()
    ->preferSecurityMode(MessageSecurityMode::SignAndEncrypt)
    ->withUsernameAuth(
        getenv('OPCUA_USERNAME'),
        getenv('OPCUA_PASSWORD')
    )
    ->withCache(
        maxSize: (int)getenv('OPCUA_CACHE_SIZE'),
        ttl: (float)getenv('OPCUA_CACHE_TTL')
    )
    ->withAutoBatching()
    ->operationTimeout(60000)
    ->sessionTimeout(300000)
    ->build();
```

## Docker Deployment

### Dockerfile

```dockerfile
FROM php:8.4-cli

# Install extensions
RUN docker-php-ext-install sockets

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application
COPY . /app

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Run application
CMD ["php", "your-app.php"]
```

### Docker Compose

```yaml
version: '3.8'

services:
  opcua-client:
    build: .
    environment:
      - OPCUA_ENDPOINT=opc.tcp://opcua-server:4840
      - OPCUA_USERNAME=${OPCUA_USERNAME}
      - OPCUA_PASSWORD=${OPCUA_PASSWORD}
    volumes:
      - ./certs:/app/certs:ro
    depends_on:
      - opcua-server
    restart: unless-stopped
```

## Logging

### Configure PSR-3 Logger

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('opcua');
$logger->pushHandler(new RotatingFileHandler(
    '/var/log/opcua/client.log',
    30,  // 30 days retention
    Logger::INFO
));

// Use with client (when logger support is added)
// $client->setLogger($logger);
```

## Health Checks

### Liveness Check

```php
function healthCheck(ConnectedClient $client): bool {
    try {
        // Read a standard node
        $value = $client->session->read([NodeId::numeric(0, 2258)])[0];
        return $value->statusCode->isGood();
    } catch (Throwable) {
        return false;
    }
}
```

### Readiness Check

```php
function readinessCheck(ConnectedClient $client): array {
    return [
        'connected' => $client->isConnected(),
        'cache_hit_rate' => $client->getCacheStats()['hitRate'] ?? 0,
        'endpoint' => $client->client->getEndpointUrl(),
    ];
}
```

## Error Handling

### Retry Logic

```php
function withRetry(callable $operation, int $maxAttempts = 3): mixed {
    $attempt = 0;
    $lastException = null;

    while ($attempt < $maxAttempts) {
        try {
            return $operation();
        } catch (Throwable $e) {
            $lastException = $e;
            $attempt++;

            if ($attempt < $maxAttempts) {
                usleep(1000000 * $attempt);  // Exponential backoff
            }
        }
    }

    throw $lastException;
}

// Usage
$value = withRetry(fn() => $client->session->read([$nodeId])[0]);
```

### Connection Recovery

```php
function ensureConnected(ConnectedClient &$client, string $endpoint): void {
    if (!$client->isConnected()) {
        $client = ClientBuilder::create()
            ->endpoint($endpoint)
            // ... other config
            ->build();
    }
}
```

## Monitoring

### Metrics to Track

- Connection uptime
- Request latency
- Error rates
- Cache hit rates
- Memory usage
- Active subscriptions

### Example Metrics

```php
class Metrics {
    private int $requests = 0;
    private int $errors = 0;
    private float $totalLatency = 0;

    public function recordRequest(float $latency): void {
        $this->requests++;
        $this->totalLatency += $latency;
    }

    public function recordError(): void {
        $this->errors++;
    }

    public function getStats(): array {
        return [
            'requests' => $this->requests,
            'errors' => $this->errors,
            'error_rate' => $this->requests > 0
                ? $this->errors / $this->requests
                : 0,
            'avg_latency' => $this->requests > 0
                ? $this->totalLatency / $this->requests
                : 0,
        ];
    }
}
```

## Testing

### Integration Testing

See [Docker Compose example](../docker-compose.test.yml) for OPC-PLC test server setup.

```yaml
version: "3.8"

services:
  opc-plc:
    image: mcr.microsoft.com/iotedge/opc-plc:latest
    ports:
      - "4840:50000"
    command: >
      --autoaccept
      --unsecuretransport
```

## Scaling

### Horizontal Scaling

Each instance can maintain its own connection:

```php
// Instance 1 monitors nodes 1-1000
// Instance 2 monitors nodes 1001-2000
// etc.
```

### Connection Pooling

```php
class ConnectionPool {
    private array $connections = [];

    public function getConnection(string $endpoint): ConnectedClient {
        if (!isset($this->connections[$endpoint])) {
            $this->connections[$endpoint] = ClientBuilder::create()
                ->endpoint($endpoint)
                ->build();
        }

        return $this->connections[$endpoint];
    }
}
```

## Performance Tuning

### PHP Configuration

```ini
; php.ini
memory_limit = 512M
max_execution_time = 300
default_socket_timeout = 60
```

### OPC UA Tuning

```php
$client = ClientBuilder::create()
    ->endpoint($endpoint)
    ->withCache(maxSize: 10000, ttl: 900.0)  // Larger cache
    ->withAutoBatching()
    ->operationTimeout(120000)  // Longer timeout for slow servers
    ->sessionTimeout(600000)    // 10 minute session
    ->build();
```

## Troubleshooting

See [Troubleshooting Guide](troubleshooting.md) for common issues and solutions.

## See Also

- [Security Guide](../security.md)
- [Performance Guide](../performance.md)
- [Troubleshooting](troubleshooting.md)
- [Monitoring Guide](monitoring.md)
