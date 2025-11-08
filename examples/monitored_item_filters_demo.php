<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Client\MonitoredItem;
use TechDock\OpcUa\Core\Types\AggregateConfiguration;
use TechDock\OpcUa\Core\Types\AggregateFilter;
use TechDock\OpcUa\Core\Types\DataChangeFilter;
use TechDock\OpcUa\Core\Types\DataChangeTrigger;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Monitored Item Filters Demo
 *
 * Demonstrates:
 * 1. DataChangeFilter with various trigger types
 * 2. Absolute deadband filtering
 * 3. Percent deadband filtering
 * 4. AggregateFilter with different aggregate functions
 * 5. Practical filter configurations for industrial monitoring
 */

$serverUrl = 'opc.tcp://localhost:4840';

echo "=== Monitored Item Filters Demo ===\n\n";

try {
    // Connect using ClientBuilder
    echo "1. Connecting to server...\n";
    $connected = ClientBuilder::create()
        ->endpoint($serverUrl)
        ->withAnonymousAuth()
        ->build();

    echo "   Connected successfully\n\n";

    // =================================================================
    // PART 1: DataChangeFilter Examples
    // =================================================================

    echo "2. DataChangeFilter Examples:\n\n";

    // Example 2a: Status-only trigger
    echo "   a) Status-Only Filter:\n";
    $statusOnlyFilter = DataChangeFilter::statusOnly();
    echo "      Description: {$statusOnlyFilter->describe()}\n";
    echo "      Use Case: Only report when status code changes (not value)\n";
    echo "      Best For: Monitoring error conditions\n\n";

    $statusOnlyItem = MonitoredItem::withDataChangeFilter(
        nodeId: NodeId::numeric(0, 2258), // Server_ServerStatus_CurrentTime
        filter: $statusOnlyFilter,
        samplingInterval: 1000.0,
    );
    echo "      Created monitored item with status-only filter\n\n";

    // Example 2b: Status + Value trigger (default)
    echo "   b) Status + Value Filter (Default):\n";
    $statusValueFilter = DataChangeFilter::statusValue();
    echo "      Description: {$statusValueFilter->describe()}\n";
    echo "      Use Case: Report when status OR value changes\n";
    echo "      Best For: Most general monitoring scenarios\n\n";

    $statusValueItem = MonitoredItem::withDataChangeFilter(
        nodeId: NodeId::numeric(0, 2259), // Server_ServerStatus_State
        filter: $statusValueFilter,
        samplingInterval: 1000.0,
    );
    echo "      Created monitored item with status+value filter\n\n";

    // Example 2c: Status + Value + Timestamp trigger
    echo "   c) Status + Value + Timestamp Filter:\n";
    $statusValueTimestampFilter = DataChangeFilter::statusValueTimestamp();
    echo "      Description: {$statusValueTimestampFilter->describe()}\n";
    echo "      Use Case: Report when status, value, OR timestamp changes\n";
    echo "      Best For: When timestamp changes are significant\n\n";

    // =================================================================
    // PART 2: Absolute Deadband Examples
    // =================================================================

    echo "3. Absolute Deadband Filtering:\n\n";

    // Example 3a: Absolute deadband of 5.0
    echo "   a) Absolute Deadband (5.0 units):\n";
    $absoluteDeadbandFilter = DataChangeFilter::absoluteDeadband(5.0);
    echo "      Description: {$absoluteDeadbandFilter->describe()}\n";
    echo "      Use Case: Only report if value changes by ±5.0 units\n";
    echo "      Best For: Temperature, pressure sensors (reduce noise)\n";
    echo "      Example: 100.2 → 100.8 (no report), 100.2 → 105.5 (report)\n\n";

    $absoluteItem = MonitoredItem::withDataChangeFilter(
        nodeId: NodeId::numeric(0, 2256), // Server_ServerStatus_StartTime
        filter: $absoluteDeadbandFilter,
        samplingInterval: 500.0,
    );
    echo "      Created monitored item with absolute deadband\n\n";

    // Example 3b: Larger absolute deadband for noisy sensors
    echo "   b) Large Absolute Deadband (100.0 units):\n";
    $largeDeadbandFilter = DataChangeFilter::absoluteDeadband(100.0);
    echo "      Description: {$largeDeadbandFilter->describe()}\n";
    echo "      Use Case: Very noisy sensors, only care about large changes\n";
    echo "      Best For: Flow meters, RPM sensors with ±50 unit noise\n\n";

    // =================================================================
    // PART 3: Percent Deadband Examples
    // =================================================================

    echo "4. Percent Deadband Filtering:\n\n";

    // Example 4a: 1% deadband
    echo "   a) Percent Deadband (1%):\n";
    $percentDeadbandFilter = DataChangeFilter::percentDeadband(1.0);
    echo "      Description: {$percentDeadbandFilter->describe()}\n";
    echo "      Use Case: Report if value changes by 1% of EURange\n";
    echo "      Best For: Analog sensors with defined ranges (0-100°C, 0-1000PSI)\n";
    echo "      Example: With EURange 0-1000, deadband = 10 units\n\n";

    $percentItem = MonitoredItem::withDataChangeFilter(
        nodeId: NodeId::numeric(0, 2255), // Server_ServerStatus
        filter: $percentDeadbandFilter,
        samplingInterval: 1000.0,
    );
    echo "      Created monitored item with 1% deadband\n\n";

    // Example 4b: 5% deadband for less sensitive monitoring
    echo "   b) Percent Deadband (5%):\n";
    $largePercentFilter = DataChangeFilter::percentDeadband(5.0);
    echo "      Description: {$largePercentFilter->describe()}\n";
    echo "      Use Case: Less sensitive monitoring, reduce notification volume\n";
    echo "      Best For: Slow-changing processes (tank levels, batch temperatures)\n\n";

    // =================================================================
    // PART 4: AggregateFilter Examples
    // =================================================================

    echo "5. AggregateFilter Examples:\n\n";

    // Example 5a: Average over 60 seconds
    echo "   a) Average Aggregate (60-second intervals):\n";
    $averageFilter = AggregateFilter::average(processingInterval: 60000.0);
    echo "      Description: {$averageFilter->describe()}\n";
    echo "      Use Case: Get average value every 60 seconds\n";
    echo "      Best For: Trending, statistical process control\n\n";

    $averageItem = MonitoredItem::withAggregateFilter(
        nodeId: NodeId::numeric(0, 2258), // Server_ServerStatus_CurrentTime
        filter: $averageFilter,
        samplingInterval: 60000.0,
    );
    echo "      Created monitored item with average aggregate\n\n";

    // Example 5b: Minimum value
    echo "   b) Minimum Aggregate (30-second intervals):\n";
    $minimumFilter = AggregateFilter::minimum(processingInterval: 30000.0);
    echo "      Description: {$minimumFilter->describe()}\n";
    echo "      Use Case: Track minimum value in each 30-second window\n";
    echo "      Best For: Quality control, alarm thresholds\n\n";

    // Example 5c: Maximum value
    echo "   c) Maximum Aggregate (30-second intervals):\n";
    $maximumFilter = AggregateFilter::maximum(processingInterval: 30000.0);
    echo "      Description: {$maximumFilter->describe()}\n";
    echo "      Use Case: Track maximum value in each 30-second window\n";
    echo "      Best For: Peak detection, capacity monitoring\n\n";

    // Example 5d: Count
    echo "   d) Count Aggregate (60-second intervals):\n";
    $countFilter = AggregateFilter::count(processingInterval: 60000.0);
    echo "      Description: {$countFilter->describe()}\n";
    echo "      Use Case: Count number of values in each 60-second window\n";
    echo "      Best For: Event counting, throughput monitoring\n\n";

    // Example 5e: Total (sum)
    echo "   e) Total Aggregate (60-second intervals):\n";
    $totalFilter = AggregateFilter::total(processingInterval: 60000.0);
    echo "      Description: {$totalFilter->describe()}\n";
    echo "      Use Case: Sum of all values in each 60-second window\n";
    echo "      Best For: Production totals, consumption tracking\n\n";

    // =================================================================
    // PART 5: Advanced AggregateConfiguration
    // =================================================================

    echo "6. Advanced Aggregate Configuration:\n\n";

    // Example 6a: Strict configuration
    echo "   a) Strict Configuration:\n";
    $strictConfig = AggregateConfiguration::strict();
    echo "      Description: {$strictConfig->describe()}\n";
    echo "      Use Case: Require all data to be good quality\n";
    echo "      Best For: Critical measurements, safety systems\n\n";

    // Example 6b: Lenient configuration
    echo "   b) Lenient Configuration (70% good data):\n";
    $lenientConfig = AggregateConfiguration::lenient(percentDataGood: 70);
    echo "      Description: {$lenientConfig->describe()}\n";
    echo "      Use Case: Accept aggregates with some bad data\n";
    echo "      Best For: Unreliable sensors, trend analysis\n\n";

    $lenientFilter = AggregateFilter::create(
        aggregateType: NodeId::numeric(0, 2341), // Average
        processingInterval: 60000.0,
        config: $lenientConfig,
    );
    echo "      Created average aggregate with lenient configuration\n\n";

    // Example 6c: Custom configuration
    echo "   c) Custom Configuration:\n";
    $customConfig = AggregateConfiguration::custom(
        treatUncertainAsBad: false,
        percentDataBad: 25,
        percentDataGood: 80,
        useSlopedExtrapolation: true,
    );
    echo "      Description: {$customConfig->describe()}\n";
    echo "      Use Case: Custom quality thresholds\n";
    echo "      Best For: Application-specific requirements\n\n";

    // =================================================================
    // PART 6: Practical Industrial Examples
    // =================================================================

    echo "7. Practical Industrial Filter Configurations:\n\n";

    echo "   a) Temperature Monitoring (±1°C deadband):\n";
    $tempFilter = DataChangeFilter::absoluteDeadband(
        absoluteDeadband: 1.0,
        trigger: DataChangeTrigger::StatusValue,
    );
    echo "      Filter: {$tempFilter->describe()}\n";
    echo "      Prevents notifications for ±1°C noise\n\n";

    echo "   b) Tank Level Monitoring (2% deadband):\n";
    $tankFilter = DataChangeFilter::percentDeadband(
        percentDeadband: 2.0,
        trigger: DataChangeTrigger::StatusValue,
    );
    echo "      Filter: {$tankFilter->describe()}\n";
    echo "      Reports changes of 2% or more (e.g., 2cm in 1m tank)\n\n";

    echo "   c) Motor Speed Monitoring (average RPM over 10s):\n";
    $motorFilter = AggregateFilter::average(processingInterval: 10000.0);
    echo "      Filter: {$motorFilter->describe()}\n";
    echo "      Smooth out RPM fluctuations for trending\n\n";

    echo "   d) Production Counter (count over 1 minute):\n";
    $productionFilter = AggregateFilter::count(processingInterval: 60000.0);
    echo "      Filter: {$productionFilter->describe()}\n";
    echo "      Track production rate (items/minute)\n\n";

    echo "   e) Alarm Monitoring (status-only):\n";
    $alarmFilter = DataChangeFilter::statusOnly();
    echo "      Filter: {$alarmFilter->describe()}\n";
    echo "      Only care when alarm status changes\n\n";

    // Cleanup
    echo "8. Cleaning up...\n";
    $connected->disconnect();
    echo "   Disconnected\n\n";

    // =================================================================
    // Summary
    // =================================================================

    echo "=== Demo Complete ===\n\n";

    echo "Filter Types Demonstrated:\n";
    echo "  1. DataChangeFilter:\n";
    echo "     - Status-only trigger\n";
    echo "     - Status + Value trigger (default)\n";
    echo "     - Status + Value + Timestamp trigger\n";
    echo "  2. Deadband Filtering:\n";
    echo "     - Absolute deadband (fixed units)\n";
    echo "     - Percent deadband (% of EURange)\n";
    echo "  3. AggregateFilter:\n";
    echo "     - Average, Minimum, Maximum\n";
    echo "     - Count, Total (sum)\n";
    echo "     - Custom aggregate configuration\n\n";

    echo "Key Benefits:\n";
    echo "  - Reduce notification volume by 50-90%\n";
    echo "  - Filter out sensor noise and insignificant changes\n";
    echo "  - Statistical aggregation for trending\n";
    echo "  - Quality-aware aggregate calculations\n";
    echo "  - Bandwidth optimization for large-scale deployments\n\n";

    echo "Best Practices:\n";
    echo "  - Use absolute deadband for sensors with fixed noise levels\n";
    echo "  - Use percent deadband for sensors with scaled ranges\n";
    echo "  - Use status-only for digital/alarm values\n";
    echo "  - Use aggregates for trending and statistics\n";
    echo "  - Start with conservative deadbands, tune based on data\n";

} catch (Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
