<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use DateTimeImmutable;
use DateTimeInterface;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * OPC UA DateTime (64-bit)
 *
 * Represents 100-nanosecond intervals since January 1, 1601 UTC
 * Special values:
 * - 0: Unspecified/null time
 * - 0x7FFFFFFFFFFFFFFF: Max value (end of time)
 */
final readonly class DateTime implements IEncodeable
{
    /**
     * Number of 100-nanosecond intervals between 1601-01-01 and 1970-01-01 (Unix epoch)
     */
    private const int EPOCH_OFFSET = 116444736000000000;

    public function __construct(
        public int $ticks,
    ) {
    }

    /**
     * Create from Unix timestamp (seconds since 1970-01-01)
     */
    public static function fromUnixTimestamp(float $timestamp): self
    {
        // Convert seconds to 100-nanosecond intervals and add epoch offset
        $ticks = (int)($timestamp * 10000000) + self::EPOCH_OFFSET;
        return new self($ticks);
    }

    /**
     * Create from PHP DateTime object
     */
    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return self::fromUnixTimestamp((float)$dateTime->format('U.u'));
    }

    /**
     * Create current time
     */
    public static function now(): self
    {
        return self::fromUnixTimestamp(microtime(true));
    }

    /**
     * Create null/unspecified time
     */
    public static function null(): self
    {
        return new self(0);
    }

    /**
     * Create minimum DateTime value (start of time)
     */
    public static function minValue(): self
    {
        return self::fromUnixTimestamp(0);
    }

    /**
     * Alias for fromUnixTimestamp for convenience
     */
    public static function fromTimestamp(float $timestamp): self
    {
        return self::fromUnixTimestamp($timestamp);
    }

    /**
     * Convert to Unix timestamp
     */
    public function toUnixTimestamp(): float
    {
        if ($this->ticks === 0) {
            return 0.0;
        }

        return ($this->ticks - self::EPOCH_OFFSET) / 10000000.0;
    }

    /**
     * Convert to PHP DateTime object
     */
    public function toDateTime(): DateTimeImmutable
    {
        if ($this->ticks === 0) {
            return new DateTimeImmutable('@0');
        }

        $timestamp = $this->toUnixTimestamp();
        $result = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp));
        return $result !== false ? $result : new DateTimeImmutable('@0');
    }

    /**
     * Check if this is a null/unspecified time
     */
    public function isNull(): bool
    {
        return $this->ticks === 0;
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeInt64($this->ticks);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $ticks = $decoder->readInt64();
        return new self($ticks);
    }

    /**
     * Get ISO 8601 string representation
     */
    public function toString(): string
    {
        if ($this->ticks === 0) {
            return '0000-00-00T00:00:00.000Z';
        }

        return $this->toDateTime()->format('Y-m-d\TH:i:s.u\Z');
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check if two DateTime values are equal
     */
    public function equals(self $other): bool
    {
        return $this->ticks === $other->ticks;
    }
}
