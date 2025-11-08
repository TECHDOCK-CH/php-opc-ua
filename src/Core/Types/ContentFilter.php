<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ContentFilter - A collection of filter elements forming a filter expression tree
 *
 * Used in EventFilter WHERE clauses and Query services.
 * Elements can reference each other via ElementOperand to build complex expressions.
 */
final class ContentFilter implements IEncodeable
{
    /**
     * @param ContentFilterElement[] $elements Filter elements
     */
    public function __construct(
        public array $elements = [],
    ) {
        foreach ($elements as $element) {
            if (!$element instanceof ContentFilterElement) {
                throw new InvalidArgumentException('Elements must be ContentFilterElement instances');
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
            $elements[] = ContentFilterElement::decode($decoder);
        }

        return new self($elements);
    }

    /**
     * Add an element and return its index
     */
    public function push(ContentFilterElement $element): int
    {
        $this->elements[] = $element;
        return count($this->elements) - 1;
    }

    /**
     * Create an empty filter
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Get the number of elements
     */
    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * Check if filter is empty
     */
    public function isEmpty(): bool
    {
        return $this->elements === [];
    }
}
