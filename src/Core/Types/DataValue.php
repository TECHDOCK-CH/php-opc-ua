<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * DataValue contains a value with quality, timestamp, and other metadata
 *
 * Encoding mask bits:
 * - 0x01: Value is present
 * - 0x02: StatusCode is present
 * - 0x04: SourceTimestamp is present
 * - 0x08: ServerTimestamp is present
 * - 0x10: SourcePicoseconds is present
 * - 0x20: ServerPicoseconds is present
 */
final readonly class DataValue implements IEncodeable
{
    public function __construct(
        public ?Variant $value = null,
        public ?StatusCode $statusCode = null,
        public ?DateTime $sourceTimestamp = null,
        public ?DateTime $serverTimestamp = null,
        public ?int $sourcePicoseconds = null,
        public ?int $serverPicoseconds = null,
    ) {
        if ($sourcePicoseconds !== null && ($sourcePicoseconds < 0 || $sourcePicoseconds > 9999)) {
            throw new InvalidArgumentException("Source picoseconds must be 0-9999, got {$sourcePicoseconds}");
        }

        if ($serverPicoseconds !== null && ($serverPicoseconds < 0 || $serverPicoseconds > 9999)) {
            throw new InvalidArgumentException("Server picoseconds must be 0-9999, got {$serverPicoseconds}");
        }
    }

    /**
     * Create a DataValue with just a value
     */
    public static function fromVariant(Variant $value): self
    {
        return new self(value: $value);
    }

    /**
     * Create a DataValue with a value and status
     */
    public static function withStatus(Variant $value, StatusCode $statusCode): self
    {
        return new self(value: $value, statusCode: $statusCode);
    }

    /**
     * Create a good DataValue with Int32 value
     */
    public static function int32(int $value): self
    {
        return new self(
            value: Variant::int32($value),
            statusCode: StatusCode::good(),
        );
    }

    /**
     * Create a good DataValue with String value
     */
    public static function string(string $value): self
    {
        return new self(
            value: Variant::string($value),
            statusCode: StatusCode::good(),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Build encoding mask
        $encodingMask = 0;

        if ($this->value !== null) {
            $encodingMask |= 0x01;
        }

        if ($this->statusCode !== null) {
            $encodingMask |= 0x02;
        }

        if ($this->sourceTimestamp !== null) {
            $encodingMask |= 0x04;
        }

        if ($this->serverTimestamp !== null) {
            $encodingMask |= 0x08;
        }

        if ($this->sourcePicoseconds !== null) {
            $encodingMask |= 0x10;
        }

        if ($this->serverPicoseconds !== null) {
            $encodingMask |= 0x20;
        }

        $encoder->writeByte($encodingMask);

        // Encode fields based on mask
        if ($this->value !== null) {
            $this->value->encode($encoder);
        }

        if ($this->statusCode !== null) {
            $this->statusCode->encode($encoder);
        }

        if ($this->sourceTimestamp !== null) {
            $this->sourceTimestamp->encode($encoder);
        }

        if ($this->serverTimestamp !== null) {
            $this->serverTimestamp->encode($encoder);
        }

        if ($this->sourcePicoseconds !== null) {
            $encoder->writeUInt16($this->sourcePicoseconds);
        }

        if ($this->serverPicoseconds !== null) {
            $encoder->writeUInt16($this->serverPicoseconds);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $encodingMask = $decoder->readByte();

        $value = null;
        if (($encodingMask & 0x01) !== 0) {
            $value = Variant::decode($decoder);
        }

        $statusCode = null;
        if (($encodingMask & 0x02) !== 0) {
            $statusCode = StatusCode::decode($decoder);
        }

        $sourceTimestamp = null;
        if (($encodingMask & 0x04) !== 0) {
            $sourceTimestamp = DateTime::decode($decoder);
        }

        $serverTimestamp = null;
        if (($encodingMask & 0x08) !== 0) {
            $serverTimestamp = DateTime::decode($decoder);
        }

        $sourcePicoseconds = null;
        if (($encodingMask & 0x10) !== 0) {
            $sourcePicoseconds = $decoder->readUInt16();
        }

        $serverPicoseconds = null;
        if (($encodingMask & 0x20) !== 0) {
            $serverPicoseconds = $decoder->readUInt16();
        }

        return new self(
            value: $value,
            statusCode: $statusCode,
            sourceTimestamp: $sourceTimestamp,
            serverTimestamp: $serverTimestamp,
            sourcePicoseconds: $sourcePicoseconds,
            serverPicoseconds: $serverPicoseconds,
        );
    }

    /**
     * Check if this DataValue has good quality
     */
    public function isGood(): bool
    {
        return $this->statusCode === null || $this->statusCode->isGood();
    }

    /**
     * Get string representation
     */
    public function toString(): string
    {
        $parts = [];

        if ($this->value !== null) {
            $parts[] = "Value: {$this->value->toString()}";
        }

        if ($this->statusCode !== null) {
            $parts[] = "Status: {$this->statusCode->toString()}";
        }

        if ($this->sourceTimestamp !== null) {
            $parts[] = "SourceTime: {$this->sourceTimestamp->toString()}";
        }

        if ($this->serverTimestamp !== null) {
            $parts[] = "ServerTime: {$this->serverTimestamp->toString()}";
        }

        return 'DataValue(' . implode(', ', $parts) . ')';
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
