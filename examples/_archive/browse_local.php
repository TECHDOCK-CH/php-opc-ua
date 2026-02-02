<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Client\Session;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Types\ExpandedNodeId;
use TechDock\OpcUa\Core\Types\NodeId;

const MAX_BROWSE_DEPTH = 5;

/**
 * Map OPC UA node class bitmask values to readable labels.
 */
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

/**
 * Format a type definition reference for display.
 */
function formatTypeDefinition(?ExpandedNodeId $typeDefinition): string
{
    if ($typeDefinition === null || $typeDefinition->nodeId->isNull()) {
        return 'Unknown';
    }

    return $typeDefinition->toString();
}

/**
 * Recursively browse the address space and render a tree up to a max depth.
 *
 * @param array<string, bool> $visited
 */
function browseTree(
    Session $session,
    NodeId  $nodeId,
    string  $displayName,
    string  $typeLabel,
    int     $depth,
    int     $maxDepth,
    array   &$visited,
    string  $prefix = '',
    bool    $isLast = true,
    bool    $skipChildren = false
): void
{
    $connector = $depth === 0 ? '' : ($isLast ? '└─ ' : '├─ ');

    printf(
        "%s%s%s (NodeId: %s) [%s]\n",
        $prefix,
        $connector,
        $displayName,
        $nodeId->toString(),
        $typeLabel
    );

    if ($skipChildren || $depth >= $maxDepth) {
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

    foreach ($references as $index => $reference) {
        $childIsLast = $index === $childCount - 1;
        $childNodeId = $reference->nodeId->nodeId;
        $childDisplayName = $reference->displayName->text
            ?? $reference->browseName->name
            ?? $childNodeId->toString();

        $typeDescriptor = formatTypeDefinition($reference->typeDefinition);
        $metadata = sprintf(
            'NodeClass: %s, Type: %s',
            nodeClassLabel($reference->nodeClass),
            $typeDescriptor
        );

        $nodeKey = $reference->nodeId->toString();
        $alreadyVisited = isset($visited[$nodeKey]);
        $visited[$nodeKey] = true;

        browseTree(
            session: $session,
            nodeId: $childNodeId,
            displayName: $childDisplayName,
            typeLabel: $metadata . ($alreadyVisited ? '; revisited' : ''),
            depth: $depth + 1,
            maxDepth: $maxDepth,
            visited: $visited,
            prefix: $nextPrefix,
            isLast: $childIsLast,
            skipChildren: $alreadyVisited
        );
    }
}

$endpointUrl = 'opc.tcp://localhost:4840';

printf("Connecting to %s…\n\n", $endpointUrl);

$client = new OpcUaClient($endpointUrl, MessageSecurityMode::None);

try {
    $client->connect();
    $secureChannel = $client->getSecureChannel();

    if ($secureChannel === null) {
        throw new RuntimeException('Secure channel was not established.');
    }

    echo "Discovered endpoints:\n";
    foreach ($secureChannel->getAvailableEndpoints() as $index => $endpoint) {
        printf(
            "  [%d] %s | Security=%s | Mode=%s\n",
            $index + 1,
            $endpoint->endpointUrl,
            $endpoint->securityPolicy->value,
            $endpoint->securityMode->name,
        );
    }

    $selected = $secureChannel->getSelectedEndpoint();
    if ($selected !== null) {
        printf(
            "\nUsing endpoint: %s (%s / %s)\n",
            $selected->endpointUrl,
            $selected->securityPolicy->value,
            $selected->securityMode->name,
        );
    }

    echo "\nCreating and activating session…\n";

    // Create a session
    $session = $client->createSession();
    $session->create();
    $session->activate();

    echo "✓ Session activated successfully\n";

    echo "\nBrowsing the Objects folder tree (max depth " . MAX_BROWSE_DEPTH . ")…\n";

    $rootNodeId = NodeId::numeric(0, 85);
    $visited = [];
    $visited[$rootNodeId->toString()] = true;

    browseTree(
        session: $session,
        nodeId: $rootNodeId,
        displayName: 'Objects',
        typeLabel: 'NodeClass: Object, Type: ns=0;i=61 (FolderType)',
        depth: 0,
        maxDepth: MAX_BROWSE_DEPTH,
        visited: $visited
    );
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
} finally {
    $client->disconnect();
}
