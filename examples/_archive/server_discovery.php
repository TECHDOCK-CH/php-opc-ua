<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\EndpointSelector;
use TechDock\OpcUa\Client\OpcUaClient;

/**
 * Server Discovery Example
 *
 * Demonstrates how to discover OPC UA servers and select the best endpoint.
 */

$discoveryUrl = 'opc.tcp://localhost:4840';

echo "=== OPC UA Server Discovery Example ===\n\n";

try {
    // 1. Discover servers at the endpoint
    echo "1. Finding servers at $discoveryUrl...\n";
    $servers = OpcUaClient::findServers($discoveryUrl);

    echo "Found " . count($servers) . " server(s):\n\n";

    foreach ($servers as $i => $server) {
        echo "Server #" . ($i + 1) . ":\n";
        echo "  Application URI: {$server->applicationUri}\n";
        echo "  Product URI: {$server->productUri}\n";
        echo "  Application Name: {$server->applicationName->text}\n";
        echo "  Application Type: {$server->applicationType->name}\n";
        echo "  Discovery URLs:\n";
        foreach ($server->discoveryUrls as $url) {
            echo "    - $url\n";
        }
        echo "\n";
    }

    // 2. Get available endpoints from the first server
    if ($servers !== []) {
        $serverUrl = $servers[0]->discoveryUrls[0] ?? $discoveryUrl;
        echo "2. Getting endpoints from $serverUrl...\n";

        $client = new OpcUaClient($serverUrl);
        $client->connect();

        try {
            $endpoints = $client->getSecureChannel()?->getEndpoints() ?? [];
            echo "Found " . count($endpoints) . " endpoint(s):\n\n";

            foreach ($endpoints as $i => $endpoint) {
                echo "Endpoint #" . ($i + 1) . ":\n";
                echo "  URL: {$endpoint->endpointUrl}\n";
                echo "  Security Mode: {$endpoint->securityMode->name}\n";
                echo "  Security Policy: {$endpoint->securityPolicy->name()}\n";
                echo "  Security Level: {$endpoint->securityLevel}\n";
                echo "  User Tokens: " . count($endpoint->userIdentityTokens) . "\n";
                echo "\n";
            }

            // 3. Select the best endpoint automatically
            echo "3. Selecting best endpoint...\n";
            $bestEndpoint = EndpointSelector::selectBest($endpoints);
            if ($bestEndpoint !== null) {
                echo "Selected endpoint:\n";
                echo "  URL: {$bestEndpoint->endpointUrl}\n";
                echo "  Security Mode: {$bestEndpoint->securityMode->name}\n";
                echo "  Security Policy: {$bestEndpoint->securityPolicy->name()}\n";
                echo "  Security Level: {$bestEndpoint->securityLevel}\n";
                echo "\n";
            }

            // 4. Select endpoint with no security (for testing)
            echo "4. Selecting endpoint with no security...\n";
            $noSecurityEndpoint = EndpointSelector::selectNoSecurity($endpoints);
            if ($noSecurityEndpoint !== null) {
                echo "Selected no-security endpoint:\n";
                echo "  URL: {$noSecurityEndpoint->endpointUrl}\n";
                echo "  Security Mode: {$noSecurityEndpoint->securityMode->name}\n";
                echo "\n";
            }

            // 5. Sort all endpoints by security
            echo "5. All endpoints sorted by security (strongest first):\n";
            $sortedEndpoints = EndpointSelector::sortBySecurity($endpoints);
            foreach ($sortedEndpoints as $i => $endpoint) {
                echo "  " . ($i + 1) . ". {$endpoint->securityMode->name} / " .
                    "{$endpoint->securityPolicy->name()} (level {$endpoint->securityLevel})\n";
            }
            echo "\n";
        } finally {
            $client->disconnect();
        }
    }

    // 6. Discover servers on the local network (if supported by server)
    echo "6. Finding servers on network (may not be supported by all servers)...\n";
    try {
        $networkServers = OpcUaClient::findServersOnNetwork($discoveryUrl);
        echo "Found " . count($networkServers) . " network server(s):\n\n";

        foreach ($networkServers as $i => $server) {
            echo "Network Server #" . ($i + 1) . ":\n";
            echo "  Record ID: {$server->recordId}\n";
            echo "  Server Name: {$server->serverName}\n";
            echo "  Discovery URL: {$server->discoveryUrl}\n";
            echo "  Capabilities: " . implode(', ', $server->serverCapabilities) . "\n";
            echo "\n";
        }
    } catch (Throwable $e) {
        echo "FindServersOnNetwork not supported or failed: {$e->getMessage()}\n\n";
    }

    echo "=== Discovery Complete ===\n";
} catch (Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
