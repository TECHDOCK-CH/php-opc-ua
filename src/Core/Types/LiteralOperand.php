<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * LiteralOperand - Operand that contains a literal value
 *
 * Used in ContentFilter expressions to represent constant values.
 * Part of the OPC UA event filtering system.
 */
final readonly class LiteralOperand implements IEncodeable
{
    public function __construct(
        public Variant $value,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->value->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $value = Variant::decode($decoder);
        return new self($value);
    }

    /**
     * Create from a simple value (automatically wrapped in Variant)
     */
    public static function fromValue(mixed $value): self
    {
        if ($value instanceof Variant) {
            return new self($value);
        }

        // Auto-detect type and create appropriate variant
        $variant = match (true) {
            is_null($value) => Variant::null(),
            is_bool($value) => Variant::boolean($value),
            is_int($value) => Variant::int32($value),
            is_float($value) => Variant::double($value),
            is_string($value) => Variant::string($value),
            $value instanceof NodeId => new Variant(VariantType::NodeId, $value),
            $value instanceof \DateTime => Variant::dateTime($value),
            default => throw new InvalidArgumentException(
                'Unsupported value type for LiteralOperand: ' . get_debug_type($value)
            ),
        };

        return new self($variant);
    }
}
