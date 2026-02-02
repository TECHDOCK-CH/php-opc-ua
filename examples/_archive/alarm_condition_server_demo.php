<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Client\TypeCache;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Types\DynamicStructure;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Live Demo: OPC UA Alarm Condition Server
 *
 * This example demonstrates:
 * 1. Connecting to opc.tcp://opcua.demo-this.com:62544/Quickstarts/AlarmConditionServer
 * 2. Browsing all nodes in the Objects folder
 * 3. Reading specific server status and diagnostic nodes:
 *    - ns=0;i=2254 (ServerArray)
 *    - ns=0;i=2255 (NamespaceArray)
 *    - ns=0;i=2256 (ServerStatus)
 *    - ns=0;i=2267 (ServiceLevel)
 *    - ns=0;i=2994 (Auditing)
 */

// Server connection details
$serverUrl = 'opc.tcp://opcua.demo-this.com:62544/Quickstarts/AlarmConditionServer';

echo "=======================================================\n";
echo "OPC UA Alarm Condition Server - Live Demo\n";
echo "=======================================================\n\n";
echo "Server: {$serverUrl}\n\n";

// Create client
$client = new OpcUaClient($serverUrl);
$session = null;

try {
    // Step 1: Connect to the server
    echo "[1/4] Connecting to server...\n";
    $client->connect();
    echo "✓ Connected\n\n";

    // Step 2: Create and activate session
    echo "[2/4] Creating and activating session...\n";
    $session = $client->createSession('Alarm Condition Server Demo');
    $session->create();
    $session->activate(); // Uses anonymous authentication with auto-detected policyId
    echo "✓ Session activated\n\n";

    // Display endpoint information
    $endpoint = $session->getSecureChannel()->getSelectedEndpoint();
    if ($endpoint !== null) {
        echo "Endpoint Details:\n";
        echo "  URL: {$endpoint->endpointUrl}\n";
        echo "  Security Mode: {$endpoint->securityMode->name}\n";
        echo "  Security Policy: {$endpoint->securityPolicy->value}\n\n";
    }

    // Step 3: Browse all nodes in the Objects folder
    echo "[3/4] Browsing Objects folder (ns=0;i=85)...\n";
    echo "-------------------------------------------------------\n";

    $objectsFolderId = NodeId::numeric(0, 85);
    $browseResult = $session->browse(BrowseDescription::create($objectsFolderId));

    echo "Found " . count($browseResult->references) . " child nodes:\n\n";

    foreach ($browseResult->references as $index => $reference) {
        $displayName = $reference->displayName->text ?? $reference->browseName->name ?? 'Unknown';
        $nodeId = $reference->nodeId->nodeId->toString();
        $nodeClass = $reference->nodeClass->name ?? 'Unknown';

        echo sprintf(
            "  %3d. %-40s | NodeId: %-20s | Class: %s\n",
            $index + 1,
            $displayName,
            $nodeId,
            $nodeClass
        );
    }

    // Handle continuation point if present
    if ($browseResult->continuationPoint !== null && $browseResult->continuationPoint !== '') {
        echo "\n📋 Note: Continuation point present - more results available\n";
        echo "   Use BrowseNext to retrieve additional references\n";

        // Release the continuation point
        $session->browseNext([$browseResult->continuationPoint], releaseContinuationPoints: true);
        echo "   ✓ Continuation point released\n";
    }

    // Step 4: Read specific server nodes
    echo "\n[4/4] Reading specific server nodes...\n";
    echo "-------------------------------------------------------\n";

    // Define the nodes we want to read
    $nodesToRead = [
        ['id' => NodeId::numeric(0, 2254), 'name' => 'ServerArray'],
        ['id' => NodeId::numeric(0, 2255), 'name' => 'NamespaceArray'],
        ['id' => NodeId::numeric(0, 2256), 'name' => 'ServerStatus'],
        ['id' => NodeId::numeric(0, 2267), 'name' => 'ServiceLevel'],
        ['id' => NodeId::numeric(0, 2994), 'name' => 'Auditing'],
    ];

    // Read all nodes in a single request
    $nodeIds = array_map(fn($node) => $node['id'], $nodesToRead);
    $dataValues = $session->read($nodeIds);

    // Create type cache for dynamic structure decoding
    $typeCache = new TypeCache($session);

    echo "\nServer Information:\n\n";

    foreach ($nodesToRead as $index => $nodeInfo) {
        $dataValue = $dataValues[$index];
        $nodeName = $nodeInfo['name'];
        $nodeIdStr = $nodeInfo['id']->toString();

        echo "  {$nodeName} ({$nodeIdStr}):\n";

        // Check if status code is good (null means good, or explicitly good)
        if ($dataValue->statusCode === null || $dataValue->statusCode->isGood()) {
            $value = $dataValue->value?->value;

            // Format the output based on the value type
            if (is_array($value)) {
                echo "    Type: Array (" . count($value) . " elements)\n";
                echo "    Values:\n";
                foreach ($value as $idx => $item) {
                    if (is_string($item)) {
                        echo "      [{$idx}] {$item}\n";
                    } else {
                        echo "      [{$idx}] " . var_export($item, true) . "\n";
                    }
                }
            } elseif ($value instanceof ExtensionObject) {
                // Try to decode ExtensionObject dynamically
                echo "    Type: ExtensionObject ({$value->typeId->toString()})\n";

                $decoded = DynamicStructure::decode($value, $typeCache);
                if ($decoded !== null) {
                    // Check if it's a typed object (well-known type)
                    if (is_object($decoded)) {
                        echo "    Decoded Type: " . get_class($decoded) . "\n";
                        echo "    Value:\n";

                        // Display object properties
                        $reflection = new ReflectionObject($decoded);
                        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

                        foreach ($properties as $property) {
                            $propName = $property->getName();
                            $propValue = $property->getValue($decoded);

                            echo "      {$propName}: ";
                            if ($propValue instanceof UnitEnum) {
                                echo "{$propValue->name}\n";
                            } elseif (is_object($propValue) && method_exists($propValue, '__toString')) {
                                echo "{$propValue}\n";
                            } elseif (is_object($propValue)) {
                                echo get_class($propValue) . " (...)\n";
                            } elseif (is_array($propValue)) {
                                echo "[" . count($propValue) . " items]\n";
                            } else {
                                echo var_export($propValue, true) . "\n";
                            }
                        }
                    } elseif (is_array($decoded)) {
                        // Dynamic structure returned as array
                        echo "    Decoded Fields:\n";
                        foreach ($decoded as $fieldName => $fieldValue) {
                            echo "      {$fieldName}: ";
                            if (is_object($fieldValue) && method_exists($fieldValue, '__toString')) {
                                echo "{$fieldValue}\n";
                            } elseif (is_array($fieldValue)) {
                                echo "[" . count($fieldValue) . " items]\n";
                            } elseif (is_string($fieldValue) || is_numeric($fieldValue)) {
                                echo "{$fieldValue}\n";
                            } else {
                                echo var_export($fieldValue, true) . "\n";
                            }
                        }
                    }
                } else {
                    echo "    (Unable to decode - unknown type or server doesn't support DataTypeDefinition)\n";
                    echo "    Binary data: " . $value->getBodyLength() . " bytes\n";
                }
            } elseif (is_object($value)) {
                // For other complex objects
                echo "    Type: " . get_class($value) . "\n";
                echo "    Value:\n";

                // Display object properties
                $reflection = new ReflectionObject($value);
                $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

                foreach ($properties as $property) {
                    $propName = $property->getName();
                    $propValue = $property->getValue($value);

                    if (is_object($propValue)) {
                        // Handle nested objects (like DateTime, etc.)
                        if (method_exists($propValue, '__toString')) {
                            echo "      {$propName}: {$propValue}\n";
                        } else {
                            echo "      {$propName}: " . get_class($propValue) . "\n";
                        }
                    } elseif (is_array($propValue)) {
                        echo "      {$propName}: [" . count($propValue) . " items]\n";
                    } else {
                        echo "      {$propName}: " . var_export($propValue, true) . "\n";
                    }
                }
            } else {
                // Simple scalar values
                echo "    Type: " . gettype($value) . "\n";
                echo "    Value: " . var_export($value, true) . "\n";
            }

            // Show timestamps if available
            if ($dataValue->sourceTimestamp !== null) {
                echo "    Source Timestamp: {$dataValue->sourceTimestamp}\n";
            }
            if ($dataValue->serverTimestamp !== null) {
                echo "    Server Timestamp: {$dataValue->serverTimestamp}\n";
            }
        } else {
            echo "    Status: {$dataValue->statusCode} (Error)\n";
        }

        echo "\n";
    }

    echo "=======================================================\n";
    echo "✓ Demo completed successfully!\n";
    echo "=======================================================\n";

} catch (Throwable $e) {
    echo "\n❌ Error occurred:\n";
    echo "  Message: {$e->getMessage()}\n";
    echo "  File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
} finally {
    // Clean up
    if ($session !== null) {
        try {
            $session->close();
            echo "\n✓ Session closed\n";
        } catch (Throwable $e) {
            echo "\n⚠ Warning: Error closing session: {$e->getMessage()}\n";
        }
    }

    try {
        $client->disconnect();
        echo "✓ Disconnected from server\n";
    } catch (Throwable $e) {
        echo "⚠ Warning: Error disconnecting: {$e->getMessage()}\n";
    }
}
