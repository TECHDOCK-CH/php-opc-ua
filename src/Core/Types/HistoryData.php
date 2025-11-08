<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * HistoryData - Container for historical data values
 *
 * Contains an array of DataValues representing historical data.
 *
 * OPC UA Specification Part 11, Section 6.4.3.3
 */
final readonly class HistoryData implements IEncodeable
{
    /**
     * @param DataValue[] $dataValues Array of historical data values
     */
    public function __construct(
        public array $dataValues,
    ) {
    }

    /**
     * Create empty history data
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Get the number of data values
     */
    public function count(): int
    {
        return count($this->dataValues);
    }

    /**
     * Check if empty
     */
    public function isEmpty(): bool
    {
        return $this->dataValues === [];
    }

    /**
     * Get values as array
     *
     * @return DataValue[]
     */
    public function getValues(): array
    {
        return $this->dataValues;
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode data values array
        $encoder->writeInt32(count($this->dataValues));
        foreach ($this->dataValues as $dataValue) {
            $dataValue->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $count = $decoder->readInt32();
        $dataValues = [];
        for ($i = 0; $i < $count; $i++) {
            $dataValues[] = DataValue::decode($decoder);
        }

        return new self($dataValues);
    }

    public function __toString(): string
    {
        return sprintf('HistoryData[%d values]', $this->count());
    }
}
