<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * ViewDescription - Defines a view
 */
final readonly class ViewDescription implements IEncodeable
{
    public function __construct(
        public NodeId $viewId,
        public int $timestamp,
        public int $viewVersion,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->viewId->encode($encoder);
        $encoder->writeUInt64($this->timestamp);
        $encoder->writeUInt32($this->viewVersion);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $viewId = NodeId::decode($decoder);
        $timestamp = $decoder->readUInt64();
        $viewVersion = $decoder->readUInt32();

        return new self(
            viewId: $viewId,
            timestamp: $timestamp,
            viewVersion: $viewVersion,
        );
    }
}
