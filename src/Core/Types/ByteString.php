<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ByteString - A sequence of bytes
 *
 * Wrapper for byte string data with encoding/decoding support.
 */
final readonly class ByteString implements IEncodeable
{
    /**
     * @param string|null $value The byte string value (binary data)
     */
    public function __construct(
        public ?string $value,
    ) {
    }

    /**
     * Create from binary data
     */
    public static function from(?string $data): self
    {
        return new self($data);
    }

    /**
     * Create empty byte string
     */
    public static function empty(): self
    {
        return new self(null);
    }

    /**
     * Check if empty
     */
    public function isEmpty(): bool
    {
        return $this->value === null || $this->value === '';
    }

    /**
     * Get length in bytes
     */
    public function length(): int
    {
        return $this->value === null ? 0 : strlen($this->value);
    }

    /**
     * Get base64-encoded representation
     */
    public function toBase64(): string
    {
        if ($this->value === null) {
            return '';
        }
        return base64_encode($this->value);
    }

    /**
     * Create from base64-encoded string
     */
    public static function fromBase64(string $base64): self
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64 string');
        }
        return new self($decoded);
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeByteString($this->value);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $value = $decoder->readByteString();
        return new self($value);
    }

    public function __toString(): string
    {
        if ($this->value === null) {
            return '<empty>';
        }
        return sprintf('<ByteString: %d bytes>', $this->length());
    }
}
