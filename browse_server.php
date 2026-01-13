<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Browse OPC UA server at opc.tcp://127.0.0.1:4840
 * and display all available datapoints
 */

$endpointUrl = 'opc.tcp://127.0.0.1:4840';

printf("Connecting to %s…\n\n", $endpointUrl);

try {
    // Use ClientBuilder for better compatibility
    $client = ClientBuilder::create()
        ->endpoint($endpointUrl)
        ->withNoSecurity()
        ->withAnonymousAuth()
        ->build();

    echo "✓ Connected\n";
    echo "✓ Session activated\n\n";

    $browser = $client->browser;

    // Browse the root Objects folder
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Browsing Objects Folder (Root)\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $objectsFolder = NodeId::numeric(0, 85); // Standard Objects folder
    $rootReferences = $browser->browse($objectsFolder);

    echo "Found " . count($rootReferences) . " top-level objects:\n\n";
    foreach ($rootReferences as $ref) {
        printf(
            "  • %s (NodeClass: %d, NodeId: %s)\n",
            $ref->browseName->name,
            $ref->nodeClass,
            $ref->nodeId->nodeId->toString()
        );
    }

    // Recursively browse each top-level object
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Detailed Recursive Browse (Depth: 3)\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $allNodes = $browser->browseRecursive($objectsFolder, maxDepth: 3);

    echo "Total nodes found: " . count($allNodes) . "\n\n";

    // Group by node class
    $byClass = [
        1 => [], // Object
        2 => [], // Variable
        4 => [], // Method
        8 => [], // ObjectType
        32 => [], // VariableType
        64 => [], // ReferenceType
        128 => [], // DataType
    ];

    $nodeClassNames = [
        1 => 'Object',
        2 => 'Variable',
        4 => 'Method',
        8 => 'ObjectType',
        32 => 'VariableType',
        64 => 'ReferenceType',
        128 => 'DataType',
    ];

    foreach ($allNodes as $nodeIdStr => $ref) {
        $class = $ref->nodeClass;
        if (!isset($byClass[$class])) {
            $byClass[$class] = [];
        }
        $byClass[$class][] = $ref;
    }

    echo "Nodes by Class:\n";
    foreach ($byClass as $class => $nodes) {
        if (count($nodes) > 0) {
            $className = $nodeClassNames[$class] ?? "Unknown($class)";
            printf("  %s: %d nodes\n", $className, count($nodes));
        }
    }

    // Show all Variables (datapoints)
    if (count($byClass[2]) > 0) {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "Available Variables (Datapoints)\n";
        echo "═══════════════════════════════════════════════════════════\n\n";

        foreach ($byClass[2] as $var) {
            printf(
                "  • %s\n    NodeId: %s\n",
                $var->browseName->name,
                $var->nodeId->nodeId->toString()
            );
            if (isset($var->displayName->text)) {
                printf("    DisplayName: %s\n", $var->displayName->text);
            }
            echo "\n";
        }
    }

    // Show all Methods
    if (count($byClass[4]) > 0) {
        echo "═══════════════════════════════════════════════════════════\n";
        echo "Available Methods\n";
        echo "═══════════════════════════════════════════════════════════\n\n";

        foreach ($byClass[4] as $method) {
            printf(
                "  • %s\n    NodeId: %s\n\n",
                $method->browseName->name,
                $method->nodeId->nodeId->toString()
            );
        }
    }

    echo "✓ Browse completed successfully!\n";

    $client->disconnect();
} catch (Throwable $e) {
    fwrite(STDERR, "\n✗ Error: {$e->getMessage()}\n");
    fwrite(STDERR, "Stack trace:\n{$e->getTraceAsString()}\n");
    exit(1);
}
