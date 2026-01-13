<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * Variant can hold any OPC UA built-in data type
 *
 * Encoding byte bits:
 * - 0-5: Type (VariantType enum value)
 * - 6: Array dimensions (1 = has dimensions)
 * - 7: Array (1 = is array)
 */
final readonly class Variant implements IEncodeable
{
    /**
     * @param mixed $value The actual value (can be scalar, array, or IEncodeable)
     * @param array<int>|null $dimensions Array dimensions (null for scalars/simple arrays)
     */
    public function __construct(
        public VariantType $type,
        public mixed $value,
        public ?array $dimensions = null,
    ) {
        if ($type === VariantType::Null && $value !== null) {
            throw new InvalidArgumentException('Null variant must have null value');
        }

        if ($dimensions !== null && !is_array($value)) {
            throw new InvalidArgumentException('Dimensions can only be specified for array values');
        }
    }

    /**
     * Create a null variant
     */
    public static function null(): self
    {
        return new self(VariantType::Null, null);
    }

    /**
     * Create a boolean variant
     */
    public static function boolean(bool $value): self
    {
        return new self(VariantType::Boolean, $value);
    }

    /**
     * Create an Int32 variant
     */
    public static function int32(int $value): self
    {
        return new self(VariantType::Int32, $value);
    }

    /**
     * Create a UInt32 variant
     */
    public static function uint32(int $value): self
    {
        return new self(VariantType::UInt32, $value);
    }

    /**
     * Create a Float variant
     */
    public static function float(float $value): self
    {
        return new self(VariantType::Float, $value);
    }

    /**
     * Create a Double variant
     */
    public static function double(float $value): self
    {
        return new self(VariantType::Double, $value);
    }

    /**
     * Create a String variant
     */
    public static function string(?string $value): self
    {
        return new self(VariantType::String, $value);
    }

    /**
     * Create a DateTime variant
     */
    public static function dateTime(\DateTime $value): self
    {
        return new self(VariantType::DateTime, $value);
    }

    /**
     * Check if this is an array
     */
    public function isArray(): bool
    {
        return is_array($this->value);
    }

    /**
     * Check if this has array dimensions
     */
    public function hasDimensions(): bool
    {
        return $this->dimensions !== null;
    }

    /**
     * Check if this is null
     */
    public function isNull(): bool
    {
        return $this->type === VariantType::Null;
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Build encoding byte
        $encodingByte = $this->type->value;

        if ($this->isArray()) {
            $encodingByte |= 0x80; // Set array bit
        }

        if ($this->hasDimensions()) {
            $encodingByte |= 0x40; // Set dimensions bit
        }

        $encoder->writeByte($encodingByte);

        // Encode the value
        if ($this->type === VariantType::Null) {
            return;
        }

        if ($this->isArray()) {
            $this->encodeArray($encoder);
        } else {
            $this->encodeScalar($encoder, $this->value);
        }

        // Encode dimensions if present
        if ($this->hasDimensions() && $this->dimensions !== null) {
            $encoder->writeInt32(count($this->dimensions));
            foreach ($this->dimensions as $dim) {
                $encoder->writeInt32($dim);
            }
        }
    }

    private function encodeArray(BinaryEncoder $encoder): void
    {
        if (!is_array($this->value)) {
            throw new RuntimeException('Expected array value');
        }

        $encoder->writeInt32(count($this->value));

        foreach ($this->value as $item) {
            $this->encodeScalar($encoder, $item);
        }
    }

    private function encodeScalar(BinaryEncoder $encoder, mixed $value): void
    {
        match ($this->type) {
            VariantType::Boolean => $encoder->writeBoolean((bool)$value),
            VariantType::SByte => $encoder->writeSByte((int)$value),
            VariantType::Byte => $encoder->writeByte((int)$value),
            VariantType::Int16 => $encoder->writeInt16((int)$value),
            VariantType::UInt16 => $encoder->writeUInt16((int)$value),
            VariantType::Int32 => $encoder->writeInt32((int)$value),
            VariantType::UInt32 => $encoder->writeUInt32((int)$value),
            VariantType::Int64 => $encoder->writeInt64((int)$value),
            VariantType::UInt64 => $encoder->writeUInt64((int)$value),
            VariantType::Float => $encoder->writeFloat((float)$value),
            VariantType::Double => $encoder->writeDouble((float)$value),
            VariantType::String => $encoder->writeString(is_string($value) ? $value : null),
            VariantType::ByteString => $encoder->writeByteString(is_string($value) ? $value : null),
            VariantType::Guid => $encoder->writeGuid((string)$value),
            VariantType::DateTime => $value instanceof DateTime
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected DateTime'),
            VariantType::NodeId => $value instanceof NodeId
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected NodeId'),
            VariantType::ExpandedNodeId => $value instanceof ExpandedNodeId
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected ExpandedNodeId'),
            VariantType::StatusCode => $value instanceof StatusCode
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected StatusCode'),
            VariantType::QualifiedName => $value instanceof QualifiedName
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected QualifiedName'),
            VariantType::LocalizedText => $value instanceof LocalizedText
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected LocalizedText'),
            VariantType::ExtensionObject => $value instanceof ExtensionObject
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected ExtensionObject'),
            VariantType::DataValue => $value instanceof DataValue
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected DataValue'),
            VariantType::Variant => $value instanceof self
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected Variant'),
            VariantType::DiagnosticInfo => $value instanceof DiagnosticInfo
                ? $value->encode($encoder)
                : throw new RuntimeException('Expected DiagnosticInfo'),
            VariantType::XmlElement => $encoder->writeString(is_string($value) ? $value : null),
            default => throw new RuntimeException("Unsupported variant type: {$this->type->name}"),
        };
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $encodingByte = $decoder->readByte();

        // Extract type
        $typeValue = $encodingByte & 0x3F; // Mask off array and dimensions bits
        $type = VariantType::from($typeValue);

        // Check flags
        $isArray = ($encodingByte & 0x80) !== 0;
        $hasDimensions = ($encodingByte & 0x40) !== 0;

        // Decode value
        if ($type === VariantType::Null) {
            return new self($type, null);
        }

        $value = $isArray ? self::decodeArray($decoder, $type) : self::decodeScalar($decoder, $type);

        // Decode dimensions
        $dimensions = null;
        if ($hasDimensions) {
            $dimCount = $decoder->readInt32();
            $dimensions = [];
            for ($i = 0; $i < $dimCount; $i++) {
                $dimensions[] = $decoder->readInt32();
            }
        }

        return new self($type, $value, $dimensions);
    }

    /**
     * @return array<mixed>
     */
    private static function decodeArray(BinaryDecoder $decoder, VariantType $type): array
    {
        $length = $decoder->readInt32();
        $array = [];

        for ($i = 0; $i < $length; $i++) {
            $array[] = self::decodeScalar($decoder, $type);
        }

        return $array;
    }

    private static function decodeScalar(BinaryDecoder $decoder, VariantType $type): mixed
    {
        return match ($type) {
            VariantType::Boolean => $decoder->readBoolean(),
            VariantType::SByte => $decoder->readSByte(),
            VariantType::Byte => $decoder->readByte(),
            VariantType::Int16 => $decoder->readInt16(),
            VariantType::UInt16 => $decoder->readUInt16(),
            VariantType::Int32 => $decoder->readInt32(),
            VariantType::UInt32 => $decoder->readUInt32(),
            VariantType::Int64 => $decoder->readInt64(),
            VariantType::UInt64 => $decoder->readUInt64(),
            VariantType::Float => $decoder->readFloat(),
            VariantType::Double => $decoder->readDouble(),
            VariantType::String => $decoder->readString(),
            VariantType::ByteString => $decoder->readByteString(),
            VariantType::Guid => $decoder->readGuid(),
            VariantType::DateTime => DateTime::decode($decoder),
            VariantType::NodeId => NodeId::decode($decoder),
            VariantType::ExpandedNodeId => ExpandedNodeId::decode($decoder),
            VariantType::StatusCode => StatusCode::decode($decoder),
            VariantType::QualifiedName => QualifiedName::decode($decoder),
            VariantType::LocalizedText => LocalizedText::decode($decoder),
            VariantType::ExtensionObject => ExtensionObject::decode($decoder),
            VariantType::DataValue => DataValue::decode($decoder),
            VariantType::Variant => self::decode($decoder),
            VariantType::DiagnosticInfo => DiagnosticInfo::decode($decoder),
            VariantType::XmlElement => $decoder->readString(),
            default => throw new RuntimeException("Unsupported variant type: {$type->name}"),
        };
    }

    /**
     * Get string representation
     */
    public function toString(): string
    {
        if ($this->isNull()) {
            return 'null';
        }

        $typeStr = $this->type->name;

        if ($this->isArray()) {
            $count = is_array($this->value) ? count($this->value) : 0;
            $dimStr = $this->hasDimensions() && $this->dimensions !== null
                ? '[' . implode(',', $this->dimensions) . ']'
                : '';
            return "{$typeStr}[{$count}]{$dimStr}";
        }

        return "{$typeStr}: " . $this->valueToString();
    }

    private function valueToString(): string
    {
        if ($this->value === null) {
            return 'null';
        }

        if (is_bool($this->value)) {
            return $this->value ? 'true' : 'false';
        }

        if (is_scalar($this->value)) {
            return (string)$this->value;
        }

        if ($this->value instanceof IEncodeable) {
            return method_exists($this->value, 'toString') ? $this->value->toString() : get_class($this->value);
        }

        return gettype($this->value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
