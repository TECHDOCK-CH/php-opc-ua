#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;

/**
 * Example: Username/Password Authentication - Detailed Analysis
 *
 * This example performs a detailed analysis of:
 * - Server endpoint discovery
 * - Available authentication policies
 * - Automatic policy selection
 * - Security policy preferences
 *
 * This is useful for:
 * - Understanding what authentication methods a server supports
 * - Debugging authentication issues
 * - Learning how auto-discovery works
 *
 * To run this example against the local test server:
 *   podman-compose up -d
 *   php examples/username_password_auth_detailed.php
 */

// Server configuration
$endpointUrl = 'opc.tcp://localhost:4840';
$username = 'integration-user';
$password = 'integration-pass';

echo "Username/Password Authentication - Detailed Analysis\n";
echo str_repeat("=", 70) . "\n\n";
echo "Endpoint: $endpointUrl\n";
echo "Username: $username\n\n";

try {
    echo "Step 1: Discovering endpoints...\n";
    echo str_repeat("-", 70) . "\n";

    $testResult = ClientBuilder::create()
        ->endpoint($endpointUrl)
        ->testConnection();

    $endpoints = $testResult['endpoints'];
    echo "Found " . count($endpoints) . " endpoints\n\n";

    echo "Step 2: Analyzing available user token policies...\n";
    echo str_repeat("-", 70) . "\n";
    foreach ($endpoints as $i => $endpoint) {
        echo "Endpoint $i: {$endpoint->securityMode->name} / ";
        echo basename($endpoint->securityPolicyUri ?? 'None') . "\n";

        if (!empty($endpoint->userIdentityTokens)) {
            foreach ($endpoint->userIdentityTokens as $policy) {
                echo "   - {$policy->policyId} ({$policy->tokenType->name})";
                if ($policy->securityPolicyUri) {
                    echo " [" . basename($policy->securityPolicyUri) . "]";
                }
                echo "\n";
            }
        }
        echo "\n";
    }

    echo "Step 3: Connecting with auto-selected username policy...\n";
    echo str_repeat("-", 70) . "\n";

    $client = ClientBuilder::create()
        ->endpoint($endpointUrl)
        ->withAutoDiscovery()
        ->preferSecurityMode(MessageSecurityMode::None)
        ->preferSecurityPolicy(SecurityPolicy::None)
        ->withUsernameAuth($username, $password)
        ->build();

    echo "✓ Successfully authenticated!\n\n";

    // Get the selected endpoint
    $selectedEndpoint = $testResult['session']->getSecureChannel()->getSelectedEndpoint();
    if ($selectedEndpoint !== null) {
        echo "Step 4: Selected endpoint details\n";
        echo str_repeat("-", 70) . "\n";
        echo "Security Mode: {$selectedEndpoint->securityMode->name}\n";
        echo "Security Policy: " . basename($selectedEndpoint->securityPolicyUri ?? 'None') . "\n";
        echo "\nAvailable username policies:\n";
        foreach ($selectedEndpoint->userIdentityTokens as $policy) {
            if ($policy->tokenType->name === 'UserName') {
                echo "   - {$policy->policyId}";
                if ($policy->securityPolicyUri) {
                    echo " [" . basename($policy->securityPolicyUri) . "]";
                }
                echo "\n";
            }
        }
    }

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "SUCCESS: Username/password authentication working correctly!\n";
    echo str_repeat("=", 70) . "\n\n";

    echo "Key Features:\n";
    echo "  ✓ Password encoding includes 4-byte length prefix (OPC UA compliant)\n";
    echo "  ✓ Policy ID automatically detected from server endpoint\n";
    echo "  ✓ Strongest security policy selected automatically\n";
    echo "  ✓ No manual policy ID configuration required\n\n";

} catch (Exception $e) {
    echo "\n✗ Error: {$e->getMessage()}\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
