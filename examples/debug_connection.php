<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;

$endpointUrl = 'opc.tcp://localhost:4840';

printf("Step 1: Creating client for %s...\n", $endpointUrl);

$client = new OpcUaClient($endpointUrl, MessageSecurityMode::None);

try {
    echo "Step 2: Connecting (TCP handshake + GetEndpoints)...\n";
    $client->connect();

    echo "Step 3: Connected successfully!\n";

    $secureChannel = $client->getSecureChannel();

    if ($secureChannel === null) {
        throw new RuntimeException('Secure channel was not established.');
    }

    echo "Step 4: Checking endpoints...\n";
    $endpoints = $secureChannel->getAvailableEndpoints();
    printf("Found %d endpoint(s)\n", count($endpoints));

} catch (Throwable $e) {
    fwrite(STDERR, sprintf("ERROR at some step: %s\n", $e->getMessage()));
    fwrite(STDERR, sprintf("Stack trace:\n%s\n", $e->getTraceAsString()));
} finally {
    $client->disconnect();
}
