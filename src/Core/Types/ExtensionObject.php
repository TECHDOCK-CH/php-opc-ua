<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ExtensionObject contains encoded structures
 *
 * Encoding byte values:
 * - 0x00: No body is encoded
 * - 0x01: Body is binary encoded
 * - 0x02: Body is XML encoded
 */
final readonly class ExtensionObject implements IEncodeable
{
    public function __construct(
        public NodeId $typeId,
        public ?string $body,
        public int $encoding = 0x01, // Binary by default
    ) {
        if ($encoding < 0x00 || $encoding > 0x02) {
            throw new InvalidArgumentException("Invalid encoding byte: {$encoding}");
        }

        if ($encoding !== 0x00 && $body === null) {
            throw new InvalidArgumentException('Body cannot be null for non-empty encoding');
        }

        if ($encoding === 0x00 && $body !== null) {
            throw new InvalidArgumentException('Body must be null for empty encoding');
        }
    }

    /**
     * Create an empty ExtensionObject (no body)
     */
    public static function empty(NodeId $typeId): self
    {
        return new self($typeId, null, 0x00);
    }

    /**
     * Create a binary-encoded ExtensionObject
     */
    public static function binary(NodeId $typeId, string $body): self
    {
        return new self($typeId, $body, 0x01);
    }

    /**
     * Create an XML-encoded ExtensionObject
     */
    public static function xml(NodeId $typeId, string $body): self
    {
        return new self($typeId, $body, 0x02);
    }

    /**
     * Create from an encodeable object
     */
    public static function fromEncodeable(NodeId $typeId, IEncodeable $object): self
    {
        $encoder = new BinaryEncoder();
        $object->encode($encoder);
        return self::binary($typeId, $encoder->getBytes());
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode the TypeId
        $this->typeId->encode($encoder);

        // Encode the encoding byte
        $encoder->writeByte($this->encoding);

        // Encode the body
        if ($this->encoding === 0x00) {
            // No body
            return;
        }

        $encoder->writeByteString($this->body);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // Decode the TypeId
        $typeId = NodeId::decode($decoder);

        // Decode the encoding byte
        $encoding = $decoder->readByte();

        // Decode the body
        if ($encoding === 0x00) {
            return new self($typeId, null, $encoding);
        }

        $body = $decoder->readByteString();

        return new self($typeId, $body, $encoding);
    }

    /**
     * Check if this has a body
     */
    public function hasBody(): bool
    {
        return $this->encoding !== 0x00;
    }

    /**
     * Check if this is binary encoded
     */
    public function isBinary(): bool
    {
        return $this->encoding === 0x01;
    }

    /**
     * Check if this is XML encoded
     */
    public function isXml(): bool
    {
        return $this->encoding === 0x02;
    }

    /**
     * Get the body length
     */
    public function getBodyLength(): int
    {
        return $this->body !== null ? strlen($this->body) : 0;
    }

    /**
     * Get string representation
     */
    public function toString(): string
    {
        $encodingStr = match ($this->encoding) {
            0x00 => 'Empty',
            0x01 => 'Binary',
            0x02 => 'XML',
            default => 'Unknown',
        };

        $bodyLen = $this->getBodyLength();
        return "ExtensionObject({$this->typeId->toString()}, {$encodingStr}, {$bodyLen} bytes)";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check if two ExtensionObjects are equal
     */
    public function equals(self $other): bool
    {
        return $this->typeId->equals($other->typeId)
            && $this->body === $other->body
            && $this->encoding === $other->encoding;
    }
}
