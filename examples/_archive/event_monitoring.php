<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\MonitoredItem;
use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Types\EventFilter;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Event Monitoring Example
 *
 * Demonstrates OPC UA event monitoring and alarm handling:
 * - Create EventFilter with field selection
 * - Monitor Server node for events
 * - Handle event notifications with callbacks
 * - Display event fields (Time, Message, Severity, etc.)
 *
 * Events and Alarms are critical for industrial monitoring systems.
 */

$endpointUrl = 'opc.tcp://localhost:4840';

printf("OPC UA Event Monitoring Example\n");
printf("Connecting to %s...\n\n", $endpointUrl);

$client = new OpcUaClient($endpointUrl, MessageSecurityMode::None);

try {
    $client->connect();
    $secureChannel = $client->getSecureChannel();

    if ($secureChannel === null) {
        throw new RuntimeException('Secure channel was not established.');
    }

    echo "✓ Connected\n";
    echo "Creating session...\n";

    $session = $client->createSession();
    $session->create();
    $session->activate();

    echo "✓ Session activated\n\n";

    // ===================================================================
    // Step 1: Create Event Filter
    // ===================================================================

    echo "Creating event filter for BaseEventType...\n";

    // Define which event type to monitor
    $baseEventTypeId = NodeId::numeric(0, 2041); // BaseEventType

    // Create filter with common event fields
    $eventFilter = EventFilter::forBaseEventType($baseEventTypeId);

    echo "Event filter created with " . count($eventFilter->selectClauses) . " select clauses:\n";
    echo "  - EventId\n";
    echo "  - EventType\n";
    echo "  - SourceName\n";
    echo "  - Time\n";
    echo "  - Message\n";
    echo "  - Severity\n";
    echo "\n";

    // Optional: Add WHERE clause to filter events
    // Example: Only show events with Severity >= 500 (Medium and above)
    /*
    $whereClause = new ContentFilter();
    $whereClause->push(
        ContentFilterElement::greaterThanOrEqual(
            SimpleAttributeOperand::fromStrings($baseEventTypeId, ['Severity']),
            LiteralOperand::fromValue(500)
        )
    );
    $eventFilter->where($whereClause);
    echo "Added WHERE clause: Severity >= 500\n\n";
    */

    // ===================================================================
    // Step 2: Create Monitored Item for Events
    // ===================================================================

    echo "Creating monitored item for Server events...\n";

    $serverNode = NodeId::numeric(0, 2253); // Server node

    $eventItem = MonitoredItem::forEvents(
        nodeId: $serverNode,
        filter: $eventFilter,
        samplingInterval: 0.0, // As fast as possible
        queueSize: 1000, // Hold up to 1000 events
    );

    // Set callback to handle events
    $eventCount = 0;
    $eventItem->setEventNotificationCallback(function ($item, $event) use (&$eventCount) {
        $eventCount++;

        printf("\n═══════════════════════════════════════════════════════════\n");
        printf("Event #%d Received (%d fields)\n", $eventCount, $event->count());
        printf("═══════════════════════════════════════════════════════════\n");

        // Parse common event fields (matching our SelectClauses order)
        // Fields correspond to: EventId, EventType, SourceName, Time, Message, Severity

        $eventId = $event->getFieldValue(0);
        $eventType = $event->getFieldValue(1);
        $sourceName = $event->getFieldValue(2);
        $time = $event->getFieldValue(3);
        $message = $event->getFieldValue(4);
        $severity = $event->getFieldValue(5);

        echo "Event Details:\n";
        printf("  EventId:    %s\n", is_string($eventId) ? bin2hex(substr($eventId, 0, 8)) . '...' : 'N/A');
        printf("  EventType:  %s\n", $eventType ?? 'N/A');
        printf("  Source:     %s\n", $sourceName ?? 'N/A');
        printf("  Time:       %s\n", $time ?? 'N/A');
        printf("  Message:    %s\n", $message ?? 'N/A');
        printf("  Severity:   %s\n", $severity ?? 'N/A');

        // Severity interpretation
        if (is_int($severity)) {
            $severityLevel = match (true) {
                $severity >= 800 => 'CRITICAL',
                $severity >= 500 => 'MEDIUM',
                $severity >= 200 => 'LOW',
                default => 'INFO',
            };
            printf("              (%s)\n", $severityLevel);
        }
    });

    echo "✓ Event callback configured\n\n";

    // ===================================================================
    // Step 3: Create Subscription and Add Monitored Item
    // ===================================================================

    echo "Creating subscription...\n";

    $subscription = $session->createSubscription(
        publishingInterval: 500.0, // 500ms
        lifetimeCount: 10000,
        maxKeepAliveCount: 10,
        publishingEnabled: true,
    );

    echo "✓ Subscription created (ID: {$subscription->getSubscriptionId()})\n";
    echo "Adding event monitored item to subscription...\n";

    $subscription->createMonitoredItems([$eventItem]);

    if ($eventItem->isCreated() && $eventItem->getStatusCode()->isGood()) {
        echo "✓ Event monitoring active (MonitoredItemId: {$eventItem->getMonitoredItemId()})\n";
    } else {
        throw new RuntimeException("Failed to create monitored item: {$eventItem->getStatusCode()}");
    }

    // ===================================================================
    // Step 4: Start Publishing Loop
    // ===================================================================

    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Monitoring events... (Press Ctrl+C to stop)\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "Waiting for events from the server...\n";
    echo "(Note: If no events appear, the server may not be generating events)\n";
    echo "\n";

    // Publish loop - runs for 60 seconds or until interrupted
    $startTime = time();
    $duration = 60; // seconds
    $iteration = 0;

    while (true) {
        $elapsed = time() - $startTime;
        if ($elapsed >= $duration) {
            echo "\nMonitoring duration reached ({$duration} seconds)\n";
            break;
        }

        // Poll for notifications
        $session->publish();

        // Sleep briefly between polls
        usleep(100000); // 100ms

        $iteration++;

        // Status update every 5 seconds
        if ($iteration % 50 === 0) {
            printf("[%ds] Still monitoring... (%d events received)\n", $elapsed, $eventCount);
        }
    }

    // ===================================================================
    // Cleanup
    // ===================================================================

    echo "\n";
    echo "Cleaning up...\n";

    $subscription->delete();
    $session->close();

    echo "✓ Subscription deleted\n";
    echo "✓ Session closed\n";
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Event Monitoring Summary\n";
    echo "═══════════════════════════════════════════════════════════\n";
    printf("Total events received: %d\n", $eventCount);
    printf("Monitoring duration:   %d seconds\n", $elapsed);

    if ($eventCount === 0) {
        echo "\nNote: No events were received. This can happen if:\n";
        echo "  - Server is not configured to generate events\n";
        echo "  - Server node does not support event notifications\n";
        echo "  - Server requires specific event setup/triggering\n";
        echo "\nConsider testing with a server that actively generates events/alarms.\n";
    }

} catch (Throwable $e) {
    fwrite(STDERR, "\n✗ Error: {$e->getMessage()}\n");
    fwrite(STDERR, "Stack trace:\n{$e->getTraceAsString()}\n");
    exit(1);
} finally {
    $client->disconnect();
    echo "\n✓ Disconnected\n";
}
