<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Messages\BrowseDirection;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Example: Browse Large Address Space
 *
 * This example demonstrates the managedBrowse() method which automatically
 * handles continuation points when browsing servers with large address spaces.
 *
 * This is critical for production servers that may have thousands of nodes.
 */

try {
    // Connect to server
    $serverUrl = $argv[1] ?? 'opc.tcp://localhost:4840';
    echo "Connecting to: $serverUrl\n\n";

    $client = new OpcUaClient($serverUrl);
    $session = $client->createSession();

    echo "Session created successfully!\n";
    echo "Session ID: {$session->getSessionId()}\n\n";

    // Example 1: Browse with manual continuation handling
    echo "=== Example 1: Manual Continuation Handling ===\n\n";

    $browseDesc = new BrowseDescription(
        nodeId: NodeId::numeric(0, 85), // Objects folder
        browseDirection: BrowseDirection::Forward,
        referenceTypeId: NodeId::numeric(0, 33), // HierarchicalReferences
        includeSubtypes: true,
        nodeClassMask: 0, // All node classes
        resultMask: 63 // All attributes
    );

    // Initial browse with limit
    $result = $session->browse($browseDesc);

    echo "Initial browse returned " . count($result->references) . " references\n";

    if ($result->continuationPoint !== null && $result->continuationPoint !== '') {
        echo "Continuation point returned, fetching more...\n";

        // Continue browsing
        $nextResults = $session->browseNext([$result->continuationPoint]);

        if (!empty($nextResults)) {
            echo "BrowseNext returned " . count($nextResults[0]->references) . " more references\n";

            // Release continuation point if there are more
            if ($nextResults[0]->continuationPoint !== null && $nextResults[0]->continuationPoint !== '') {
                echo "Releasing remaining continuation points...\n";
                $session->browseNext([$nextResults[0]->continuationPoint], releaseContinuationPoints: true);
            }
        }
    }

    echo "\n";

    // Example 2: Automatic continuation handling with managedBrowse
    echo "=== Example 2: Automatic Continuation (Managed Browse) ===\n\n";

    // This method automatically handles all continuation points
    $completeResult = $session->managedBrowse(
        $browseDesc,
        maxReferencesPerNode: 100 // Process 100 references at a time
    );

    echo "Managed browse returned " . count($completeResult->references) . " total references\n";
    echo "No continuation points remain\n\n";

    // Display the references
    echo "References found:\n";
    $count = 0;
    foreach ($completeResult->references as $ref) {
        $count++;
        echo "  $count. {$ref->browseName->name} ({$ref->nodeClass})\n";

        // Limit display to first 20
        if ($count >= 20) {
            $remaining = count($completeResult->references) - 20;
            if ($remaining > 0) {
                echo "  ... and $remaining more\n";
            }
            break;
        }
    }

    echo "\n";

    // Example 3: Browse entire address space recursively
    echo "=== Example 3: Recursive Browse (Large Address Space) ===\n\n";

    function browseRecursive($session, $nodeId, $depth = 0, $maxDepth = 3, &$totalNodes = 0)
    {
        if ($depth > $maxDepth) {
            return;
        }

        $indent = str_repeat("  ", $depth);

        try {
            $browseDesc = new BrowseDescription(
                nodeId: $nodeId,
                browseDirection: BrowseDirection::Forward,
                referenceTypeId: NodeId::numeric(0, 33), // HierarchicalReferences
                includeSubtypes: true,
                nodeClassMask: 0,
                resultMask: 63
            );

            // Use managedBrowse to handle large result sets automatically
            $result = $session->managedBrowse($browseDesc, maxReferencesPerNode: 50);
            $totalNodes += count($result->references);

            foreach ($result->references as $ref) {
                echo "{$indent}└─ {$ref->browseName->name} ({$ref->nodeClass})\n";

                // Don't browse properties (they're leaf nodes)
                if ($ref->nodeClass !== 'Variable') {
                    // Recursively browse child nodes
                    browseRecursive($session, $ref->nodeId->toNodeId(), $depth + 1, $maxDepth, $totalNodes);
                }
            }
        } catch (Exception $e) {
            echo "{$indent}Error: {$e->getMessage()}\n";
        }
    }

    $totalNodes = 0;
    echo "Browsing from Objects folder (max depth 3)...\n";
    browseRecursive($session, NodeId::numeric(0, 85), 0, 3, $totalNodes);

    echo "\nTotal nodes browsed: $totalNodes\n";

    // Close session
    $session->close();
    echo "\nSession closed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Key Benefits of Managed Browse ===\n";
echo "1. Automatically handles continuation points\n";
echo "2. Can browse servers with thousands of nodes\n";
echo "3. Prevents server-side resource exhaustion\n";
echo "4. Provides safety limits (max 1000 iterations)\n";
echo "5. Automatic cleanup on errors\n";
echo "\nUse managedBrowse() for production systems with large address spaces!\n";
