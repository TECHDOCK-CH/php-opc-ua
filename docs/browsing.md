# Browsing the OPC UA Address Space

The address space is a hierarchical structure of nodes representing data, objects, variables, and methods. The `Browser` class provides powerful tools for exploring and navigating this structure.

## Quick Start

```php
use TechDock\OpcUa\Core\Types\NodeId;

$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->build();

// Browse the Objects folder (standard node at i=85)
$objectsFolder = NodeId::numeric(0, 85);
$references = $client->browser->browse($objectsFolder);

foreach ($references as $ref) {
    echo "{$ref->displayName->text}\n";
}
```

## Browser API

### `browse(NodeId $nodeId, BrowserOptions $options = null): array`

Browse a single node to get its references (children).

```php
$nodeId = NodeId::numeric(0, 85);  // Objects folder
$refs = $client->browser->browse($nodeId);

// Returns array of ReferenceDescription objects
foreach ($refs as $ref) {
    echo "Name: {$ref->displayName->text}\n";
    echo "NodeId: {$ref->nodeId}\n";
    echo "NodeClass: {$ref->nodeClass->name}\n";
    echo "TypeDefinition: {$ref->typeDefinition}\n";
}
```

### `browseRecursive(NodeId $nodeId, int $maxDepth = -1): array`

Recursively browse from a starting node.

```php
// Browse entire subtree
$tree = $client->browser->browseRecursive($objectsFolder);

// Limit depth to avoid deep recursion
$tree = $client->browser->browseRecursive($objectsFolder, maxDepth: 3);

// Returns nested array structure
foreach ($tree as $node) {
    echo "{$node['reference']->displayName->text}\n";
    foreach ($node['children'] as $child) {
        echo "  {$child['reference']->displayName->text}\n";
    }
}
```

### `browseMultiple(array $nodeIds, BrowserOptions $options = null): array`

Browse multiple nodes in a single call (more efficient).

```php
$nodes = [
    NodeId::numeric(0, 85),  // Objects
    NodeId::numeric(0, 86),  // Types
    NodeId::numeric(0, 87),  // Views
];

$results = $client->browser->browseMultiple($nodes);

// Returns array keyed by node ID string
foreach ($results as $nodeIdStr => $refs) {
    echo "Node $nodeIdStr has " . count($refs) . " children\n";
}
```

## Browse Options

Customize browsing behavior with `BrowserOptions`:

```php
use TechDock\OpcUa\Client\BrowserOptions;
use TechDock\OpcUa\Core\Messages\BrowseDirection;
use TechDock\OpcUa\Core\Types\NodeId;

$options = new BrowserOptions(
    browseDirection: BrowseDirection::Forward,    // Forward, Inverse, or Both
    referenceTypeId: NodeId::numeric(0, 33),     // HierarchicalReferences
    includeSubtypes: true,                        // Include subtype references
    nodeClassMask: 0,                             // 0 = all classes
    resultMask: 0x3F,                             // All attributes
    maxReferencesPerNode: 100,                    // Limit results
);

$refs = $client->browser->browse($nodeId, $options);
```

### Browse Directions

```php
use TechDock\OpcUa\Core\Messages\BrowseDirection;

// Forward: Parent → Children (most common)
$options->browseDirection = BrowseDirection::Forward;

// Inverse: Child → Parent
$options->browseDirection = BrowseDirection::Inverse;

// Both: All connected nodes
$options->browseDirection = BrowseDirection::Both;
```

### Reference Type Filtering

```php
// Only hierarchical references (folders, objects)
$options->referenceTypeId = NodeId::numeric(0, 33);  // HierarchicalReferences

// Only HasProperty references
$options->referenceTypeId = NodeId::numeric(0, 46);  // HasProperty

// Only HasComponent references
$options->referenceTypeId = NodeId::numeric(0, 47);  // HasComponent

// Include subtypes
$options->includeSubtypes = true;  // Default
```

### Node Class Filtering

```php
use TechDock\OpcUa\Core\Types\NodeClass;

// Only variables
$options->nodeClassMask = NodeClass::Variable->value;

// Only objects
$options->nodeClassMask = NodeClass::Object->value;

// Variables and objects
$options->nodeClassMask = NodeClass::Variable->value | NodeClass::Object->value;

// All classes (default)
$options->nodeClassMask = 0;
```

## Common Browsing Patterns

### Pattern 1: Browse All Variables

Find all variable nodes under a folder:

```php
use TechDock\OpcUa\Core\Types\NodeClass;

function browseVariables(Browser $browser, NodeId $startNode): array {
    $options = new BrowserOptions(
        nodeClassMask: NodeClass::Variable->value,
    );

    return $browser->browseRecursive($startNode, options: $options);
}

$variables = browseVariables($client->browser, $objectsFolder);
```

### Pattern 2: Build Address Space Map

Create a complete map of the address space:

```php
function buildAddressSpaceMap(Browser $browser, NodeId $root): array {
    $map = [];

    $refs = $browser->browseRecursive($root);

    function flatten(array $nodes, string $path = ''): array {
        $result = [];
        foreach ($nodes as $node) {
            $name = $node['reference']->displayName->text;
            $fullPath = $path ? "$path/$name" : $name;

            $result[$fullPath] = $node['reference']->nodeId;

            if (!empty($node['children'])) {
                $result = array_merge(
                    $result,
                    flatten($node['children'], $fullPath)
                );
            }
        }
        return $result;
    }

    return flatten($refs);
}

$map = buildAddressSpaceMap($client->browser, $objectsFolder);

// Use the map
foreach ($map as $path => $nodeId) {
    echo "$path => $nodeId\n";
}
```

