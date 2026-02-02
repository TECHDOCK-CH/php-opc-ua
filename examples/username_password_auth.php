#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;

/**
 * Example: Username/Password Authentication
 *
 * This example demonstrates how to authenticate with an OPC UA server
 * using username and password credentials. The client automatically:
 * - Discovers available endpoints
 * - Selects the appropriate username authentication policy
 * - Encrypts the password according to OPC UA specifications (with 4-byte length prefix)
 * - Prioritizes stronger security policies when multiple are available
 *
 * To run this example against the local test server:
 *   podman-compose up -d
 *   php examples/username_password_auth.php
 *
 * Server credentials are defined in podman-compose.yml:
 *   --defaultuser=integration-user
 *   --defaultpassword=integration-pass
 */

// Server configuration
$endpointUrl = 'opc.tcp://localhost:4840';
$username = 'integration-user';
$password = 'integration-pass';

echo "Username/Password Authentication Example\n";
echo str_repeat("=", 50) . "\n\n";
echo "Endpoint: $endpointUrl\n";
echo "Username: $username\n";
echo "Password: " . str_repeat('*', strlen($password)) . "\n\n";

try {
    echo "Connecting with auto-discovery...\n";

    // Create client with automatic endpoint discovery and authentication
    $client = ClientBuilder::create()
        ->endpoint($endpointUrl)
        ->withAutoDiscovery()
        ->preferSecurityMode(MessageSecurityMode::None)  // For testing - use Sign or SignAndEncrypt in production
        ->preferSecurityPolicy(SecurityPolicy::None)     // For testing - use stronger policies in production
        ->withUsernameAuth($username, $password)         // Automatically selects correct policy ID
        ->build();

    echo "✓ Successfully authenticated!\n\n";

    echo "The client is now connected and ready for OPC UA operations.\n";
    echo "Try exploring the server with browse_server.php or other examples.\n\n";

    echo str_repeat("=", 50) . "\n";
    echo "Authentication successful!\n";
    echo str_repeat("=", 50) . "\n";

} catch (Exception $e) {
    echo "\n✗ Error: {$e->getMessage()}\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
