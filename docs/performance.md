# Performance Optimization

Techniques and best practices for optimizing PHP OPC UA client performance.

## Caching

### Enable Node Caching

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withCache(maxSize: 5000, ttl: 600.0)  // 5000 entries, 10 min TTL
    ->build();
```

**Benefits**:
- Reduces repeated Browse calls
- Faster attribute lookups
- Lower network traffic

**When to use**:
- Large address spaces
- Repeated browsing operations
- Stable server structures

### Custom Cache Implementation

```php
use TechDock\OpcUa\Client\Cache\INodeCache;

class RedisNodeCache implements INodeCache {
    public function get(string $nodeId): ?NodeCacheEntry { /* ... */ }
    public function set(string $nodeId, NodeCacheEntry $entry): void { /* ... */ }
    public function has(string $nodeId): bool { /* ... */ }
    public function clear(): void { /* ... */ }
    public function getStats(): array { /* ... */ }
}

$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withCustomCache(new RedisNodeCache())
    ->build();
```

## Auto-Batching

### Enable Automatic Batching

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withAutoBatching()
    ->build();
```

**Benefits**:
- Automatically detects server limits
- Splits large operations into optimal batches
- Transparent to application code

### Manual Batching

```php
function readInBatches(Session $session, array $nodeIds, int $batchSize = 100): array {
    $results = [];
    foreach (array_chunk($nodeIds, $batchSize) as $batch) {
        $batchResults = $session->readMultiple($batch);
        $results = array_merge($results, $batchResults);
    }
    return $results;
}
```

## Connection Optimization

### Set Appropriate Timeouts

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->operationTimeout(30000)   // 30s per operation
    ->sessionTimeout(300000)    // 5 min session
    ->build();
```

### Reuse Connections

```php
// ❌ Bad: Create new client for each operation
function readValue($nodeId) {
    $client = ClientBuilder::create()->endpoint($url)->build();
    $value = $client->session->read([$nodeId])[0];
    $client->disconnect();
    return $value;
}

// ✅ Good: Reuse client
$client = ClientBuilder::create()->endpoint($url)->build();

function readValue($client, $nodeId) {
    return $client->session->read([$nodeId])[0];
}
```

## Bulk Operations

### Batch Reads

```php
// ❌ Slow: Individual reads
foreach ($nodeIds as $nodeId) {
    $values[] = $client->session->read([$nodeId])[0];
}

// ✅ Fast: Single batch read
$values = $client->session->readMultiple($nodeIds);
```

### Batch Writes

```php
// ❌ Slow: Individual writes
foreach ($writes as $write) {
    $client->session->write($write['nodeId'], $write['value']);
}

// ✅ Fast: Single batch write
$results = $client->session->writeMultiple($writes);
```

## Subscription Optimization

### Appropriate Intervals

```php
// Balance responsiveness vs load
$subscription = $client->session->createSubscription(
    publishingInterval: 1000.0,    // How often to receive notifications
);

$monitoredItem = $subscription->createMonitoredItem(
    nodeId: $nodeId,
    samplingInterval: 500.0,       // How often to sample
    callback: $callback
);
```

### Deadband Filters

Reduce unnecessary notifications:

```php
use TechDock\OpcUa\Core\Types\{DataChangeFilter, DataChangeTrigger, DeadbandType};

$filter = new DataChangeFilter(
    trigger: DataChangeTrigger::StatusValue,
    deadbandType: DeadbandType::Absolute,
    deadbandValue: 0.1,  // Only notify if change > 0.1
);

$subscription->createMonitoredItem(
    nodeId: $nodeId,
    filter: $filter,
    callback: $callback
);
```

## Memory Management

### Clear Caches Periodically

```php
// Clear cache when structure changes
$client->clearCache();
```

### Unsubscribe When Done

```php
$subscription->delete();
$client->disconnect();
```

## Profiling

### Measure Performance

```php
$start = microtime(true);

$values = $client->session->readMultiple($nodeIds);

$elapsed = microtime(true) - $start;
echo "Read " . count($nodeIds) . " nodes in " . ($elapsed * 1000) . "ms\n";
```

### Cache Statistics

```php
$stats = $client->getCacheStats();
echo "Cache hit rate: " . ($stats['hitRate'] * 100) . "%\n";
echo "Total hits: {$stats['hits']}\n";
echo "Total misses: {$stats['misses']}\n";
```

## Benchmarks

Typical performance on modern hardware:

| Operation | Without Optimization | With Optimization |
|-----------|---------------------|-------------------|
| Read 1000 nodes | ~5000ms | ~500ms |
| Browse 100 folders | ~3000ms | ~300ms (cached) |
| Subscribe 100 items | ~2000ms | ~200ms (batched) |

## See Also

- [Stage 2 Performance Example](../examples/stage2_performance_features.php)
- [Client Builder](client-builder.md)
