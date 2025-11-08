<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Client\UserIdentity;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Example: Automatic vs Manual Policy ID
 *
 * This example demonstrates the difference between:
 * 1. Manual policy ID specification (old way)
 * 2. Automatic policy ID detection (new way)
 */

$serverUrl = 'opc.tcp://opcua.demo-this.com:62544/Quickstarts/AlarmConditionServer';

echo "=== Manual Policy ID Specification (Old Way) ===\n\n";

$client1 = new OpcUaClient($serverUrl);
try {
    $client1->connect();
    $session1 = $client1->createSession();
    $session1->create();

    // Get the endpoint to see what policy IDs are available
    $endpoint = $session1->getSecureChannel()->getSelectedEndpoint();
    if ($endpoint !== null) {
        echo "Available policies:\n";
        foreach ($endpoint->userIdentityTokens as $token) {
            echo "  - PolicyId: '{$token->policyId}' (Type: {$token->tokenType->name})\n";
        }
        echo "\n";
    }

    // OLD WAY: You had to manually specify the correct policyId
    echo "Activating with manual policyId='0'...\n";
    $session1->activate(UserIdentity::anonymous('0'));
    echo "✓ Session activated successfully\n\n";

    // Quick browse test
    $result = $session1->browse(BrowseDescription::create(NodeId::numeric(0, 85)));
    echo "Browse test: Found " . count($result->references) . " references\n";

} catch (Throwable $e) {
    echo "❌ Error: {$e->getMessage()}\n";
} finally {
    $session1->close();
    $client1->disconnect();
}

echo "\n" . str_repeat("=", 60) . "\n\n";

echo "=== Automatic Policy ID Detection (New Way) ===\n\n";

$client2 = new OpcUaClient($serverUrl);
try {
    $client2->connect();
    $session2 = $client2->createSession();
    $session2->create();

    // NEW WAY: No need to specify policyId - it's automatically detected!
    echo "Activating with automatic policyId detection...\n";
    $session2->activate(); // That's it! No policyId needed!
    echo "✓ Session activated successfully (policyId auto-detected)\n\n";

    // Quick browse test
    $result = $session2->browse(BrowseDescription::create(NodeId::numeric(0, 85)));
    echo "Browse test: Found " . count($result->references) . " references\n";

} catch (Throwable $e) {
    echo "❌ Error: {$e->getMessage()}\n";
} finally {
    $session2->close();
    $client2->disconnect();
}

echo "\n" . str_repeat("=", 60) . "\n\n";

echo "Key Benefits of Automatic Detection:\n";
echo "  ✓ No need to research server-specific policy IDs\n";
echo "  ✓ Works with any OPC UA server (policy ID '0', 'Anonymous', etc.)\n";
echo "  ✓ Cleaner, more maintainable code\n";
echo "  ✓ Follows OPC UA discovery best practices\n";
echo "\nNote: You can still specify a policyId manually if needed!\n";
