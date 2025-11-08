<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * BrowsePathResult - Result of translating a single browse path
 *
 * Contains status and target nodes for one BrowsePath.
 */
final readonly class BrowsePathResult implements IEncodeable
{
    /**
     * @param StatusCode $statusCode Operation result
     * @param BrowsePathTarget[] $targets Matching target nodes
     */
    public function __construct(
        public StatusCode $statusCode,
        public array $targets,
    ) {
        foreach ($targets as $target) {
            if (!$target instanceof BrowsePathTarget) {
                throw new InvalidArgumentException('Targets must be BrowsePathTarget instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->statusCode->encode($encoder);

        $encoder->writeInt32(count($this->targets));
        foreach ($this->targets as $target) {
            $target->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $statusCode = StatusCode::decode($decoder);

        $targetCount = $decoder->readInt32();
        $targets = [];
        for ($i = 0; $i < $targetCount; $i++) {
            $targets[] = BrowsePathTarget::decode($decoder);
        }

        return new self(
            statusCode: $statusCode,
            targets: $targets,
        );
    }

    /**
     * Check if translation was successful
     */
    public function isGood(): bool
    {
        return $this->statusCode->isGood();
    }

    /**
     * Get the first target NodeId (most common case)
     */
    public function getFirstTarget(): ?NodeId
    {
        if ($this->targets === []) {
            return null;
        }

        return $this->targets[0]->targetId->nodeId;
    }
}
