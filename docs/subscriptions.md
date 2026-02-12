# Subscriptions and Monitoring

Subscriptions provide efficient real-time monitoring of data changes and events without polling.

## Quick Start

```php
use TechDock\OpcUa\Core\Types\{NodeId, MonitoringMode};

// Create subscription
$subscription = $client->session->createSubscription(
    publishingInterval: 1000.0,  // 1 second
);

// Monitor a node
$nodeId = NodeId::numeric(2, 1001);
$monitoredItem = $subscription->createMonitoredItem(
    nodeId: $nodeId,
    samplingInterval: 500.0,  // Sample every 500ms
    callback: function ($value, $timestamp) {
        echo "Value changed: $value at $timestamp\n";
    }
);

// Process notifications
while (true) {
    $subscription->publishAsync();
    usleep(100000);  // 100ms
}
```

## Complete Documentation

See [examples/subscription_example.php](../examples/subscription_example.php) and [examples/subscription_advanced.php](../examples/subscription_advanced.php) for comprehensive examples.

## Key Concepts

- **Subscription**: Container for monitored items with shared publish interval
- **Monitored Item**: Single node being monitored
- **Publishing Interval**: How often server sends notifications
- **Sampling Interval**: How often server samples the data
- **Callback**: Function called when data changes

## Performance

Subscriptions are far more efficient than polling:

```php
// ❌ Polling: Wastes bandwidth
while (true) {
    $value = $client->session->read([$nodeId])[0];
    checkChange($value);
    usleep(100000);
}

// ✅ Subscription: Server notifies only on change
$subscription->createMonitoredItem($nodeId, callback: fn($v) => process($v));
```

## See Also

- [Subscription Example](../examples/subscription_example.php)
- [Advanced Subscription](../examples/subscription_advanced.php)
- [Event Monitoring](../examples/event_monitoring.php)
