# Reading and Writing Data

Learn how to read and write values in the OPC UA address space using the Session API.

## Quick Start

```php
use TechDock\OpcUa\Core\Types\{NodeId, Variant, VariantType};

// Read a value
$nodeId = NodeId::numeric(2, 1001);
$dataValue = $client->session->read([$nodeId])[0];
echo "Value: {$dataValue->value}\n";

// Write a value
$newValue = new Variant(VariantType::Double, 123.45);
$client->session->write($nodeId, $newValue);
```

## Reading Values

### Single Node Read

```php
use TechDock\OpcUa\Core\Types\{NodeId, TimestampsToReturn};

$nodeId = NodeId::numeric(2, 1001);

// Read with both timestamps (default)
$dataValue = $client->session->read([$nodeId])[0];

// Read with specific timestamp preference
$dataValue = $client->session->read([$nodeId], 0.0, TimestampsToReturn::Server)[0];
$dataValue = $client->session->read([$nodeId], 0.0, TimestampsToReturn::Source)[0];
$dataValue = $client->session->read([$nodeId], 0.0, TimestampsToReturn::Neither)[0];

// Access the result
echo "Value: {$dataValue->value}\n";
echo "Server timestamp: {$dataValue->serverTimestamp}\n";
echo "Source timestamp: {$dataValue->sourceTimestamp}\n";
echo "Status: {$dataValue->statusCode}\n";
```

### Multiple Node Read

More efficient than reading one at a time:

```php
$nodeIds = [
    NodeId::numeric(2, 1001),
    NodeId::numeric(2, 1002),
    NodeId::numeric(2, 1003),
];

$results = $client->session->readMultiple($nodeIds);

foreach ($results as $i => $dataValue) {
    echo "Node {$nodeIds[$i]}: {$dataValue->value}\n";

    if (!$dataValue->statusCode->isGood()) {
        echo "  Error: {$dataValue->statusCode}\n";
    }
}
```

### Reading Attributes

Read specific node attributes (not just values):

```php
use TechDock\OpcUa\Core\Types\AttributeId;

// Read DisplayName
$name = $client->session->readAttribute(
    $nodeId,
    AttributeId::DisplayName
);
echo "Display name: {$name->value->text}\n";

// Read BrowseName
$browseName = $client->session->readAttribute(
    $nodeId,
    AttributeId::BrowseName
);

// Read DataType
$dataType = $client->session->readAttribute(
    $nodeId,
    AttributeId::DataType
);

// Read AccessLevel
$accessLevel = $client->session->readAttribute(
    $nodeId,
    AttributeId::AccessLevel
);

// Check if writable
$isWritable = ($accessLevel->value & 0x02) !== 0;
```

## Writing Values

### Single Node Write

```php
use TechDock\OpcUa\Core\Types\{Variant, VariantType};

$nodeId = NodeId::numeric(2, 1001);

// Write a double
$value = new Variant(VariantType::Double, 123.45);
$client->session->write($nodeId, $value);

// Write an integer
$value = new Variant(VariantType::Int32, 42);
$client->session->write($nodeId, $value);

// Write a string
$value = new Variant(VariantType::String, "Hello OPC UA");
$client->session->write($nodeId, $value);

// Write a boolean
$value = new Variant(VariantType::Boolean, true);
$client->session->write($nodeId, $value);
```

### Multiple Node Write

```php
$writes = [
    [
        'nodeId' => NodeId::numeric(2, 1001),
        'value' => new Variant(VariantType::Double, 100.0),
    ],
    [
        'nodeId' => NodeId::numeric(2, 1002),
        'value' => new Variant(VariantType::Int32, 50),
    ],
    [
        'nodeId' => NodeId::numeric(2, 1003),
        'value' => new Variant(VariantType::String, "Updated"),
    ],
];

$results = $client->session->writeMultiple($writes);

foreach ($results as $i => $statusCode) {
    if ($statusCode->isGood()) {
        echo "Write {$i}: Success\n";
    } else {
        echo "Write {$i}: Failed - {$statusCode}\n";
    }
}
```

## Data Types

### Primitive Types

```php
// Boolean
new Variant(VariantType::Boolean, true);

// Integers
new Variant(VariantType::Byte, 255);       // 8-bit unsigned
new Variant(VariantType::SByte, -128);     // 8-bit signed
new Variant(VariantType::Int16, -32768);   // 16-bit signed
new Variant(VariantType::UInt16, 65535);   // 16-bit unsigned
new Variant(VariantType::Int32, -2147483648);  // 32-bit signed
new Variant(VariantType::UInt32, 4294967295);  // 32-bit unsigned
new Variant(VariantType::Int64, PHP_INT_MIN);  // 64-bit signed
new Variant(VariantType::UInt64, PHP_INT_MAX); // 64-bit unsigned

// Floating point
new Variant(VariantType::Float, 3.14);     // 32-bit
new Variant(VariantType::Double, 3.14159); // 64-bit

// String
new Variant(VariantType::String, "Hello");

// DateTime
new Variant(VariantType::DateTime, new DateTime('now'));
```

### Complex Types

```php
use TechDock\OpcUa\Core\Types\{LocalizedText, QualifiedName, NodeId};

// LocalizedText
$localizedText = new LocalizedText('en-US', 'Hello World');
new Variant(VariantType::LocalizedText, $localizedText);

// QualifiedName
$qualifiedName = new QualifiedName(2, 'MyVariable');
new Variant(VariantType::QualifiedName, $qualifiedName);

// NodeId
$nodeId = NodeId::numeric(2, 1001);
new Variant(VariantType::NodeId, $nodeId);
```

