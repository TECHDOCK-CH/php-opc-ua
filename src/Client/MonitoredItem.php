<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use RuntimeException;
use TechDock\OpcUa\Core\Types\AggregateFilter;
use TechDock\OpcUa\Core\Types\DataChangeFilter;
use TechDock\OpcUa\Core\Types\DataValue;
use TechDock\OpcUa\Core\Types\EventFieldList;
use TechDock\OpcUa\Core\Types\EventFilter;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\MonitoredItemCreateRequest;
use TechDock\OpcUa\Core\Types\MonitoredItemCreateResult;
use TechDock\OpcUa\Core\Types\MonitoringMode;
use TechDock\OpcUa\Core\Types\MonitoringParameters;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\ReadValueId;
use TechDock\OpcUa\Core\Types\StatusCode;

/**
 * MonitoredItem - represents a monitored item within a subscription.
 *
 * Tracks client and server state for a single monitored item.
 */
final class MonitoredItem
{
    private static int $nextClientHandle = 1;

    private int $clientHandle;
    private ?int $monitoredItemId = null;
    private bool $created = false;
    private StatusCode $statusCode;
    private float $revisedSamplingInterval = 0.0;
    private int $revisedQueueSize = 0;

    /** @var DataValue[] */
    private array $valueCache = [];
    private int $maxCacheSize = 10;

    /** @var callable|null Callback: function(MonitoredItem, DataValue): void */
    private $onNotification = null;

    /** @var callable|null Callback for events: function(MonitoredItem, EventFieldList): void */
    private $onEventNotification = null;

    public function __construct(
        private readonly ReadValueId $itemToMonitor,
        private readonly MonitoringMode $monitoringMode,
        private readonly float $samplingInterval,
        private readonly int $queueSize = 1,
        private readonly bool $discardOldest = true,
        private readonly DataChangeFilter|EventFilter|AggregateFilter|null $filter = null,
    ) {
        $this->clientHandle = self::$nextClientHandle++;
        $this->statusCode = StatusCode::good();
    }

    /**
     * Create a monitored item for a node's value attribute.
     */
    public static function forValue(
        NodeId $nodeId,
        float $samplingInterval = 0.0,
        MonitoringMode $monitoringMode = MonitoringMode::Reporting,
        int $queueSize = 1,
    ): self {
        return new self(
            itemToMonitor: ReadValueId::attribute($nodeId, attributeId: 13),
            monitoringMode: $monitoringMode,
            samplingInterval: $samplingInterval,
            queueSize: $queueSize,
        );
    }

    /**
     * Create a monitored item with a data change filter
     *
     * @param NodeId $nodeId Node to monitor
     * @param DataChangeFilter $filter Data change filter (deadband, trigger)
     * @param float $samplingInterval Sampling interval (0 = fastest practical)
     * @param int $queueSize Queue size for values
     */
    public static function withDataChangeFilter(
        NodeId $nodeId,
        DataChangeFilter $filter,
        float $samplingInterval = 0.0,
        MonitoringMode $monitoringMode = MonitoringMode::Reporting,
        int $queueSize = 1,
    ): self {
        return new self(
            itemToMonitor: ReadValueId::attribute($nodeId, attributeId: 13), // Value
            monitoringMode: $monitoringMode,
            samplingInterval: $samplingInterval,
            queueSize: $queueSize,
            discardOldest: true,
            filter: $filter,
        );
    }

    /**
     * Create a monitored item for event notifications.
     *
     * @param NodeId $nodeId Node to monitor for events (e.g., Server node)
     * @param EventFilter $filter Event filter specifying which events and fields
     * @param float $samplingInterval Sampling interval (0 = fastest practical)
     * @param int $queueSize Queue size for events
     */
    public static function forEvents(
        NodeId $nodeId,
        EventFilter $filter,
        float $samplingInterval = 0.0,
        MonitoringMode $monitoringMode = MonitoringMode::Reporting,
        int $queueSize = 1000,
    ): self {
        return new self(
            itemToMonitor: ReadValueId::attribute($nodeId, attributeId: 12), // EventNotifier
            monitoringMode: $monitoringMode,
            samplingInterval: $samplingInterval,
            queueSize: $queueSize,
            discardOldest: true,
            filter: $filter,
        );
    }

    /**
     * Create a monitored item with an aggregate filter
     *
     * @param NodeId $nodeId Node to monitor
     * @param AggregateFilter $filter Aggregate filter (function, interval)
     * @param float $samplingInterval Sampling interval (0 = fastest practical)
     * @param int $queueSize Queue size for aggregate values
     */
    public static function withAggregateFilter(
        NodeId $nodeId,
        AggregateFilter $filter,
        float $samplingInterval = 0.0,
        MonitoringMode $monitoringMode = MonitoringMode::Reporting,
        int $queueSize = 1,
    ): self {
        return new self(
            itemToMonitor: ReadValueId::attribute($nodeId, attributeId: 13), // Value
            monitoringMode: $monitoringMode,
            samplingInterval: $samplingInterval,
            queueSize: $queueSize,
            discardOldest: true,
            filter: $filter,
        );
    }

