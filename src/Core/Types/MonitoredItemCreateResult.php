<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * MonitoredItemCreateResult - result of creating a monitored item.
 */
final readonly class MonitoredItemCreateResult implements IEncodeable
{
    public function __construct(
        public StatusCode $statusCode,
        public int $monitoredItemId,
        public float $revisedSamplingInterval,
        public int $revisedQueueSize,
        public ?ExtensionObject $filterResult,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->statusCode->encode($encoder);
        $encoder->writeUInt32($this->monitoredItemId);
        $encoder->writeDouble($this->revisedSamplingInterval);
        $encoder->writeUInt32($this->revisedQueueSize);

        if ($this->filterResult === null) {
            ExtensionObject::empty(NodeId::numeric(0, 0))->encode($encoder);
        } else {
            $this->filterResult->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $statusCode = StatusCode::decode($decoder);
        $monitoredItemId = $decoder->readUInt32();
        $revisedSamplingInterval = $decoder->readDouble();
        $revisedQueueSize = $decoder->readUInt32();
        $filterResult = ExtensionObject::decode($decoder);

        return new self(
            statusCode: $statusCode,
            monitoredItemId: $monitoredItemId,
            revisedSamplingInterval: $revisedSamplingInterval,
            revisedQueueSize: $revisedQueueSize,
            filterResult: $filterResult->typeId->isNull() ? null : $filterResult,
        );
    }
}
