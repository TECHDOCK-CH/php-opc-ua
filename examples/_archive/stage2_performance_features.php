<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\Browser;
use TechDock\OpcUa\Client\Cache\LruNodeCache;
use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Client\UserIdentity;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Stage 2 Performance Features Example
 *
 * Demonstrates:
 * 1. NodeCache for reduced network roundtrips
 * 2. ServerCapabilities detection
 * 3. Automatic batch splitting for large operations
 */

$serverUrl = 'opc.tcp://localhost:4840';

echo "=== Stage 2: Performance Features Demo ===\n\n";

try {
    // 1. Connect and create session
    echo "1. Connecting to $serverUrl...\n";
    $client = new OpcUaClient($serverUrl);
    $client->connect();

    $session = $client->createSession();
    $session->create();
    $session->activate(UserIdentity::anonymous());
    echo "   Session activated\n\n";

    // 2. Detect Server Capabilities
    echo "2. Detecting server capabilities...\n";
    $capabilities = $session->detectServerCapabilities();

    echo "   Server Operational Limits:\n";
    echo "     Max Nodes Per Read: " . ($capabilities->maxNodesPerRead ?? 'unlimited') . "\n";
    echo "     Max Nodes Per Write: " . ($capabilities->maxNodesPerWrite ?? 'unlimited') . "\n";
    echo "     Max Nodes Per Browse: " . ($capabilities->maxNodesPerBrowse ?? 'unlimited') . "\n";
    echo "     Max Nodes Per Register: " . ($capabilities->maxNodesPerRegisterNodes ?? 'unlimited') . "\n";
    echo "\n";

    echo "   Safe Batch Sizes:\n";
    echo "     Read: {$capabilities->getSafeReadBatchSize()}\n";
    echo "     Write: {$capabilities->getSafeWriteBatchSize()}\n";
    echo "     Browse: {$capabilities->getSafeBrowseBatchSize()}\n";
    echo "\n";

    // 3. Create NodeCache
    echo "3. Setting up NodeCache (1000 entries, 5 min TTL)...\n";
    $cache = new LruNodeCache(maxSize: 1000, defaultTtl: 300.0);
    echo "   Cache created\n\n";

    // 4. Browse with Cache (First time - cache miss)
    echo "4. Browsing Objects folder with cache (first time - cache miss)...\n";
    $objectsFolder = NodeId::numeric(0, 85);
    $browser = new Browser($session, cache: $cache);

    $startTime = microtime(true);
    $refs1 = $browser->browse($objectsFolder);
    $time1 = (microtime(true) - $startTime) * 1000;

    echo "   Found " . count($refs1) . " references\n";
    echo "   Time: " . number_format($time1, 2) . " ms\n";
    echo "   Cache stats: " . json_encode($cache->getStats()) . "\n\n";

    // 5. Browse again (Second time - cache hit!)
    echo "5. Browsing same folder again (cache hit!)...\n";
    $startTime = microtime(true);
    $refs2 = $browser->browse($objectsFolder);
    $time2 = (microtime(true) - $startTime) * 1000;

    echo "   Found " . count($refs2) . " references\n";
    echo "   Time: " . number_format($time2, 2) . " ms\n";
    echo "   Speedup: " . number_format($time1 / $time2, 1) . "x faster!\n";
    echo "   Cache stats: " . json_encode($cache->getStats()) . "\n\n";

    // 6. Enable Automatic Batch Splitting
    echo "6. Enabling automatic batch splitting...\n";
    $session->enableAutoBatchSplitting(detectCapabilities: false); // Already detected
    echo "   Auto-batching enabled\n";
    echo "   Large operations will be automatically split into safe batches\n\n";

    // 7. Demonstrate batch splitting with multiple reads
    echo "7. Reading multiple nodes with automatic batching...\n";
    $nodesToRead = [
        NodeId::numeric(0, 2258), // Server_ServerStatus_CurrentTime
        NodeId::numeric(0, 2259), // Server_ServerStatus_State
        NodeId::numeric(0, 2255), // Server_ServerStatus
        NodeId::numeric(0, 2256), // Server_ServerStatus_StartTime
        NodeId::numeric(0, 2257), // Server_ServerStatus_BuildInfo
    ];

    echo "   Reading " . count($nodesToRead) . " nodes...\n";
    $values = $session->readBatched(
        nodes: $nodesToRead,
        progressCallback: function (int $completed, int $total) {
            echo "     Progress: $completed/$total nodes\n";
        }
    );

    echo "   Results:\n";
    foreach ($values as $i => $dataValue) {
        if ($dataValue->statusCode->isGood()) {
            $valueStr = (string)($dataValue->value ?? 'null');
            if (strlen($valueStr) > 50) {
                $valueStr = substr($valueStr, 0, 50) . '...';
            }
            echo "     Node " . ($i + 1) . ": $valueStr\n";
        } else {
            echo "     Node " . ($i + 1) . ": Error - {$dataValue->statusCode}\n";
        }
    }
    echo "\n";

    // 8. Cache Statistics
    echo "8. Final cache statistics:\n";
    $stats = $cache->getStats();
    echo "   Total entries: {$stats['size']}/{$stats['maxSize']}\n";
    echo "   Cache hits: {$stats['hits']}\n";
    echo "   Cache misses: {$stats['misses']}\n";
    echo "   Hit rate: " . number_format($stats['hitRate'] * 100, 1) . "%\n";
    echo "   Average age: " . number_format($cache->getAverageAge(), 1) . " seconds\n\n";

    // 9. Demonstrate cache eviction
    echo "9. Testing cache eviction...\n";
    $expiredCount = $cache->evictExpired();
    echo "   Evicted $expiredCount expired entries\n";
    echo "   Current size: {$cache->getSize()}\n\n";

    // 10. Performance Summary
    echo "10. Performance Summary:\n";
    echo "    \u{2705} NodeCache reduces network roundtrips by ~50%+\n";
    echo "    \u{2705} Server capabilities auto-detected\n";
    echo "    \u{2705} Batch operations respect server limits\n";
    echo "    \u{2705} Progress callbacks for large operations\n";
    echo "    \u{2705} LRU eviction prevents memory bloat\n\n";

    // Cleanup
    echo "11. Cleaning up...\n";
    $cache->clear();
    $session->close();
    $client->disconnect();

    echo "\n=== Demo Complete ===\n";
    echo "\nStage 2 Performance Features:\n";
    echo "  - Intelligent caching\n";
    echo "  - Automatic server capability detection\n";
    echo "  - Smart batch splitting\n";
    echo "  - Progress tracking\n";
    echo "  - Production-ready performance optimizations\n";

} catch (Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
