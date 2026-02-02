<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * ClientBuilder Example
 *
 * Demonstrates the fluent API for creating and configuring OPC UA clients.
 * The ClientBuilder provides a clean, intuitive way to set up clients with
 * sensible defaults and optional performance optimizations.
 */

$serverUrl = 'opc.tcp://localhost:4840';

echo "=== ClientBuilder Demo ===\n\n";

// Example 1: Minimal Configuration
echo "1. Minimal configuration (anonymous, no optimizations):\n";
try {
    $client1 = ClientBuilder::create()
        ->endpoint($serverUrl)
        ->build();

    echo "   Connected: " . ($client1->isConnected() ? 'YES' : 'NO') . "\n";

    // Read a value
    $currentTime = $client1->session->read([NodeId::numeric(0, 2258)]);
    echo "   Server time: {$currentTime[0]->value}\n";

    $client1->disconnect();
    echo "   Disconnected\n\n";
} catch (Throwable $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 2: Full Configuration with Performance Features
echo "2. Full configuration (cache + auto-batching):\n";
try {
    $client2 = ClientBuilder::create()
        ->endpoint($serverUrl)
        ->application('My Industrial App', 'urn:mycompany:industrial-app')
        ->withAnonymousAuth()
        ->withCache(maxSize: 1000)
        ->withAutoBatching()
        ->build();

    echo "   Connected: " . ($client2->isConnected() ? 'YES' : 'NO') . "\n";

    // Browse with caching
    $objectsFolder = NodeId::numeric(0, 85);
    $refs = $client2->browser->browse($objectsFolder);
    echo "   Browsed Objects folder: " . count($refs) . " references\n";

    // Check cache stats
    $cacheStats = $client2->getCacheStats();
    if ($cacheStats !== null) {
        echo "   Cache hits: {$cacheStats['hits']}, misses: {$cacheStats['misses']}\n";
    }

    // Check server capabilities
    $caps = $client2->getServerCapabilities();
    echo "   Max nodes per read: " . ($caps->maxNodesPerRead ?? 'unlimited') . "\n";

    $client2->disconnect();
    echo "   Disconnected\n\n";
} catch (Throwable $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 3: Auto-Discovery with Security Preferences
echo "3. Auto-discovery (find best endpoint automatically):\n";
try {
    $client3 = ClientBuilder::create()
        ->endpoint($serverUrl)
        ->withAutoDiscovery()
        ->withNoSecurity()  // Prefer no security for testing
        ->withAnonymousAuth()
        ->build();

    echo "   Connected: " . ($client3->isConnected() ? 'YES' : 'NO') . "\n";
    echo "   Endpoint auto-selected via discovery\n";

    $client3->disconnect();
    echo "   Disconnected\n\n";
} catch (Throwable $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 4: Test Connection (verify endpoint without full setup)
echo "4. Test connection (verify endpoint is reachable):\n";
try {
    $testResult = ClientBuilder::create()
        ->endpoint($serverUrl)
        ->testConnection();

    echo "   Connection successful!\n";
    echo "   Found " . count($testResult['endpoints']) . " endpoints\n";

    // Show available endpoints
    foreach ($testResult['endpoints'] as $i => $endpoint) {
        $securityMode = $endpoint->securityMode->name;
        $securityPolicy = basename($endpoint->securityPolicy->uri());
        echo "     Endpoint " . ($i + 1) . ": $securityMode / $securityPolicy\n";
    }

    // Clean up test connection
    $testResult['session']->close();
    $testResult['client']->disconnect();
    echo "\n";
} catch (Throwable $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 5: Username Authentication
echo "5. Username authentication:\n";
try {
    $client5 = ClientBuilder::create()
        ->endpoint($serverUrl)
        ->withUsernameAuth('admin', 'password123')
        ->build();

    echo "   Connected with username authentication\n";

    $client5->disconnect();
    echo "   Disconnected\n\n";
} catch (Throwable $e) {
    echo "   Error: {$e->getMessage()}\n";
    echo "   (This is expected if server doesn't support username auth)\n\n";
}

// Example 6: Production-Ready Configuration
echo "6. Production-ready configuration:\n";
echo "   Recommended settings for production deployments:\n";
echo "\n";
echo "   \$client = ClientBuilder::create()\n";
echo "       ->endpoint('opc.tcp://production-server:4840')\n";
echo "       ->application('Production App', 'urn:company:prod-app')\n";
echo "       ->withAutoDiscovery()              // Auto-select best endpoint\n";
echo "       ->preferSecurityMode(SignAndEncrypt) // Require encryption\n";
echo "       ->withUsernameAuth(\$user, \$pass)   // Authenticated access\n";
echo "       ->withCache(5000)                  // Large cache\n";
echo "       ->withAutoBatching()               // Handle large operations\n";
echo "       ->build();\n";
echo "\n";

echo "=== Demo Complete ===\n";
echo "\nClientBuilder Benefits:\n";
echo "  - Clean, fluent API\n";
echo "  - Sensible defaults\n";
echo "  - Automatic endpoint discovery\n";
echo "  - Optional performance features\n";
echo "  - Simplified configuration\n";
echo "  - Production-ready out of the box\n";
