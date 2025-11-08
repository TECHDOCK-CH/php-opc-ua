<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

/**
 * ServerCapabilities - Detected server operational limits and features
 *
 * Stores information about what the server supports and its operational limits.
 * This allows the client to auto-configure and respect server constraints.
 */
final readonly class ServerCapabilities
{
    /**
     * @param int|null $maxBrowseContinuationPoints Maximum browse continuation points
     * @param int|null $maxQueryContinuationPoints Maximum query continuation points
     * @param int|null $maxHistoryContinuationPoints Maximum history continuation points
     * @param int|null $maxArrayLength Maximum array length in a single operation
     * @param int|null $maxStringLength Maximum string length
     * @param int|null $maxByteStringLength Maximum byte string length
     * @param int|null $maxNodesPerRead Maximum nodes per read operation
     * @param int|null $maxNodesPerWrite Maximum nodes per write operation
     * @param int|null $maxNodesPerMethodCall Maximum nodes per method call
     * @param int|null $maxNodesPerBrowse Maximum nodes per browse operation
     * @param int|null $maxNodesPerRegisterNodes Maximum nodes per register operation
     * @param int|null $maxNodesPerTranslateBrowsePathsToNodeIds Maximum paths to translate
     * @param int|null $maxNodesPerNodeManagement Maximum nodes per node management operation
     * @param int|null $maxMonitoredItemsPerCall Maximum monitored items per call
     * @param string[] $serverProfileArray Server profiles supported (e.g., 'Standard UA Server')
     * @param string[] $localeIdArray Supported locale IDs
     * @param float|null $minSupportedSampleRate Minimum supported sampling rate (ms)
     * @param float|null $maxSupportedSampleRate Maximum supported sampling rate (ms)
     * @param bool $supportsAggregates Server supports aggregate functions
     * @param bool $supportsHistoryReading Server supports history read
     * @param bool $supportsHistoryUpdating Server supports history update
     * @param bool $supportsEventSubscription Server supports event subscriptions
     */
    public function __construct(
        public ?int $maxBrowseContinuationPoints = null,
        public ?int $maxQueryContinuationPoints = null,
        public ?int $maxHistoryContinuationPoints = null,
        public ?int $maxArrayLength = null,
        public ?int $maxStringLength = null,
        public ?int $maxByteStringLength = null,
        public ?int $maxNodesPerRead = null,
        public ?int $maxNodesPerWrite = null,
        public ?int $maxNodesPerMethodCall = null,
        public ?int $maxNodesPerBrowse = null,
        public ?int $maxNodesPerRegisterNodes = null,
        public ?int $maxNodesPerTranslateBrowsePathsToNodeIds = null,
        public ?int $maxNodesPerNodeManagement = null,
        public ?int $maxMonitoredItemsPerCall = null,
        public array $serverProfileArray = [],
        public array $localeIdArray = [],
        public ?float $minSupportedSampleRate = null,
        public ?float $maxSupportedSampleRate = null,
        public bool $supportsAggregates = false,
        public bool $supportsHistoryReading = false,
        public bool $supportsHistoryUpdating = false,
        public bool $supportsEventSubscription = false,
    ) {
    }

    /**
     * Get safe batch size for read operations
     *
     * Returns a conservative batch size that respects server limits.
     */
    public function getSafeReadBatchSize(): int
    {
        if ($this->maxNodesPerRead !== null && $this->maxNodesPerRead > 0) {
            return $this->maxNodesPerRead;
        }
        return 100; // Conservative default
    }

    /**
     * Get safe batch size for write operations
     */
    public function getSafeWriteBatchSize(): int
    {
        if ($this->maxNodesPerWrite !== null && $this->maxNodesPerWrite > 0) {
            return $this->maxNodesPerWrite;
        }
        return 100; // Conservative default
    }

    /**
     * Get safe batch size for browse operations
     */
    public function getSafeBrowseBatchSize(): int
    {
        if ($this->maxNodesPerBrowse !== null && $this->maxNodesPerBrowse > 0) {
            return $this->maxNodesPerBrowse;
        }
        return 100; // Conservative default
    }

    /**
     * Get safe batch size for register nodes operations
     */
    public function getSafeRegisterNodesBatchSize(): int
    {
        if ($this->maxNodesPerRegisterNodes !== null && $this->maxNodesPerRegisterNodes > 0) {
            return $this->maxNodesPerRegisterNodes;
        }
        return 100; // Conservative default
    }

    /**
     * Check if server supports a specific profile
     */
    public function supportsProfile(string $profileUri): bool
    {
        return in_array($profileUri, $this->serverProfileArray, true);
    }

    /**
     * Check if server supports a specific locale
     */
    public function supportsLocale(string $localeId): bool
    {
        return in_array($localeId, $this->localeIdArray, true);
    }

    /**
     * Create from server status node reads
     *
     * @param array<string, mixed> $capabilities Key-value pairs of capability values
     */
    public static function fromArray(array $capabilities): self
    {
        return new self(
            maxBrowseContinuationPoints: $capabilities['maxBrowseContinuationPoints'] ?? null,
            maxQueryContinuationPoints: $capabilities['maxQueryContinuationPoints'] ?? null,
            maxHistoryContinuationPoints: $capabilities['maxHistoryContinuationPoints'] ?? null,
            maxArrayLength: $capabilities['maxArrayLength'] ?? null,
            maxStringLength: $capabilities['maxStringLength'] ?? null,
            maxByteStringLength: $capabilities['maxByteStringLength'] ?? null,
            maxNodesPerRead: $capabilities['maxNodesPerRead'] ?? null,
            maxNodesPerWrite: $capabilities['maxNodesPerWrite'] ?? null,
            maxNodesPerMethodCall: $capabilities['maxNodesPerMethodCall'] ?? null,
            maxNodesPerBrowse: $capabilities['maxNodesPerBrowse'] ?? null,
            maxNodesPerRegisterNodes: $capabilities['maxNodesPerRegisterNodes'] ?? null,
            maxNodesPerTranslateBrowsePathsToNodeIds: $capabilities['maxNodesPerTranslateBrowsePathsToNodeIds'] ?? null,
            maxNodesPerNodeManagement: $capabilities['maxNodesPerNodeManagement'] ?? null,
            maxMonitoredItemsPerCall: $capabilities['maxMonitoredItemsPerCall'] ?? null,
            serverProfileArray: $capabilities['serverProfileArray'] ?? [],
            localeIdArray: $capabilities['localeIdArray'] ?? [],
            minSupportedSampleRate: $capabilities['minSupportedSampleRate'] ?? null,
            maxSupportedSampleRate: $capabilities['maxSupportedSampleRate'] ?? null,
            supportsAggregates: $capabilities['supportsAggregates'] ?? false,
            supportsHistoryReading: $capabilities['supportsHistoryReading'] ?? false,
            supportsHistoryUpdating: $capabilities['supportsHistoryUpdating'] ?? false,
            supportsEventSubscription: $capabilities['supportsEventSubscription'] ?? false,
        );
    }

    /**
     * Create minimal capabilities with defaults
     */
    public static function defaults(): self
    {
        return new self(
            maxNodesPerRead: 100,
            maxNodesPerWrite: 100,
            maxNodesPerBrowse: 100,
            maxNodesPerRegisterNodes: 100,
            maxNodesPerTranslateBrowsePathsToNodeIds: 100,
            maxMonitoredItemsPerCall: 100,
            minSupportedSampleRate: 100.0,
            supportsEventSubscription: true,
        );
    }

    /**
     * Convert to array for serialization
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'maxBrowseContinuationPoints' => $this->maxBrowseContinuationPoints,
            'maxQueryContinuationPoints' => $this->maxQueryContinuationPoints,
            'maxHistoryContinuationPoints' => $this->maxHistoryContinuationPoints,
            'maxArrayLength' => $this->maxArrayLength,
            'maxStringLength' => $this->maxStringLength,
            'maxByteStringLength' => $this->maxByteStringLength,
            'maxNodesPerRead' => $this->maxNodesPerRead,
            'maxNodesPerWrite' => $this->maxNodesPerWrite,
            'maxNodesPerMethodCall' => $this->maxNodesPerMethodCall,
            'maxNodesPerBrowse' => $this->maxNodesPerBrowse,
            'maxNodesPerRegisterNodes' => $this->maxNodesPerRegisterNodes,
            'maxNodesPerTranslateBrowsePathsToNodeIds' => $this->maxNodesPerTranslateBrowsePathsToNodeIds,
            'maxNodesPerNodeManagement' => $this->maxNodesPerNodeManagement,
            'maxMonitoredItemsPerCall' => $this->maxMonitoredItemsPerCall,
            'serverProfileArray' => $this->serverProfileArray,
            'localeIdArray' => $this->localeIdArray,
            'minSupportedSampleRate' => $this->minSupportedSampleRate,
            'maxSupportedSampleRate' => $this->maxSupportedSampleRate,
            'supportsAggregates' => $this->supportsAggregates,
            'supportsHistoryReading' => $this->supportsHistoryReading,
            'supportsHistoryUpdating' => $this->supportsHistoryUpdating,
            'supportsEventSubscription' => $this->supportsEventSubscription,
        ];
    }
}
