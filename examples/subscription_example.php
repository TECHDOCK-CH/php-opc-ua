<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\MonitoredItem;
use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Types\MonitoringMode;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Example: Using Subscriptions and Monitored Items
 *
 * This example demonstrates how to:
 * 1. Connect to an OPC UA server
 * 2. Create a session
 * 3. Create a subscription
 * 4. Add monitored items
 * 5. Receive data change notifications
 */

// Server endpoint
$endpointUrl = 'opc.tcp://localhost:4840';

try {
    echo "=== OPC UA Subscription Example ===\n\n";

    // Step 1: Create client and connect
    echo "1. Connecting to server: {$endpointUrl}\n";
    $client = new OpcUaClient($endpointUrl);
    $client->connect();
    echo "   ✓ Connected\n\n";

    // Step 2: Create and activate session
    echo "2. Creating session\n";
    $session = $client->createSession();
    $session->create();
    $session->activate();
    echo "   ✓ Session active\n\n";

    // Step 3: Create subscription
    echo "3. Creating subscription\n";
    $subscription = $session->createSubscription(
        publishingInterval: 1000.0,    // 1 second
        maxKeepAliveCount: 10,
        lifetimeCount: 10000
    );
    echo "   ✓ Subscription created (ID: {$subscription->getSubscriptionId()})\n";
    echo "   Publishing interval: {$subscription->getCurrentPublishingInterval()}ms\n\n";

    // Step 4: Create monitored items
    echo "4. Adding monitored items\n";

    // Example: Monitor server status (NodeId i=2259)
    $serverStatusItem = MonitoredItem::forValue(
        nodeId: NodeId::numeric(0, 2259),
        samplingInterval: 500.0,  // Sample every 500ms
        monitoringMode: MonitoringMode::Reporting
    );

    // Set callback for notifications
    $serverStatusItem->setNotificationCallback(function ($item, $value) {
        echo "   [ServerStatus] Value: {$value->value->value}, ";
        echo "Status: {$value->statusCode}, ";
        echo "Timestamp: {$value->sourceTimestamp}\n";
    });

    // Example: Monitor current time (NodeId i=2258)
    $currentTimeItem = MonitoredItem::forValue(
        nodeId: NodeId::numeric(0, 2258),
        samplingInterval: 1000.0,  // Sample every 1 second
        monitoringMode: MonitoringMode::Reporting
    );

    $currentTimeItem->setNotificationCallback(function ($item, $value) {
        echo "   [CurrentTime] Value: {$value->value->value}\n";
    });

    // Add items to subscription
    $subscription->createMonitoredItems([
        $serverStatusItem,
        $currentTimeItem
    ]);

    echo "   ✓ Added 2 monitored items\n";
    echo "   Server Status - ID: {$serverStatusItem->getMonitoredItemId()}\n";
    echo "   Current Time  - ID: {$currentTimeItem->getMonitoredItemId()}\n\n";

    // Step 5: Start receiving notifications
    echo "5. Starting publish loop (press Ctrl+C to stop)\n";
    echo "   Polling for notifications...\n\n";

    // Option A: Manual polling (for integration with event loops)
    /*
    while (true) {
        $session->publish();
        usleep(100_000); // 100ms
    }
    */

    // Option B: Blocking publish loop (simpler)
    $session->startPublishing(
        intervalSeconds: 0.1,      // Poll every 100ms
        maxIterations: 100         // Run 100 iterations (~10 seconds) for demo
    );

    echo "\n6. Cleaning up\n";
    $session->close();
    $client->disconnect();
    echo "   ✓ Session closed\n";
    echo "   ✓ Disconnected\n\n";

    echo "=== Example Complete ===\n";

} catch (Throwable $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
