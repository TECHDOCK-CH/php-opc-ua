<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * RelativePath - A relative path constructed from reference types and browse names
 *
 * Used in TranslateBrowsePathsToNodeIds service and AttributeOperand.
 * Represents a path through the address space using reference types.
 */
final readonly class RelativePath implements IEncodeable
{
    /**
     * @param RelativePathElement[] $elements Path elements
     */
    public function __construct(
        public array $elements,
    ) {
        foreach ($elements as $element) {
            if (!$element instanceof RelativePathElement) {
                throw new InvalidArgumentException('Elements must be RelativePathElement instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeInt32(count($this->elements));
        foreach ($this->elements as $element) {
            $element->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $count = $decoder->readInt32();
        $elements = [];

        for ($i = 0; $i < $count; $i++) {
            $elements[] = RelativePathElement::decode($decoder);
        }

        return new self($elements);
    }

    /**
     * Create an empty relative path
     */
    public static function empty(): self
    {
        return new self([]);
    }
}