    /**
     * Set callback for data change notifications.
     *
     * @param callable $callback function(MonitoredItem, DataValue): void
     */
    public function setNotificationCallback(callable $callback): void
    {
        $this->onNotification = $callback;
    }

    /**
     * Set callback for event notifications.
     *
     * @param callable $callback function(MonitoredItem, EventFieldList): void
     */
    public function setEventNotificationCallback(callable $callback): void
    {
        $this->onEventNotification = $callback;
    }

    /**
     * Get the create request for this monitored item.
     */
    public function getCreateRequest(): MonitoredItemCreateRequest
    {
        $filterExtension = null;
        if ($this->filter !== null) {
            // Determine TypeId based on filter type
            $typeId = match (true) {
                $this->filter instanceof EventFilter => NodeId::numeric(0, 725), // EventFilter
                $this->filter instanceof DataChangeFilter => NodeId::numeric(0, 722), // DataChangeFilter
                default => NodeId::numeric(0, 728), // AggregateFilter
            };

            $filterExtension = ExtensionObject::fromEncodeable(
                $typeId,
                $this->filter
            );
        }

        return new MonitoredItemCreateRequest(
            itemToMonitor: $this->itemToMonitor,
            monitoringMode: $this->monitoringMode,
            requestedParameters: new MonitoringParameters(
                clientHandle: $this->clientHandle,
                samplingInterval: $this->samplingInterval,
                filter: $filterExtension,
                queueSize: $this->queueSize,
                discardOldest: $this->discardOldest,
            ),
        );
    }

    /**
     * Set the result from server after creation.
     */
    public function setCreateResult(MonitoredItemCreateResult $result): void
    {
        $this->statusCode = $result->statusCode;
        $this->monitoredItemId = $result->monitoredItemId;
        $this->revisedSamplingInterval = $result->revisedSamplingInterval;
        $this->revisedQueueSize = $result->revisedQueueSize;
        $this->created = $this->statusCode->isGood();
    }

    /**
     * Save a notification value to cache and invoke callback.
     *
     * Called by Subscription when notifications arrive.
     */
    public function saveNotification(DataValue $value): void
    {
        // Add to cache
        $this->valueCache[] = $value;

        // Trim cache if needed
        if (count($this->valueCache) > $this->maxCacheSize) {
            array_shift($this->valueCache);
        }

        // Invoke callback if set
        if ($this->onNotification !== null) {
            ($this->onNotification)($this, $value);
        }
    }

    /**
     * Save an event notification and invoke callback.
     *
     * Called by Subscription when event notifications arrive.
     */
    public function saveEventNotification(EventFieldList $event): void
    {
        // Invoke callback if set
        if ($this->onEventNotification !== null) {
            ($this->onEventNotification)($this, $event);
        }
    }

    /**
     * Get the latest cached value.
     */
    public function getLastValue(): ?DataValue
    {
        if ($this->valueCache === []) {
            return null;
        }

        return $this->valueCache[array_key_last($this->valueCache)];
    }

    /**
     * Get all cached values and clear the cache.
     *
     * @return DataValue[]
     */
    public function dequeueValues(): array
    {
        $values = $this->valueCache;
        $this->valueCache = [];
        return $values;
    }

    /**
     * Get the client handle for this item.
     */
    public function getClientHandle(): int
    {
        return $this->clientHandle;
    }

    /**
     * Get the server-assigned monitored item ID.
     */
    public function getMonitoredItemId(): ?int
    {
        return $this->monitoredItemId;
    }

    /**
     * Check if the item was successfully created on the server.
     */
    public function isCreated(): bool
    {
        return $this->created;
    }

    /**
     * Get the status code from the last operation.
     */
    public function getStatusCode(): StatusCode
    {
        return $this->statusCode;
    }

    /**
     * Get the server-revised sampling interval.
     */
    public function getRevisedSamplingInterval(): float
    {
        return $this->revisedSamplingInterval;
    }

    /**
     * Get the server-revised queue size.
     */
    public function getRevisedQueueSize(): int
    {
        return $this->revisedQueueSize;
    }

    /**
     * Get the node being monitored.
     */
    public function getNodeId(): NodeId
    {
        return $this->itemToMonitor->nodeId;
    }

    /**
     * Get the attribute being monitored.
     */
    public function getAttributeId(): int
    {
        return $this->itemToMonitor->attributeId;
    }

    /**
     * Check if this is an event monitored item.
     */
    public function isEventItem(): bool
    {
        return $this->filter instanceof EventFilter;
    }

    /**
     * Get the event filter (if this is an event item).
     */
    public function getEventFilter(): ?EventFilter
    {
        return $this->filter instanceof EventFilter ? $this->filter : null;
    }

    /**
     * Get the data change filter (if this item has one).
     */
    public function getDataChangeFilter(): ?DataChangeFilter
    {
        return $this->filter instanceof DataChangeFilter ? $this->filter : null;
    }

    /**
     * Get the aggregate filter (if this item has one).
     */
    public function getAggregateFilter(): ?AggregateFilter
    {
        return $this->filter instanceof AggregateFilter ? $this->filter : null;
    }

    /**
     * Get the filter (any type).
     */
    public function getFilter(): DataChangeFilter|EventFilter|AggregateFilter|null
    {
        return $this->filter;
    }
}
