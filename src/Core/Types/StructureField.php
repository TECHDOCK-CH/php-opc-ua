<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * StructureField provides metadata for a field of a Structure DataType
 *
 * This describes one field within a structured data type, including its name,
 * data type, whether it's an array, and other metadata.
 */
final readonly class StructureField implements IEncodeable
{
    public function __construct(
        public string $name,
        public ?LocalizedText $description,
        public NodeId $dataType,
        public int $valueRank,
        /** @var array<int>|null array of uint32 */
        public ?array $arrayDimensions,
        public int $maxStringLength,
        public bool $isOptional,
    ) {
        if ($arrayDimensions !== null) {
            foreach ($arrayDimensions as $dimension) {
                if (!is_int($dimension) || $dimension < 0) {
                    throw new InvalidArgumentException('Array dimensions must be non-negative integers');
                }
            }
        }

        if ($maxStringLength < 0) {
            throw new InvalidArgumentException('Max string length must be non-negative');
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode name
        $encoder->writeString($this->name);

        // Encode description (LocalizedText)
        if ($this->description !== null) {
            $this->description->encode($encoder);
        } else {
            (new LocalizedText(null, null))->encode($encoder);
        }

        // Encode dataType (NodeId)
        $this->dataType->encode($encoder);

        // Encode valueRank (Int32)
        $encoder->writeInt32($this->valueRank);

        // Encode arrayDimensions (array of UInt32)
        if ($this->arrayDimensions === null) {
            $encoder->writeInt32(-1); // null array
        } else {
            $encoder->writeInt32(count($this->arrayDimensions));
            foreach ($this->arrayDimensions as $dimension) {
                $encoder->writeUInt32($dimension);
            }
        }

        // Encode maxStringLength (UInt32)
        $encoder->writeUInt32($this->maxStringLength);

        // Encode isOptional (Boolean)
        $encoder->writeBoolean($this->isOptional);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // Decode name
        $name = $decoder->readString();
        if ($name === null) {
            throw new RuntimeException('StructureField name cannot be null');
        }

        // Decode description
        $description = LocalizedText::decode($decoder);
        // Treat empty LocalizedText as null
        if ($description->locale === null && $description->text === null) {
            $description = null;
        }

        // Decode dataType
        $dataType = NodeId::decode($decoder);

        // Decode valueRank
        $valueRank = $decoder->readInt32();

        // Decode arrayDimensions
        $arrayDimensionsCount = $decoder->readInt32();
        $arrayDimensions = null;
        if ($arrayDimensionsCount >= 0) {
            $arrayDimensions = [];
            for ($i = 0; $i < $arrayDimensionsCount; $i++) {
                $arrayDimensions[] = $decoder->readUInt32();
            }
        }

        // Decode maxStringLength
        $maxStringLength = $decoder->readUInt32();

        // Decode isOptional
        $isOptional = $decoder->readBoolean();

        return new self(
            name: $name,
            description: $description,
            dataType: $dataType,
            valueRank: $valueRank,
            arrayDimensions: $arrayDimensions,
            maxStringLength: $maxStringLength,
            isOptional: $isOptional,
        );
    }

    /**
     * Check if this field is an array
     */
    public function isArray(): bool
    {
        return $this->valueRank >= 1;
    }

    /**
     * Check if this field is a scalar value
     */
    public function isScalar(): bool
    {
        return $this->valueRank === -1 || $this->valueRank === 0;
    }

    /**
     * Get string representation
     */
    public function toString(): string
    {
        $arrayInfo = $this->isArray() ? "[array]" : "";
        $optional = $this->isOptional ? " (optional)" : "";
        return "{$this->name}: {$this->dataType->toString()}{$arrayInfo}{$optional}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
