<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ExpandedNodeId extends NodeId with namespace URI and server index
 *
 * Encoding mask bits (added to NodeId encoding byte):
 * - 0x80: Namespace URI is present
 * - 0x40: Server index is present
 */
final readonly class ExpandedNodeId implements IEncodeable
{
    public function __construct(
        public NodeId $nodeId,
        public ?string $namespaceUri = null,
        public ?int $serverIndex = null,
    ) {
        if ($serverIndex !== null && $serverIndex < 0) {
            throw new InvalidArgumentException("Server index cannot be negative, got {$serverIndex}");
        }
    }

    /**
     * Create from a NodeId
     */
    public static function fromNodeId(NodeId $nodeId): self
    {
        return new self($nodeId, null, null);
    }

    /**
     * Check if namespace URI is present
     */
    public function hasNamespaceUri(): bool
    {
        return $this->namespaceUri !== null;
    }

    /**
     * Check if server index is present
     */
    public function hasServerIndex(): bool
    {
        return $this->serverIndex !== null;
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode NodeId first to get its encoding byte
        $nodeIdEncoder = new BinaryEncoder();
        $this->nodeId->encode($nodeIdEncoder);
        $nodeIdBytes = $nodeIdEncoder->getBytes();

        // Modify the first byte to add namespace URI and server index flags
        $encodingByte = ord($nodeIdBytes[0]);

        if ($this->hasNamespaceUri()) {
            $encodingByte |= 0x80;
        }

        if ($this->hasServerIndex()) {
            $encodingByte |= 0x40;
        }

        // Write modified encoding byte
        $encoder->writeByte($encodingByte);

        // Write rest of NodeId (skip first byte)
        for ($i = 1; $i < strlen($nodeIdBytes); $i++) {
            $encoder->writeByte(ord($nodeIdBytes[$i]));
        }

        // Write namespace URI if present
        if ($this->hasNamespaceUri()) {
            $encoder->writeString($this->namespaceUri);
        }

        // Write server index if present
        if ($this->hasServerIndex() && $this->serverIndex !== null) {
            $encoder->writeUInt32($this->serverIndex);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // Read encoding byte
        $encodingByte = $decoder->readByte();

        // Check for namespace URI and server index flags
        $hasNamespaceUri = ($encodingByte & 0x80) !== 0;
        $hasServerIndex = ($encodingByte & 0x40) !== 0;

        // Clear the flags to decode as normal NodeId
        $nodeIdEncodingByte = $encodingByte & 0x3F;

        // Reconstruct NodeId bytes and decode
        $nodeIdEncoder = new BinaryEncoder();
        $nodeIdEncoder->writeByte($nodeIdEncodingByte);

        // Read the rest of NodeId based on its type
        $typeId = $nodeIdEncodingByte & 0x3F;

        if ($typeId === 0x00) {
            // Two-byte format: 1 more byte
            $nodeIdEncoder->writeByte($decoder->readByte());
        } elseif ($typeId === 0x01) {
            // Four-byte format: 3 more bytes
            $nodeIdEncoder->writeByte($decoder->readByte());
            $nodeIdEncoder->writeUInt16($decoder->readUInt16());
        } elseif ($typeId === 0x02) {
            // Numeric: 6 more bytes
            $nodeIdEncoder->writeUInt16($decoder->readUInt16());
            $nodeIdEncoder->writeUInt32($decoder->readUInt32());
        } elseif ($typeId === 0x03) {
            // String: 2 + string
            $nodeIdEncoder->writeUInt16($decoder->readUInt16());
            $nodeIdEncoder->writeString($decoder->readString());
        } elseif ($typeId === 0x04) {
            // GUID: 2 + 16
            $nodeIdEncoder->writeUInt16($decoder->readUInt16());
            $nodeIdEncoder->writeGuid($decoder->readGuid());
        } elseif ($typeId === 0x05) {
            // Opaque: 2 + bytestring
            $nodeIdEncoder->writeUInt16($decoder->readUInt16());
            $nodeIdEncoder->writeByteString($decoder->readByteString());
        } else {
            throw new RuntimeException("Invalid NodeId encoding byte: 0x" . dechex($typeId));
        }

        $nodeIdDecoder = new BinaryDecoder($nodeIdEncoder->getBytes());
        $nodeId = NodeId::decode($nodeIdDecoder);

        // Read namespace URI if present
        $namespaceUri = null;
        if ($hasNamespaceUri) {
            $namespaceUri = $decoder->readString();
        }

        // Read server index if present
        $serverIndex = null;
        if ($hasServerIndex) {
            $serverIndex = $decoder->readUInt32();
        }

        return new self($nodeId, $namespaceUri, $serverIndex);
    }

    public function toString(): string
    {
        $parts = [$this->nodeId->toString()];

        if ($this->namespaceUri !== null) {
            $parts[] = "uri={$this->namespaceUri}";
        }

        if ($this->serverIndex !== null) {
            $parts[] = "srv={$this->serverIndex}";
        }

        return implode(', ', $parts);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->nodeId->equals($other->nodeId)
            && $this->namespaceUri === $other->namespaceUri
            && $this->serverIndex === $other->serverIndex;
    }
}
