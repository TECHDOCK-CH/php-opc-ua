<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * MonitoredItemCreateRequest - request to create a monitored item.
 */
final readonly class MonitoredItemCreateRequest implements IEncodeable
{
    public function __construct(
        public ReadValueId $itemToMonitor,
        public MonitoringMode $monitoringMode,
        public MonitoringParameters $requestedParameters,
    ) {
    }

    /**
     * Create a monitored item request for a node's value attribute.
     */
    public static function forValue(
        NodeId $nodeId,
        int $clientHandle,
        float $samplingInterval = 0.0,
        MonitoringMode $monitoringMode = MonitoringMode::Reporting,
        int $queueSize = 1,
        bool $discardOldest = true,
    ): self {
        return new self(
            itemToMonitor: ReadValueId::attribute($nodeId, attributeId: 13),
            monitoringMode: $monitoringMode,
            requestedParameters: MonitoringParameters::create(
                clientHandle: $clientHandle,
                samplingInterval: $samplingInterval,
                queueSize: $queueSize,
                discardOldest: $discardOldest,
            ),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->itemToMonitor->encode($encoder);
        $encoder->writeInt32($this->monitoringMode->value);
        $this->requestedParameters->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $itemToMonitor = ReadValueId::decode($decoder);
        $monitoringMode = MonitoringMode::from($decoder->readInt32());
        $requestedParameters = MonitoringParameters::decode($decoder);

        return new self(
            itemToMonitor: $itemToMonitor,
            monitoringMode: $monitoringMode,
            requestedParameters: $requestedParameters,
        );
    }
}