### Pattern 3: Find Node by Browse Path

Navigate using a path string:

```php
function findNodeByPath(Browser $browser, NodeId $root, string $path): ?NodeId {
    $parts = explode('/', $path);
    $currentNode = $root;

    foreach ($parts as $part) {
        $refs = $browser->browse($currentNode);

        $found = false;
        foreach ($refs as $ref) {
            if ($ref->displayName->text === $part) {
                $currentNode = $ref->nodeId;
                $found = true;
                break;
            }
        }

        if (!$found) {
            return null;
        }
    }

    return $currentNode;
}

// Find "Server/ServerStatus/CurrentTime"
$nodeId = findNodeByPath(
    $client->browser,
    NodeId::numeric(0, 85),  // Objects
    'Server/ServerStatus/CurrentTime'
);
```

### Pattern 4: Browse Only Writable Variables

Find variables that can be written to:

```php
function browseWritableVariables(
    Session $session,
    Browser $browser,
    NodeId $startNode
): array {
    $writable = [];

    $refs = $browser->browseRecursive($startNode);

    function findWritable(array $nodes, Session $session): array {
        $result = [];

        foreach ($nodes as $node) {
            $ref = $node['reference'];

            if ($ref->nodeClass === NodeClass::Variable) {
                // Read AccessLevel attribute
                $accessLevel = $session->readAttribute(
                    $ref->nodeId,
                    AttributeId::AccessLevel
                );

                // Check if writable (bit 1 set)
                if (($accessLevel->value & 0x02) !== 0) {
                    $result[] = $ref;
                }
            }

            if (!empty($node['children'])) {
                $result = array_merge(
                    $result,
                    findWritable($node['children'], $session)
                );
            }
        }

        return $result;
    }

    return findWritable($refs, $session);
}

$writable = browseWritableVariables(
    $client->session,
    $client->browser,
    $objectsFolder
);
```

## Caching

Enable caching to avoid repeated browse calls:

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withCache(maxSize: 5000, ttl: 600.0)  // 10 minute cache
    ->build();

// First call: queries server
$refs1 = $client->browser->browse($nodeId);

// Second call: from cache (if within TTL)
$refs2 = $client->browser->browse($nodeId);

// Check cache stats
$stats = $client->getCacheStats();
echo "Cache hits: {$stats['hits']}, misses: {$stats['misses']}\n";
```

## Performance Tips

### 1. Use `browseMultiple` for Bulk Operations

```php
// ❌ Slow: Multiple calls
foreach ($nodeIds as $nodeId) {
    $results[$nodeId] = $client->browser->browse($nodeId);
}

// ✅ Fast: Single call
$results = $client->browser->browseMultiple($nodeIds);
```

### 2. Limit Recursion Depth

```php
// ❌ Potentially slow: Unlimited depth
$tree = $client->browser->browseRecursive($root);

// ✅ Better: Limited depth
$tree = $client->browser->browseRecursive($root, maxDepth: 5);
```

### 3. Filter Early

```php
// ❌ Slow: Browse all, filter in PHP
$refs = $client->browser->browse($nodeId);
$variables = array_filter($refs, fn($r) => $r->nodeClass === NodeClass::Variable);

// ✅ Fast: Filter on server
$options = new BrowserOptions(
    nodeClassMask: NodeClass::Variable->value,
);
$variables = $client->browser->browse($nodeId, $options);
```

### 4. Use Continuation Points

Large browse results are automatically handled via continuation points:

```php
// The library handles this automatically
$refs = $client->browser->browse($nodeId);

// Even if server returns 1000+ references across
// multiple responses, you get them all transparently
```

## Standard Nodes

Common starting points (namespace 0):

```php
// Root nodes
$rootFolder = NodeId::numeric(0, 84);     // RootFolder
$objectsFolder = NodeId::numeric(0, 85);  // Objects
$typesFolder = NodeId::numeric(0, 86);    // Types
$viewsFolder = NodeId::numeric(0, 87);    // Views

// Server node
$serverNode = NodeId::numeric(0, 2253);          // Server
$serverStatus = NodeId::numeric(0, 2256);        // ServerStatus
$serverCurrentTime = NodeId::numeric(0, 2258);   // CurrentTime
$serverState = NodeId::numeric(0, 2259);         // State
```

## Error Handling

```php
use TechDock\OpcUa\Exceptions\StatusCodeException;

try {
    $refs = $client->browser->browse($nodeId);

} catch (StatusCodeException $e) {
    if ($e->getStatusCode()->value === 0x80340000) {
        // BadNodeIdUnknown
        echo "Node does not exist\n";
    } elseif ($e->getStatusCode()->value === 0x80360000) {
        // BadNotReadable
        echo "Node cannot be browsed\n";
    } else {
        echo "Browse error: {$e->getMessage()}\n";
    }
}
```

## See Also

- [Reading & Writing](reading-writing.md) - Data operations
- [Client Builder](client-builder.md) - Configuration
- [API Reference](api/browser.md) - Complete Browser API
- [Examples](../examples/browser_helper_demo.php) - Working code
