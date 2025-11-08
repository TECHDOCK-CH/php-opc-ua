<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * MonitoringParameters - parameters for a monitored item.
 */
final readonly class MonitoringParameters implements IEncodeable
{
    public function __construct(
        public int $clientHandle,
        public float $samplingInterval,
        public ?ExtensionObject $filter,
        public int $queueSize,
        public bool $discardOldest,
    ) {
    }

    /**
     * Create default monitoring parameters.
     */
    public static function create(
        int $clientHandle = 0,
        float $samplingInterval = 0.0,
        ?ExtensionObject $filter = null,
        int $queueSize = 1,
        bool $discardOldest = true,
    ): self {
        return new self(
            clientHandle: $clientHandle,
            samplingInterval: $samplingInterval,
            filter: $filter,
            queueSize: $queueSize,
            discardOldest: $discardOldest,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->clientHandle);
        $encoder->writeDouble($this->samplingInterval);

        if ($this->filter === null) {
            ExtensionObject::empty(NodeId::numeric(0, 0))->encode($encoder);
        } else {
            $this->filter->encode($encoder);
        }

        $encoder->writeUInt32($this->queueSize);
        $encoder->writeBoolean($this->discardOldest);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $clientHandle = $decoder->readUInt32();
        $samplingInterval = $decoder->readDouble();
        $filter = ExtensionObject::decode($decoder);
        $queueSize = $decoder->readUInt32();
        $discardOldest = $decoder->readBoolean();

        return new self(
            clientHandle: $clientHandle,
            samplingInterval: $samplingInterval,
            filter: $filter->typeId->isNull() ? null : $filter,
            queueSize: $queueSize,
            discardOldest: $discardOldest,
        );
    }
}
