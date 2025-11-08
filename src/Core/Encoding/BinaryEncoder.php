<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Encoding;

use InvalidArgumentException;

/**
 * Encodes PHP values to OPC UA binary format using native PHP pack()
 */
final class BinaryEncoder
{
    private string $buffer = '';

    /**
     * Get the encoded bytes
     */
    public function getBytes(): string
    {
        return $this->buffer;
    }

    /**
     * Write a boolean (1 byte)
     */
    public function writeBoolean(bool $value): void
    {
        $this->buffer .= pack('C', $value ? 1 : 0);
    }

    /**
     * Write a signed byte (8-bit)
     */
    public function writeSByte(int $value): void
    {
        $this->buffer .= pack('c', $value);
    }

    /**
     * Write an unsigned byte (8-bit)
     */
    public function writeByte(int $value): void
    {
        $this->buffer .= pack('C', $value);
    }

    /**
     * Write a signed 16-bit integer (little-endian)
     */
    public function writeInt16(int $value): void
    {
        $this->buffer .= pack('v', $value);  // v = unsigned short (16-bit) little-endian
    }

    /**
     * Write an unsigned 16-bit integer (little-endian)
     */
    public function writeUInt16(int $value): void
    {
        $this->buffer .= pack('v', $value);
    }

    /**
     * Write a signed 32-bit integer (little-endian)
     */
    public function writeInt32(int $value): void
    {
        $this->buffer .= pack('l', $value);  // l = signed long (32-bit) little-endian
    }

    /**
     * Write an unsigned 32-bit integer (little-endian)
     */
    public function writeUInt32(int $value): void
    {
        $this->buffer .= pack('V', $value);  // V = unsigned long (32-bit) little-endian
    }

    /**
     * Write a signed 64-bit integer (little-endian)
     */
    public function writeInt64(int $value): void
    {
        $this->buffer .= pack('q', $value);  // q = signed long long (64-bit) little-endian
    }

    /**
     * Write an unsigned 64-bit integer (little-endian)
     */
    public function writeUInt64(int $value): void
    {
        $this->buffer .= pack('P', $value);  // P = unsigned long long (64-bit) little-endian
    }

    /**
     * Write a 32-bit float (little-endian)
     */
    public function writeFloat(float $value): void
    {
        $this->buffer .= pack('g', $value);  // g = float (32-bit) little-endian
    }

    /**
     * Write a 64-bit double (little-endian)
     */
    public function writeDouble(float $value): void
    {
        $this->buffer .= pack('e', $value);  // e = double (64-bit) little-endian
    }

    /**
     * Write a string (4-byte length prefix + UTF-8 bytes)
     * Null strings are encoded as length = -1
     */
    public function writeString(?string $value): void
    {
        if ($value === null) {
            $this->writeInt32(-1);
            return;
        }

        $length = strlen($value);
        $this->writeInt32($length);

        if ($length > 0) {
            $this->buffer .= $value;
        }
    }

    /**
     * Write a byte string (4-byte length prefix + raw bytes)
     * Null byte strings are encoded as length = -1
     */
    public function writeByteString(?string $value): void
    {
        $this->writeString($value);  // Same encoding as string
    }

    /**
     * Write a GUID (16 bytes)
     * Format: Data1 (4 bytes), Data2 (2 bytes), Data3 (2 bytes), Data4 (8 bytes)
     */
    public function writeGuid(string $guid): void
    {
        // Parse standard GUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        $hex = str_replace('-', '', $guid);

        if (strlen($hex) !== 32) {
            throw new InvalidArgumentException('Invalid GUID format');
        }

        // Data1 (4 bytes, little-endian)
        $this->writeUInt32((int)hexdec(substr($hex, 0, 8)));

        // Data2 (2 bytes, little-endian)
        $this->writeUInt16((int)hexdec(substr($hex, 8, 4)));

        // Data3 (2 bytes, little-endian)
        $this->writeUInt16((int)hexdec(substr($hex, 12, 4)));

        // Data4 (8 bytes, big-endian as per spec)
        for ($i = 16; $i < 32; $i += 2) {
            $this->writeByte((int)hexdec(substr($hex, $i, 2)));
        }
    }

    /**
     * Write raw bytes to the buffer
     */
    public function writeBytes(string $bytes): void
    {
        $this->buffer .= $bytes;
    }
}
