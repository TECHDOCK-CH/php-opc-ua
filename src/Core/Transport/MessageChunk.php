<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

/**
 * Represents a single OPC UA TCP message chunk.
 */
final readonly class MessageChunk
{
    public function __construct(
        public MessageHeader $header,
        public string $payload,
    ) {
    }
}
