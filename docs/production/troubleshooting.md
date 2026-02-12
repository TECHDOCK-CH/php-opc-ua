# Troubleshooting Guide

Common issues and their solutions.

## Connection Issues

### "Connection refused"

**Symptom**: Cannot connect to server

**Causes**:
- Server not running
- Wrong port
- Firewall blocking

**Solutions**:
```bash
# Check if server is running
telnet localhost 4840

# Check with netstat
netstat -an | grep 4840

# Test with different endpoint
php examples/debug_connection.php
```

### "No endpoints found"

**Symptom**: GetEndpoints returns empty array

**Solution**:
```php
// Try with explicit None security
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withNoSecurity()
    ->build();
```

### "Decode failed" or large response errors

**Symptom**: Errors while decoding large reads/browse responses

**Causes**:
- Server returns chunked responses that exceed negotiated limits
- Client request exceeds server's max message size or chunk count

**Solutions**:
1. Reduce read/browse batch size (fewer nodes per request)
2. Increase server-side OPC UA limits (MaxMessageSize/MaxChunkCount)
3. Check that client and server agree on endpoint security settings

### "Certificate validation failed"

**Symptom**: SSL/TLS errors

**Solutions**:
1. Check certificate paths
2. Verify certificate validity
3. Check trust store configuration
4. For testing: disable validation (NOT for production!)

## Authentication Issues

### "User access denied"

**Symptom**: Session activation fails

**Solutions**:
```php
// List available auth methods
$endpoints = OpcUaClient::getEndpoints($url);
foreach ($endpoints[0]->userIdentityTokens as $token) {
    echo "Supported: {$token->tokenType->name}\n";
}

// Try anonymous first
$client = ClientBuilder::create()
    ->endpoint($url)
    ->withAnonymousAuth()
    ->build();
```

## Performance Issues

### "Slow browse operations"

**Solutions**:
```php
// Enable caching
$client = ClientBuilder::create()
    ->endpoint($url)
    ->withCache(maxSize: 5000, ttl: 600.0)
    ->build();

// Use filters
$options = new BrowserOptions(
    nodeClassMask: NodeClass::Variable->value,
    maxReferencesPerNode: 100,
);
```

### "Timeout errors"

**Solutions**:
```php
// Increase timeouts
$client = ClientBuilder::create()
    ->endpoint($url)
    ->operationTimeout(60000)    // 60s
    ->sessionTimeout(300000)     // 5 min
    ->build();
```

## Memory Issues

### "Out of memory"

**Solutions**:
```php
// Clear cache periodically
$client->clearCache();

// Use smaller cache
$client = ClientBuilder::create()
    ->endpoint($url)
    ->withCache(maxSize: 1000, ttl: 300.0)
    ->build();

// Process in batches
foreach (array_chunk($nodeIds, 100) as $batch) {
    $values = $client->session->readMultiple($batch);
    process($values);
    unset($values);
}
```

## Data Issues

### "Bad status code"

**Check specific codes**:
```php
$value = $client->session->read([$nodeId])[0];

if ($value->statusCode->value === 0x80340000) {
    echo "Node does not exist\n";
} elseif ($value->statusCode->value === 0x80360000) {
    echo "Node not readable\n";
} else {
    echo "Error: {$value->statusCode}\n";
}
```

### "Type conversion errors"

**Solution**:
```php
// Check node data type first
$dataType = $client->session->readAttribute(
    $nodeId,
    AttributeId::DataType
);

echo "Node data type: {$dataType->value}\n";

// Use correct variant type
$value = new Variant(VariantType::Double, 123.45);
```

## Subscription Issues

### "No notifications received"

**Checks**:
```php
// Verify subscription is created
echo "Subscription ID: {$subscription->getSubscriptionId()}\n";

// Check monitored item status
echo "Monitored item ID: {$monitoredItem->getMonitoredItemId()}\n";

// Ensure publishAsync is being called
while (true) {
    $subscription->publishAsync();
    usleep(100000);
}
```

## Debugging Tools

### Enable Debug Logging

```php
// Use debug examples
php examples/debug_connection.php
php examples/debug_hex.php
php examples/debug_response.php
```

### Check Server Logs

Most OPC UA servers provide diagnostic logs.

### Network Analysis

```bash
# Capture OPC UA traffic
tcpdump -i any -w opcua.pcap port 4840

# Analyze with Wireshark
wireshark opcua.pcap
```

## Getting Help

1. Check [examples/](../../examples/) for working code
2. Review [documentation](../)
3. Search [GitHub issues](https://github.com/TECHDOCK-CH/php-opc-ua/issues)
4. Open new issue with:
   - PHP version
   - Library version
   - Minimal reproducible example
   - Full error message

## See Also

- [Deployment Guide](deployment.md)
- [Security Guide](../security.md)
- [Examples](../../examples/)
