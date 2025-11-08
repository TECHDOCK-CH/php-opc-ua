<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Example: Browsing the public OPC UA Alarm Condition demo server
 *
 * This example demonstrates:
 * 1. Connecting to a live OPC UA server
 * 2. Automatic policy ID detection (no need to specify server-specific policy IDs)
 * 3. Browsing the address space
 * 4. Using BrowseNext for continuation points
 */

$serverUrl = 'opc.tcp://opcua.demo-this.com:62544/Quickstarts/AlarmConditionServer';

echo "Connecting to demo server...\n";
echo "Server: {$serverUrl}\n\n";

$client = new OpcUaClient($serverUrl);

try {
    // Connect to the server
    $client->connect();

    // Create and activate session
    // Note: No need to specify policyId - it's automatically detected!
    $session = $client->createSession();
    $session->create();
    $session->activate(); // Automatically detects correct policyId from endpoint

    echo "✓ Connected and authenticated successfully\n";

    // Display endpoint information
    $endpoint = $session->getSecureChannel()->getSelectedEndpoint();
    if ($endpoint !== null) {
        echo "\nEndpoint Information:\n";
        echo "  URL: {$endpoint->endpointUrl}\n";
        echo "  Security Mode: {$endpoint->securityMode->name}\n";
        echo "  Security Policy: {$endpoint->securityPolicy->value}\n";

        echo "\nSupported User Identity Tokens:\n";
        foreach ($endpoint->userIdentityTokens as $token) {
            echo "  - PolicyId: '{$token->policyId}' | Type: {$token->tokenType->name}\n";
        }
    }

    // Browse the Objects folder (standard NodeId ns=0;i=85)
    echo "\nBrowsing Objects folder...\n";
    $objectsFolderId = NodeId::numeric(0, 85);
    $browseResult = $session->browse(BrowseDescription::create($objectsFolderId));

    echo "\nFound " . count($browseResult->references) . " references:\n";
    foreach ($browseResult->references as $reference) {
        $displayName = $reference->displayName->text ?? $reference->browseName->name ?? 'Unknown';
        $nodeId = $reference->nodeId->nodeId->toString();
        echo "  • {$displayName} (NodeId: {$nodeId})\n";
    }

    // Check for continuation point
    if ($browseResult->continuationPoint !== null && $browseResult->continuationPoint !== '') {
        echo "\n📋 Continuation point present - more results available via BrowseNext\n";

        // Use BrowseNext to get more results
        $nextResults = $session->browseNext([$browseResult->continuationPoint]);
        if (!empty($nextResults)) {
            echo "   Retrieved " . count($nextResults[0]->references) . " additional references\n";

            // Release the continuation point if there are more
            if ($nextResults[0]->continuationPoint !== null && $nextResults[0]->continuationPoint !== '') {
                $session->browseNext([$nextResults[0]->continuationPoint], releaseContinuationPoints: true);
                echo "   Released continuation point\n";
            }
        }
    } else {
        echo "\n✓ All results returned (no continuation point)\n";
    }

    // Browse the Server node
    echo "\nBrowsing Server node (ns=0;i=2253)...\n";
    $serverNodeId = NodeId::numeric(0, 2253);
    $serverBrowseResult = $session->browse(BrowseDescription::create($serverNodeId));

    echo "Found " . count($serverBrowseResult->references) . " references under Server node\n";

    // Show first few server references
    $displayCount = min(5, count($serverBrowseResult->references));
    echo "\nFirst {$displayCount} Server node children:\n";
    for ($i = 0; $i < $displayCount; $i++) {
        $reference = $serverBrowseResult->references[$i];
        $displayName = $reference->displayName->text ?? $reference->browseName->name ?? 'Unknown';
        echo "  " . ($i + 1) . ". {$displayName}\n";
    }

    echo "\n✓ Browse operations completed successfully\n";

} catch (Throwable $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    if (method_exists($e, 'getTraceAsString')) {
        echo "\nStack trace:\n{$e->getTraceAsString()}\n";
    }
} finally {
    $session->close();
    $client->disconnect();
    echo "\nConnection closed.\n";
}
