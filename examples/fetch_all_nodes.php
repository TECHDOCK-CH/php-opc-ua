<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Client\Session;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Types\ExpandedNodeId;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\DataValue;

const MAX_BROWSE_DEPTH = 5;

function nodeClassLabel(int $nodeClass): string
{
    $labels = [
        0 => 'Unspecified',
        1 => 'Object',
        2 => 'Variable',
        4 => 'Method',
        8 => 'ObjectType',
        16 => 'VariableType',
        32 => 'ReferenceType',
        64 => 'DataType',
        128 => 'View',
    ];

    return $labels[$nodeClass] ?? "Unknown({$nodeClass})";
}

function browseAndRead(
    Session  $session,
    NodeId   $nodeId,
    string   $displayName,
    int      $nodeClass,
    int      $depth,
    int      $maxDepth,
    array    &$visited,
    string   $prefix = '',
    bool     $isLast = true
): void {
    $connector = $depth === 0 ? '' : ($isLast ? '└─ ' : '├─ ');
    
    $extraInfo = "";
    if ($nodeClass === 2) { // Variable
        try {
            /** @var DataValue[] $values */
            $values = $session->read([$nodeId]);
            if (isset($values[0]) && $values[0]->isGood()) {
                $val = $values[0]->value;
                if (is_array($val)) {
                    $valStr = json_encode($val);
                } elseif (is_object($val)) {
                    if (method_exists($val, '__toString')) {
                        $valStr = (string)$val;
                    } else {
                        $valStr = get_class($val);
                    }
                } else {
                    $valStr = (string)$val;
                }
                $extraInfo = " = " . $valStr;
            } else {
                $statusCode = isset($values[0]) ? $values[0]->statusCode->toString() : 'Unknown';
                $extraInfo = " (Read Failed: $statusCode)";
            }
        } catch (Throwable $e) {
            $extraInfo = " (Read Error: " . $e->getMessage() . ")";
        }
    }

    printf(
        "%s%s%s (NodeId: %s) [%s]%s\n",
        $prefix,
        $connector,
        $displayName,
        $nodeId->toString(),
        nodeClassLabel($nodeClass),
        $extraInfo
    );

    if ($depth >= $maxDepth) {
        return;
    }

    try {
        $browseResult = $session->browse(BrowseDescription::create($nodeId));
    } catch (Throwable $e) {
        printf(
            "%s%s• Browse failed: %s\n",
            $prefix . ($depth === 0 ? '' : ($isLast ? '   ' : '│  ')),
            $depth === 0 ? '' : '  ',
            $e->getMessage()
        );
        return;
    }

    $references = $browseResult->references;
    $childCount = count($references);

    if ($childCount === 0) {
        return;
    }

    $nextPrefix = $prefix . ($depth === 0 ? '' : ($isLast ? '   ' : '│  '));

    // Filter out references we've already visited to avoid infinite loops
    // But we need to keep the index for isLast calculation, so we iterate and check inside
    
    foreach ($references as $index => $reference) {
        $childIsLast = $index === $childCount - 1;
        $childNodeId = $reference->nodeId->nodeId;
        
        // Skip if we've visited this node ID already
        $nodeKey = $childNodeId->toString();
        if (isset($visited[$nodeKey])) {
             // Print a marker that we skipped it? Maybe too verbose.
             continue;
        }
        $visited[$nodeKey] = true;

        $childDisplayName = $reference->displayName->text
            ?? $reference->browseName->name
            ?? $childNodeId->toString();

        browseAndRead(
            session: $session,
            nodeId: $childNodeId,
            displayName: $childDisplayName,
            nodeClass: $reference->nodeClass,
            depth: $depth + 1,
            maxDepth: $maxDepth,
            visited: $visited,
            prefix: $nextPrefix,
            isLast: $childIsLast
        );
    }
}

$endpointUrl = 'opc.tcp://127.0.0.1:4840';

printf("Connecting to %s…\n\n", $endpointUrl);

$client = new OpcUaClient($endpointUrl, MessageSecurityMode::None);

try {
    $client->connect();
    
    // Create a session
    $session = $client->createSession();
    $session->create();
    $session->activate();

    echo "✓ Session activated successfully\n";
    echo "\nBrowsing and fetching values...\n";

    $rootNodeId = NodeId::numeric(0, 84); // Root folder
    $visited = [];
    $visited[$rootNodeId->toString()] = true;

    browseAndRead(
        session: $session,
        nodeId: $rootNodeId,
        displayName: 'Objects',
        nodeClass: 1, // Object
        depth: 0,
        maxDepth: MAX_BROWSE_DEPTH,
        visited: $visited
    );

} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
} finally {
    if (isset($client)) {
        $client->disconnect();
    }
}
