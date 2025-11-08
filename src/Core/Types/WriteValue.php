<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * Describes a single value to write to a node attribute.
 */
final readonly class WriteValue implements IEncodeable
{
    public function __construct(
        public NodeId $nodeId,
        public int $attributeId,
        public ?string $indexRange,
        public DataValue $value,
    ) {
    }

    /**
     * Create a WriteValue for the Value attribute (default attributeId = 13).
     */
    public static function forValue(
        NodeId $nodeId,
        DataValue $value,
        int $attributeId = 13,
        ?string $indexRange = null,
    ): self {
        return new self($nodeId, $attributeId, $indexRange, $value);
    }

    /**
     * Convenience helper accepting a Variant directly.
     */
    public static function fromVariant(
        NodeId $nodeId,
        Variant $variant,
        int $attributeId = 13,
        ?string $indexRange = null,
    ): self {
        return new self(
            nodeId: $nodeId,
            attributeId: $attributeId,
            indexRange: $indexRange,
            value: DataValue::fromVariant($variant),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->nodeId->encode($encoder);
        $encoder->writeUInt32($this->attributeId);
        $encoder->writeString($this->indexRange);
        $this->value->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $nodeId = NodeId::decode($decoder);
        $attributeId = $decoder->readUInt32();
        $indexRange = $decoder->readString();
        $value = DataValue::decode($decoder);

        return new self(
            nodeId: $nodeId,
            attributeId: $attributeId,
            indexRange: $indexRange,
            value: $value,
        );
    }
}
