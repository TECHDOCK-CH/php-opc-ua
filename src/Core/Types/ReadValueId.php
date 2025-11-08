<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * Identifies an attribute to read from a node.
 */
final readonly class ReadValueId implements IEncodeable
{
    public function __construct(
        public NodeId $nodeId,
        public int $attributeId,
        public ?string $indexRange,
        public QualifiedName $dataEncoding,
    ) {
    }

    /**
     * Helper to create a read description for a single attribute.
     */
    public static function attribute(
        NodeId $nodeId,
        int $attributeId = 13,
        ?string $indexRange = null,
        ?QualifiedName $dataEncoding = null,
    ): self {
        return new self(
            nodeId: $nodeId,
            attributeId: $attributeId,
            indexRange: $indexRange,
            dataEncoding: $dataEncoding ?? new QualifiedName(0, ''),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->nodeId->encode($encoder);
        $encoder->writeUInt32($this->attributeId);
        $encoder->writeString($this->indexRange);
        $this->dataEncoding->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $nodeId = NodeId::decode($decoder);
        $attributeId = $decoder->readUInt32();
        $indexRange = $decoder->readString();
        $dataEncoding = QualifiedName::decode($decoder);

        return new self(
            nodeId: $nodeId,
            attributeId: $attributeId,
            indexRange: $indexRange,
            dataEncoding: $dataEncoding,
        );
    }
}
