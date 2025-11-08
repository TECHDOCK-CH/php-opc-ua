<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ElementOperand - References another element in the ContentFilter
 *
 * Used to build complex filter expressions by referencing other filter elements.
 * The index refers to an element in the ContentFilter's elements array.
 */
final readonly class ElementOperand implements IEncodeable
{
    public function __construct(
        public int $index,
    ) {
        if ($index < 0) {
            throw new InvalidArgumentException("Element index cannot be negative, got {$index}");
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->index);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $index = $decoder->readUInt32();
        return new self($index);
    }
}
