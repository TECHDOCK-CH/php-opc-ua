<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Client\MonitoredItem;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Test subscription with username/password authentication.
 *
 * Monitors OPC-PLC telemetry nodes (StepUp counter, AlternatingBoolean,
 * RandomSignedInt32, SpikeData) to verify that subscriptions work correctly
 * when authenticated with username/password.
 *
 * Requires OPC-PLC running:
 *   podman-compose up -d
 */

$endpointUrl = 'opc.tcp://127.0.0.1:4840';

printf("Connecting to %s with username/password auth...\n\n", $endpointUrl);

try {
    $client = ClientBuilder::create()
        ->endpoint($endpointUrl)
        ->withAutoDiscovery()
        ->preferSecurityMode(MessageSecurityMode::None)
        ->preferSecurityPolicy(SecurityPolicy::None)
        ->withUsernameAuth('integration-user', 'integration-pass')
        ->build();

    echo "Connected and session activated.\n";

    // OPC-PLC telemetry nodes that change over time
    $nodesToMonitor = [
        'StepUp'            => NodeId::string(3, 'StepUp'),
        'AlternatingBoolean'=> NodeId::string(3, 'AlternatingBoolean'),
        'RandomSignedInt32' => NodeId::string(3, 'RandomSignedInt32'),
        'SpikeData'         => NodeId::string(3, 'SpikeData'),
    ];

    echo "\nCreating subscription...\n";
    $subscription = $client->session->createSubscription(
        publishingInterval: 1000.0,
    );
    printf("Subscription created (ID: %d, interval: %.0f ms)\n",
        $subscription->getSubscriptionId(),
        $subscription->getCurrentPublishingInterval(),
    );

    // Create monitored items
    $items = [];
    foreach ($nodesToMonitor as $name => $nodeId) {
        $item = MonitoredItem::forValue(
            nodeId: $nodeId,
            samplingInterval: 1000.0,
        );
        $item->setNotificationCallback(function (MonitoredItem $item, $value) use ($name) {
            $raw = $value->value->value ?? null;
            $display = is_scalar($raw) ? (string)$raw : gettype($raw);
            printf("  [%s] %-22s = %s\n", date('H:i:s'), $name, $display);
        });
        $items[] = $item;
        printf("  Monitoring: %s (%s)\n", $name, $nodeId->toString());
    }

    echo "\nCreating monitored items...\n";
    $subscription->createMonitoredItems($items);
    echo "Monitored items created.\n";

    // Publish loop
    echo "\nListening for notifications (10 iterations)...\n\n";
    for ($i = 0; $i < 10; $i++) {
        $client->session->publish();
        usleep(1_000_000);
    }

    echo "\nDone. Disconnecting...\n";
    $client->disconnect();
    echo "Disconnected.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\nError: {$e->getMessage()}\n");
    fwrite(STDERR, "Stack trace:\n{$e->getTraceAsString()}\n");
    exit(1);
}
