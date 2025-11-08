<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * BrowsePathTarget - A target node that matches a browse path
 *
 * Returned by TranslateBrowsePathsToNodeIds service.
 */
final readonly class BrowsePathTarget implements IEncodeable
{
    public function __construct(
        public ExpandedNodeId $targetId,
        public int $remainingPathIndex,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->targetId->encode($encoder);
        $encoder->writeUInt32($this->remainingPathIndex);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $targetId = ExpandedNodeId::decode($decoder);
        $remainingPathIndex = $decoder->readUInt32();

        return new self(
            targetId: $targetId,
            remainingPathIndex: $remainingPathIndex,
        );
    }
}
