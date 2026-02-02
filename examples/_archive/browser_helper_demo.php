<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\Browser;
use TechDock\OpcUa\Client\BrowserOptions;
use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Demonstration of the Browser helper class
 *
 * This example shows how to use the Browser class for simplified browsing:
 * - Simple browse operations with automatic continuation handling
 * - Filtered browsing (objects only, variables only, etc.)
 * - Recursive browsing
 * - Pattern matching by browse name
 * - Fluent BrowserOptions API
 */

$endpointUrl = 'opc.tcp://localhost:4840';

printf("Connecting to %s…\n\n", $endpointUrl);

$client = new OpcUaClient($endpointUrl, MessageSecurityMode::None);

try {
    $client->connect();
    $secureChannel = $client->getSecureChannel();

    if ($secureChannel === null) {
        throw new RuntimeException('Secure channel was not established.');
    }

    echo "✓ Connected\n";
    echo "Creating session…\n";

    $session = $client->createSession();
    $session->create();
    $session->activate();

    echo "✓ Session activated\n\n";

    // Example 1: Simple browse with default options
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Example 1: Browse Objects folder (default options)\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $browser = new Browser($session);
    $objectsFolder = NodeId::numeric(0, 85); // Standard Objects folder
    $references = $browser->browse($objectsFolder);

    echo "Found " . count($references) . " references in Objects folder:\n\n";
    foreach (array_slice($references, 0, 5) as $ref) {
        printf(
            "  • %s (NodeClass: %d, NodeId: %s)\n",
            $ref->browseName->name,
            $ref->nodeClass,
            $ref->nodeId->nodeId->toString()
        );
    }

    if (count($references) > 5) {
        printf("  ... and %d more\n", count($references) - 5);
    }

    // Example 2: Browse only Variables
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Example 2: Browse Server node - Variables only\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $serverNode = NodeId::numeric(0, 2253); // Server node
    $variables = $browser->browseVariables($serverNode);

    echo "Found " . count($variables) . " variables under Server node:\n\n";
    foreach ($variables as $var) {
        printf(
            "  • %s (NodeId: %s)\n",
            $var->browseName->name,
            $var->nodeId->nodeId->toString()
        );
    }

    // Example 3: Browse only Objects
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Example 3: Browse Server node - Objects only\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $objects = $browser->browseObjects($serverNode);

    echo "Found " . count($objects) . " objects under Server node:\n\n";
    foreach ($objects as $obj) {
        printf(
            "  • %s (NodeId: %s)\n",
            $obj->browseName->name,
            $obj->nodeId->nodeId->toString()
        );
    }

    // Example 4: Using BrowserOptions fluent API
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Example 4: Custom filtering with BrowserOptions\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $options = BrowserOptions::defaults()
        ->variablesOnly()
        ->onlyForward();

    $customBrowser = Browser::withOptions($session, $options);
    $customResults = $customBrowser->browse($serverNode);

    echo "Variables under Server node (using custom options):\n";
    echo "Found " . count($customResults) . " results\n\n";

    // Example 5: Recursive browse
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Example 5: Recursive browse (depth 2)\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $allReferences = $browser->browseRecursive($serverNode, maxDepth: 2);

    echo "Recursively browsed from Server node:\n";
    echo "Total nodes found: " . count($allReferences) . "\n\n";
    echo "First 10 nodes:\n";

    $count = 0;
    foreach ($allReferences as $nodeIdStr => $ref) {
        if ($count++ >= 10) {
            break;
        }
        printf(
            "  • %s (NodeClass: %d)\n",
            $ref->browseName->name,
            $ref->nodeClass
        );
    }

    // Example 6: Find nodes by pattern
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Example 6: Find nodes matching pattern\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $matches = $browser->findByBrowseName($serverNode, pattern: "Server*", maxDepth: 2);

    echo "Nodes matching pattern 'Server*':\n";
    echo "Found " . count($matches) . " matches\n\n";

    foreach ($matches as $match) {
        printf(
            "  • %s (NodeId: %s)\n",
            $match->browseName->name,
            $match->nodeId->nodeId->toString()
        );
    }

    // Example 7: Using different browse directions
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Example 7: Browse with inverse direction\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    $optionsInverse = BrowserOptions::defaults()->onlyInverse();
    $inverseBrowser = Browser::withOptions($session, $optionsInverse);
    $inverseRefs = $inverseBrowser->browse($serverNode);

    echo "Inverse references from Server node:\n";
    echo "Found " . count($inverseRefs) . " inverse references\n\n";

    foreach (array_slice($inverseRefs, 0, 5) as $ref) {
        printf(
            "  • %s → %s (ReferenceType: %s)\n",
            $ref->browseName->name,
            $ref->displayName->text ?? 'N/A',
            $ref->referenceTypeId->toString()
        );
    }

    // Example 8: Full address space browsing options
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Example 8: Pre-configured options demonstration\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    echo "Available pre-configured options:\n";
    echo "  • BrowserOptions::defaults()         - Standard browsing\n";
    echo "  • BrowserOptions::minimal()          - Minimal fields (performance)\n";
    echo "  • BrowserOptions::fullAddressSpace() - Large address space browsing\n";
    echo "\n";

    echo "Using minimal() option (only NodeClass + BrowseName):\n";
    $minimalBrowser = Browser::withOptions($session, BrowserOptions::minimal());
    $minimalResults = $minimalBrowser->browse($objectsFolder);
    echo "Successfully browsed " . count($minimalResults) . " nodes with minimal data\n";

    echo "\n✓ All examples completed successfully!\n";

    $session->close();
} catch (Throwable $e) {
    fwrite(STDERR, "\n✗ Error: {$e->getMessage()}\n");
    fwrite(STDERR, "Stack trace:\n{$e->getTraceAsString()}\n");
    exit(1);
} finally {
    $client->disconnect();
}