### Arrays

```php
// Array of doubles
$values = [1.1, 2.2, 3.3, 4.4, 5.5];
$variant = Variant::array(VariantType::Double, $values);

// Array of integers
$values = [1, 2, 3, 4, 5];
$variant = Variant::array(VariantType::Int32, $values);

// Array of strings
$values = ['one', 'two', 'three'];
$variant = Variant::array(VariantType::String, $values);
```

## Historical Data

Read historical values for a node:

```php
use TechDock\OpcUa\Core\Types\{ReadRawModifiedDetails, DateTime};

$nodeId = NodeId::numeric(2, 1001);

// Define time range
$startTime = new DateTime('-1 hour');
$endTime = new DateTime('now');

// Read raw history
$details = new ReadRawModifiedDetails(
    isReadModified: false,
    startTime: $startTime,
    endTime: $endTime,
    numValuesPerNode: 100,  // Max values to return
    returnBounds: true,
);

$history = $client->session->readHistory($nodeId, $details);

foreach ($history->dataValues as $dv) {
    echo "{$dv->sourceTimestamp}: {$dv->value}\n";
}
```

## Bulk Operations

### Auto-Batching

Enable automatic splitting of large operations:

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withAutoBatching()  // Enable auto-batching
    ->build();

// Read 10,000 nodes - automatically split into batches
$nodeIds = [/* 10,000 node IDs */];
$results = $client->session->readMultiple($nodeIds);

// Writes also batched automatically
$writes = [/* 10,000 writes */];
$results = $client->session->writeMultiple($writes);
```

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

$allResults = readInBatches($client->session, $nodeIds, 100);
```

## Error Handling

```php
use TechDock\OpcUa\Exceptions\StatusCodeException;

try {
    $value = $client->session->read([$nodeId])[0];

    if (!$value->statusCode->isGood()) {
        // Check specific status codes
        if ($value->statusCode->value === 0x80340000) {
            echo "Node does not exist\n";
        } elseif ($value->statusCode->value === 0x80380000) {
            echo "Bad data encoding\n";
        } else {
            echo "Read failed: {$value->statusCode}\n";
        }
    }

} catch (StatusCodeException $e) {
    echo "Operation failed: {$e->getMessage()}\n";
    echo "Status code: {$e->getStatusCode()}\n";
}
```

### Check Before Write

```php
function canWrite(Session $session, NodeId $nodeId): bool {
    try {
        $accessLevel = $session->readAttribute(
            $nodeId,
            AttributeId::AccessLevel
        );

        // Check writable bit (0x02)
        return ($accessLevel->value & 0x02) !== 0;

    } catch (StatusCodeException) {
        return false;
    }
}

if (canWrite($client->session, $nodeId)) {
    $client->session->write($nodeId, $value);
} else {
    echo "Node is not writable\n";
}
```

## Performance Tips

### 1. Batch Reads/Writes

```php
// ❌ Slow: Individual calls
foreach ($nodeIds as $nodeId) {
    $value = $client->session->read([$nodeId])[0];
    process($value);
}

// ✅ Fast: Batch call
$values = $client->session->readMultiple($nodeIds);
foreach ($values as $value) {
    process($value);
}
```

### 2. Use Appropriate Timestamps

```php
// ❌ Slower: Request all timestamps
$value = $client->session->read([$nodeId], 0.0, TimestampsToReturn::Both)[0];

// ✅ Faster: Request only what you need
$value = $client->session->read([$nodeId], 0.0, TimestampsToReturn::Neither)[0];
```

### 3. Check Status Before Processing

```php
$values = $client->session->readMultiple($nodeIds);

foreach ($values as $i => $value) {
    // Skip bad values early
    if (!$value->statusCode->isGood()) {
        continue;
    }

    process($value);
}
```

## Common Patterns

### Pattern: Read-Modify-Write

```php
// Read current value
$current = $client->session->read([$nodeId])[0];

// Modify
$newValue = $current->value * 1.1;  // Increase by 10%

// Write back
$variant = new Variant(VariantType::Double, $newValue);
$client->session->write($nodeId, $variant);
```

### Pattern: Atomic Multi-Write

```php
// Prepare all writes
$writes = [
    ['nodeId' => $node1, 'value' => $value1],
    ['nodeId' => $node2, 'value' => $value2],
    ['nodeId' => $node3, 'value' => $value3],
];

// Execute atomically
$results = $client->session->writeMultiple($writes);

// Check all succeeded
$allGood = array_reduce(
    $results,
    fn($carry, $status) => $carry && $status->isGood(),
    true
);

if (!$allGood) {
    // Rollback or retry
}
```

### Pattern: Poll for Changes

```php
$lastValue = null;

while (true) {
    $current = $client->session->read([$nodeId])[0];

    if ($current->value !== $lastValue) {
        echo "Value changed: {$current->value}\n";
        $lastValue = $current->value;
    }

    usleep(100000);  // 100ms
}
```

**Note**: For change detection, use [Subscriptions](subscriptions.md) instead!

## See Also

- [Subscriptions](subscriptions.md) - Monitor data changes
- [Browsing](browsing.md) - Discover nodes
- [Client Builder](client-builder.md) - Configuration
- [Examples](../examples/node_operations.php) - Working code
