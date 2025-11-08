<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Encoding;

use InvalidArgumentException;
use RuntimeException;

/**
 * Decodes OPC UA binary format to PHP values using native PHP unpack()
 */
final class BinaryDecoder
{
    private string $data;
    private int $position = 0;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    /**
     * Helper method to safely unpack integer data
     *
     * @param non-empty-string $format
     * @param int $length
     * @return int
     */
    private function unpackInt(string $format, int $length): int
    {
        $bytes = substr($this->data, $this->position, $length);
        $this->position += $length;
        $result = unpack($format, $bytes);
        if ($result === false) {
            throw new RuntimeException('Failed to unpack data');
        }
        return $result[1];
    }

    /**
     * Helper method to safely unpack float data
     *
     * @param non-empty-string $format
     * @param int $length
     * @return float
     */
    private function unpackFloat(string $format, int $length): float
    {
        $bytes = substr($this->data, $this->position, $length);
        $this->position += $length;
        $result = unpack($format, $bytes);
        if ($result === false) {
            throw new RuntimeException('Failed to unpack data');
        }
        return $result[1];
    }

    /**
     * Read a boolean (1 byte)
     */
    public function readBoolean(): bool
    {
        return $this->readByte() !== 0;
    }

    /**
     * Read a signed byte (8-bit)
     */
    public function readSByte(): int
    {
        return $this->unpackInt('c', 1);
    }

    /**
     * Read an unsigned byte (8-bit)
     */
    public function readByte(): int
    {
        return $this->unpackInt('C', 1);
    }

    /**
     * Read a signed 16-bit integer (little-endian)
     */
    public function readInt16(): int
    {
        $value = $this->unpackInt('v', 2);
        // Convert to signed
        return $value > 32767 ? $value - 65536 : $value;
    }

    /**
     * Read an unsigned 16-bit integer (little-endian)
     */
    public function readUInt16(): int
    {
        return $this->unpackInt('v', 2);
    }

    /**
     * Read a signed 32-bit integer (little-endian)
     */
    public function readInt32(): int
    {
        return $this->unpackInt('l', 4);
    }

    /**
     * Read an unsigned 32-bit integer (little-endian)
     */
    public function readUInt32(): int
    {
        return $this->unpackInt('V', 4);
    }

    /**
     * Read a signed 64-bit integer (little-endian)
     */
    public function readInt64(): int
    {
        return $this->unpackInt('q', 8);
    }

    /**
     * Read an unsigned 64-bit integer (little-endian)
     */
    public function readUInt64(): int
    {
        return $this->unpackInt('P', 8);
    }

    /**
     * Read a 32-bit float (little-endian)
     */
    public function readFloat(): float
    {
        return $this->unpackFloat('g', 4);
    }

    /**
     * Read a 64-bit double (little-endian)
     */
    public function readDouble(): float
    {
        return $this->unpackFloat('e', 8);
    }

    /**
     * Read a string (4-byte length prefix + UTF-8 bytes)
     * Returns null if length is -1
     */
    public function readString(): ?string
    {
        $length = $this->readInt32();

        if ($length === -1) {
            return null;
        }

        if ($length === 0) {
            return '';
        }

        if ($length < 0) {
            throw new RuntimeException("Invalid string length: {$length}");
        }

        $value = substr($this->data, $this->position, $length);
        $this->position += $length;
        return $value;
    }

    /**
     * Read a byte string (4-byte length prefix + raw bytes)
     * Returns null if length is -1
     */
    public function readByteString(): ?string
    {
        return $this->readString();  // Same encoding as string
    }

    /**
     * Read a GUID (16 bytes)
     * Returns standard format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     */
    public function readGuid(): string
    {
        // Data1 (4 bytes, little-endian)
        $data1 = sprintf('%08x', $this->readUInt32());

        // Data2 (2 bytes, little-endian)
        $data2 = sprintf('%04x', $this->readUInt16());

        // Data3 (2 bytes, little-endian)
        $data3 = sprintf('%04x', $this->readUInt16());

        // Data4 (8 bytes, big-endian as per spec)
        $data4 = '';
        for ($i = 0; $i < 8; $i++) {
            $data4 .= sprintf('%02x', $this->readByte());
        }

        return "{$data1}-{$data2}-{$data3}-" . substr($data4, 0, 4) . '-' . substr($data4, 4);
    }

    /**
     * Check if there is more data to read
     */
    public function hasMoreData(): bool
    {
        return $this->position < strlen($this->data);
    }

    /**
     * Get current position in stream
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Set position in stream
     */
    public function setPosition(int $position): void
    {
        if ($position < 0 || $position > strlen($this->data)) {
            throw new InvalidArgumentException("Invalid position: {$position}");
        }
        $this->position = $position;
    }

    /**
     * Read raw bytes from the buffer
     */
    public function readBytes(int $length): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException("Invalid length: {$length}");
        }

        if ($this->position + $length > strlen($this->data)) {
            throw new RuntimeException("Not enough data to read {$length} bytes");
        }

        $bytes = substr($this->data, $this->position, $length);
        $this->position += $length;
        return $bytes;
    }
}
