<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Client\UserIdentity;
use TechDock\OpcUa\Core\Types\BrowsePath;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\QualifiedName;
use TechDock\OpcUa\Core\Types\RelativePath;
use TechDock\OpcUa\Core\Types\RelativePathElement;

/**
 * Node Operations Example
 *
 * Demonstrates TranslateBrowsePathsToNodeIds, RegisterNodes, and UnregisterNodes.
 */

$serverUrl = 'opc.tcp://localhost:4840';

echo "=== OPC UA Node Operations Example ===\n\n";

try {
    // 1. Connect and create session
    echo "1. Connecting to $serverUrl...\n";
    $client = new OpcUaClient($serverUrl);
    $client->connect();

    $session = $client->createSession();
    $session->create();
    $session->activate(UserIdentity::anonymous());
    echo "Session activated\n\n";

    // 2. Translate browse paths to NodeIds
    echo "2. Translating browse paths to NodeIds...\n";
    echo "   Resolving path: /Objects/Server/ServerStatus/CurrentTime\n";

    // Build the relative path
    $pathElements = [
        new RelativePathElement(
            referenceTypeId: NodeId::numeric(0, 35),  // Organizes
            isInverse: false,
            includeSubtypes: true,
            targetName: QualifiedName::create('Server', 0),
        ),
        new RelativePathElement(
            referenceTypeId: NodeId::numeric(0, 47),  // HasComponent
            isInverse: false,
            includeSubtypes: true,
            targetName: QualifiedName::create('ServerStatus', 0),
        ),
        new RelativePathElement(
            referenceTypeId: NodeId::numeric(0, 47),  // HasComponent
            isInverse: false,
            includeSubtypes: true,
            targetName: QualifiedName::create('CurrentTime', 0),
        ),
    ];

    $relativePath = new RelativePath($pathElements);
    $browsePath = new BrowsePath(
        startingNode: NodeId::numeric(0, 85),  // Objects folder
        relativePath: $relativePath,
    );

    $results = $session->translateBrowsePaths([$browsePath]);

    echo "   Results:\n";
    foreach ($results as $i => $result) {
        echo "   Path #" . ($i + 1) . ":\n";
        echo "     Status: {$result->statusCode}\n";

        if ($result->statusCode->isGood() && $result->targets !== []) {
            echo "     Found " . count($result->targets) . " target(s):\n";
            foreach ($result->targets as $j => $target) {
                echo "       Target " . ($j + 1) . ": {$target->targetId}\n";
                echo "         Remaining Path Index: {$target->remainingPathIndex}\n";
            }
        } else {
            echo "     No targets found or error occurred\n";
        }
    }
    echo "\n";

    // 3. Register nodes for optimized access
    echo "3. Registering nodes for repeated access...\n";
    $nodesToRegister = [
        NodeId::numeric(0, 2258),  // Server_ServerStatus_CurrentTime
        NodeId::numeric(0, 2259),  // Server_ServerStatus_State
        NodeId::numeric(0, 2255),  // Server_ServerStatus
    ];

    echo "   Registering " . count($nodesToRegister) . " nodes:\n";
    foreach ($nodesToRegister as $i => $nodeId) {
        echo "     " . ($i + 1) . ". $nodeId\n";
    }

    $registeredNodes = $session->registerNodes($nodesToRegister);

    echo "   Server returned " . count($registeredNodes) . " registered node aliases:\n";
    foreach ($registeredNodes as $i => $nodeId) {
        echo "     " . ($i + 1) . ". $nodeId\n";
    }
    echo "   (Use these aliases for subsequent read/write operations for better performance)\n\n";

    // 4. Read values using registered node aliases
    echo "4. Reading values using registered nodes...\n";
    $values = $session->read($registeredNodes);

    foreach ($values as $i => $dataValue) {
        echo "   Value " . ($i + 1) . ":\n";
        if ($dataValue->statusCode->isGood()) {
            echo "     Value: {$dataValue->value}\n";
            echo "     Source Timestamp: {$dataValue->sourceTimestamp}\n";
        } else {
            echo "     Status: {$dataValue->statusCode}\n";
        }
    }
    echo "\n";

    // 5. Unregister nodes when done
    echo "5. Unregistering nodes...\n";
    $session->unregisterNodes($registeredNodes);
    echo "   Nodes unregistered successfully\n\n";

    // 6. Demonstrate multiple path translations at once
    echo "6. Translating multiple paths at once...\n";
    $multiplePaths = [
        // Path 1: /Objects/Server
        new BrowsePath(
            startingNode: NodeId::numeric(0, 85),
            relativePath: new RelativePath([
                new RelativePathElement(
                    referenceTypeId: NodeId::numeric(0, 35),
                    isInverse: false,
                    includeSubtypes: true,
                    targetName: QualifiedName::create('Server', 0),
                ),
            ]),
        ),
        // Path 2: /Objects/Types
        new BrowsePath(
            startingNode: NodeId::numeric(0, 85),
            relativePath: new RelativePath([
                new RelativePathElement(
                    referenceTypeId: NodeId::numeric(0, 35),
                    isInverse: false,
                    includeSubtypes: true,
                    targetName: QualifiedName::create('Types', 0),
                ),
            ]),
        ),
    ];

    $multipleResults = $session->translateBrowsePaths($multiplePaths);
    echo "   Translated " . count($multipleResults) . " paths:\n";
    foreach ($multipleResults as $i => $result) {
        echo "     Path " . ($i + 1) . ": ";
        if ($result->statusCode->isGood() && $result->targets !== []) {
            echo $result->targets[0]->targetId . "\n";
        } else {
            echo "Failed - {$result->statusCode}\n";
        }
    }
    echo "\n";

    // Cleanup
    echo "7. Closing session...\n";
    $session->close();
    $client->disconnect();

    echo "\n=== Operations Complete ===\n";
} catch (Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
