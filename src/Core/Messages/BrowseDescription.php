<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * BrowseDescription - Specifies a node to browse
 */
final readonly class BrowseDescription implements IEncodeable
{
    public function __construct(
        public NodeId $nodeId,
        public BrowseDirection $browseDirection,
        public NodeId $referenceTypeId,
        public bool $includeSubtypes,
        public int $nodeClassMask,
        public int $resultMask,
    ) {
    }

    /**
     * Create a BrowseDescription with default values
     */
    public static function create(
        NodeId $nodeId,
        BrowseDirection $browseDirection = BrowseDirection::Forward,
        ?NodeId $referenceTypeId = null,
        bool $includeSubtypes = true,
        int $nodeClassMask = 0, // 0 = all node classes
        int $resultMask = 63,   // All result fields
    ): self {
        return new self(
            nodeId: $nodeId,
            browseDirection: $browseDirection,
            referenceTypeId: $referenceTypeId ?? NodeId::numeric(0, 0), // null = all reference types
            includeSubtypes: $includeSubtypes,
            nodeClassMask: $nodeClassMask,
            resultMask: $resultMask,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->nodeId->encode($encoder);
        $encoder->writeUInt32($this->browseDirection->value);
        $this->referenceTypeId->encode($encoder);
        $encoder->writeBoolean($this->includeSubtypes);
        $encoder->writeUInt32($this->nodeClassMask);
        $encoder->writeUInt32($this->resultMask);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $nodeId = NodeId::decode($decoder);
        $browseDirection = BrowseDirection::from($decoder->readUInt32());
        $referenceTypeId = NodeId::decode($decoder);
        $includeSubtypes = $decoder->readBoolean();
        $nodeClassMask = $decoder->readUInt32();
        $resultMask = $decoder->readUInt32();

        return new self(
            nodeId: $nodeId,
            browseDirection: $browseDirection,
            referenceTypeId: $referenceTypeId,
            includeSubtypes: $includeSubtypes,
            nodeClassMask: $nodeClassMask,
            resultMask: $resultMask,
        );
    }
}
