<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\StatusCode;

/**
 * BrowseResult - Result of a Browse operation
 */
final readonly class BrowseResult implements IEncodeable
{
    /**
     * @param ReferenceDescription[] $references
     */
    public function __construct(
        public StatusCode $statusCode,
        public ?string $continuationPoint,
        public array $references,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->statusCode->encode($encoder);
        $encoder->writeByteString($this->continuationPoint);

        $encoder->writeInt32(count($this->references));
        foreach ($this->references as $ref) {
            $ref->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $statusCode = StatusCode::decode($decoder);
        $continuationPoint = $decoder->readByteString();

        $count = $decoder->readArrayLength();
        $references = [];
        for ($i = 0; $i < $count; $i++) {
            $references[] = ReferenceDescription::decode($decoder);
        }

        return new self(
            statusCode: $statusCode,
            continuationPoint: $continuationPoint,
            references: $references,
        );
    }
}
