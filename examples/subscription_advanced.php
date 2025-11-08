<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\MonitoredItem;
use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Example: Advanced Subscription Features
 *
 * Demonstrates:
 * - Multiple subscriptions with different rates
 * - Item-level and subscription-level callbacks
 * - Value caching and dequeuing
 * - Subscription modification
 * - Manual publish control
 */

$endpointUrl = 'opc.tcp://localhost:4840';

try {
    echo "=== Advanced Subscription Example ===\n\n";

    $client = new OpcUaClient($endpointUrl);
    $client->connect();

    $session = $client->createSession();
    $session->create();
    $session->activate();

    echo "Creating two subscriptions with different rates:\n";

    // Fast subscription (100ms)
    $fastSubscription = $session->createSubscription(
        publishingInterval: 100.0,
        maxKeepAliveCount: 20
    );
    echo "  Fast: {$fastSubscription->getSubscriptionId()} ({$fastSubscription->getCurrentPublishingInterval()}ms)\n";

    // Slow subscription (1000ms)
    $slowSubscription = $session->createSubscription(
        publishingInterval: 1000.0,
        maxKeepAliveCount: 10
    );
    echo "  Slow: {$slowSubscription->getSubscriptionId()} ({$slowSubscription->getCurrentPublishingInterval()}ms)\n\n";

    // Add items to fast subscription
    $fastItem = MonitoredItem::forValue(
        nodeId: NodeId::numeric(0, 2258),  // CurrentTime
        samplingInterval: 50.0
    );
    $fastItem->setNotificationCallback(fn($item, $value) =>
        echo "[FAST] {$value->value->value}\n"
    )
    $fastSubscription->createMonitoredItems([$fastItem]);

    // Add items to slow subscription
    $slowItem = MonitoredItem::forValue(
        nodeId: NodeId::numeric(0, 2259),  // ServerStatus
        samplingInterval: 500.0
    );
    $slowItem->setNotificationCallback(fn($item, $value) =>
        echo "[SLOW] Status: {$value->value->value}\n"
    )
    $slowSubscription->createMonitoredItems([$slowItem]);

    // Set subscription-level callback
    $fastSubscription->setNotificationCallback(function ($subscription, $notification) {
        echo "  → Fast subscription received {$notification->sequenceNumber}\n";
    });

    echo "Starting publish loop for 5 seconds...\n\n";

    $session->startPublishing(
        intervalSeconds: 0.05,  // 50ms polling
        maxIterations: 100       // ~5 seconds
    );

    echo "\n\nDemonstrating value caching:\n";

    // Dequeue values
    $cachedValues = $fastItem->dequeueValues();
    echo "Cached values from fast item: " . count($cachedValues) . "\n";

    foreach (array_slice($cachedValues, -3) as $value) {
        echo "  - {$value->value->value}\n";
    }

    // Get last value only
    $lastValue = $slowItem->getLastValue();
    echo "\nLast value from slow item: {$lastValue->value->value}\n";

    echo "\n\nModifying subscription:\n";
    $fastSubscription->modify(
        requestedPublishingInterval: 200.0
    );
    echo "  Fast subscription now: {$fastSubscription->getCurrentPublishingInterval()}ms\n";

    echo "\nCleaning up...\n";
    $session->close();
    $client->disconnect();

    echo "\n=== Example Complete ===\n";

} catch (Throwable $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    echo "   {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
