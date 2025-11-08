<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * NodeId uniquely identifies a node in the OPC UA address space
 *
 * Encoding formats (based on first byte):
 * - 0x00: Two-byte format (ns=0, identifier <= 255)
 * - 0x01: Four-byte format (ns <= 255, identifier <= 65535)
 * - 0x02: Numeric (full 32-bit identifier, 16-bit namespace)
 * - 0x03: String identifier
 * - 0x04: GUID identifier
 * - 0x05: Opaque/ByteString identifier
 */
final readonly class NodeId implements IEncodeable
{
    public function __construct(
        public int $namespaceIndex,
        public int|string $identifier,
        public NodeIdType $type,
    ) {
        if ($namespaceIndex < 0 || $namespaceIndex > 65535) {
            throw new InvalidArgumentException("Namespace index must be between 0 and 65535, got {$namespaceIndex}");
        }

        if ($type === NodeIdType::Numeric && !is_int($identifier)) {
            throw new InvalidArgumentException('Numeric NodeId must have integer identifier');
        }

        if ($type === NodeIdType::String && !is_string($identifier)) {
            throw new InvalidArgumentException('String NodeId must have string identifier');
        }

        if ($type === NodeIdType::Guid && !is_string($identifier)) {
            throw new InvalidArgumentException('GUID NodeId must have string identifier');
        }

        if ($type === NodeIdType::Opaque && !is_string($identifier)) {
            throw new InvalidArgumentException('Opaque NodeId must have string identifier');
        }
    }

    /**
     * Create a numeric NodeId (ns=X;i=Y format)
     */
    public static function numeric(int $ns, int $identifier): self
    {
        return new self($ns, $identifier, NodeIdType::Numeric);
    }

    /**
     * Create a string NodeId (ns=X;s=Y format)
     */
    public static function string(int $ns, string $identifier): self
    {
        return new self($ns, $identifier, NodeIdType::String);
    }

    /**
     * Create a GUID NodeId (ns=X;g=Y format)
     */
    public static function guid(int $ns, string $identifier): self
    {
        return new self($ns, $identifier, NodeIdType::Guid);
    }

    /**
     * Create an opaque NodeId (ns=X;b=Y format, base64 encoded)
     */
    public static function opaque(int $ns, string $identifier): self
    {
        return new self($ns, $identifier, NodeIdType::Opaque);
    }

    public function encode(BinaryEncoder $encoder): void
    {
        match ($this->type) {
            NodeIdType::Numeric => $this->encodeNumeric($encoder),
            NodeIdType::String => $this->encodeString($encoder),
            NodeIdType::Guid => $this->encodeGuid($encoder),
            NodeIdType::Opaque => $this->encodeOpaque($encoder),
        };
    }

    private function encodeNumeric(BinaryEncoder $encoder): void
    {
        $id = $this->identifier;
        assert(is_int($id));

        // Two-byte format: ns=0, id <= 255
        if ($this->namespaceIndex === 0 && $id <= 255) {
            $encoder->writeByte(0x00);
            $encoder->writeByte($id);
            return;
        }

        // Four-byte format: ns <= 255, id <= 65535
        if ($this->namespaceIndex <= 255 && $id <= 65535) {
            $encoder->writeByte(0x01);
            $encoder->writeByte($this->namespaceIndex);
            $encoder->writeUInt16($id);
            return;
        }

        // Full numeric format
        $encoder->writeByte(0x02);
        $encoder->writeUInt16($this->namespaceIndex);
        $encoder->writeUInt32($id);
    }

    private function encodeString(BinaryEncoder $encoder): void
    {
        $encoder->writeByte(0x03);
        $encoder->writeUInt16($this->namespaceIndex);
        assert(is_string($this->identifier));
        $encoder->writeString($this->identifier);
    }

    private function encodeGuid(BinaryEncoder $encoder): void
    {
        $encoder->writeByte(0x04);
        $encoder->writeUInt16($this->namespaceIndex);
        assert(is_string($this->identifier));
        $encoder->writeGuid($this->identifier);
    }

    private function encodeOpaque(BinaryEncoder $encoder): void
    {
        $encoder->writeByte(0x05);
        $encoder->writeUInt16($this->namespaceIndex);
        assert(is_string($this->identifier));
        $encoder->writeByteString($this->identifier);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $encodingByte = $decoder->readByte();

        return match ($encodingByte) {
            0x00 => self::decodeTwoByte($decoder),
            0x01 => self::decodeFourByte($decoder),
            0x02 => self::decodeNumeric($decoder),
            0x03 => self::decodeString($decoder),
            0x04 => self::decodeGuid($decoder),
            0x05 => self::decodeOpaque($decoder),
            default => throw new RuntimeException("Invalid NodeId encoding byte: 0x" . dechex($encodingByte)),
        };
    }

    private static function decodeTwoByte(BinaryDecoder $decoder): self
    {
        $identifier = $decoder->readByte();
        return new self(0, $identifier, NodeIdType::Numeric);
    }

    private static function decodeFourByte(BinaryDecoder $decoder): self
    {
        $ns = $decoder->readByte();
        $identifier = $decoder->readUInt16();
        return new self($ns, $identifier, NodeIdType::Numeric);
    }

    private static function decodeNumeric(BinaryDecoder $decoder): self
    {
        $ns = $decoder->readUInt16();
        $identifier = $decoder->readUInt32();
        return new self($ns, $identifier, NodeIdType::Numeric);
    }

    private static function decodeString(BinaryDecoder $decoder): self
    {
        $ns = $decoder->readUInt16();
        $identifier = $decoder->readString() ?? '';
        return new self($ns, $identifier, NodeIdType::String);
    }

    private static function decodeGuid(BinaryDecoder $decoder): self
    {
        $ns = $decoder->readUInt16();
        $identifier = $decoder->readGuid();
        return new self($ns, $identifier, NodeIdType::Guid);
    }

    private static function decodeOpaque(BinaryDecoder $decoder): self
    {
        $ns = $decoder->readUInt16();
        $identifier = $decoder->readByteString() ?? '';
        return new self($ns, $identifier, NodeIdType::Opaque);
    }

    /**
     * Get string representation (ns=X;i=Y format)
     */
    public function toString(): string
    {
        $prefix = match ($this->type) {
            NodeIdType::Numeric => 'i',
            NodeIdType::String => 's',
            NodeIdType::Guid => 'g',
            NodeIdType::Opaque => 'b',
        };

        $value = match ($this->type) {
            NodeIdType::Opaque => is_string($this->identifier) ? base64_encode($this->identifier) : '',
            default => (string)$this->identifier,
        };

        return "ns={$this->namespaceIndex};{$prefix}={$value}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check if two NodeIds are equal
     */
    public function equals(self $other): bool
    {
        return $this->namespaceIndex === $other->namespaceIndex
            && $this->identifier === $other->identifier
            && $this->type === $other->type;
    }

    /**
     * Check if this is a null NodeId (ns=0;i=0)
     */
    public function isNull(): bool
    {
        return $this->namespaceIndex === 0
            && $this->type === NodeIdType::Numeric
            && $this->identifier === 0;
    }
}
