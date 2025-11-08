<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ReadRawModifiedDetails - Specifies details for reading raw historical data
 *
 * Used to read raw or modified historical values in a time range.
 *
 * OPC UA Specification Part 11, Section 6.4.3.2
 */
final readonly class ReadRawModifiedDetails implements IEncodeable
{
    /**
     * @param bool $isReadModified True to read modified values, false for raw values
     * @param DateTime $startTime Start of the time range
     * @param DateTime $endTime End of the time range
     * @param int $numValuesPerNode Maximum number of values to return per node (0 = all)
     * @param bool $returnBounds Include bounding values at start/end times
     */
    public function __construct(
        public bool $isReadModified,
        public DateTime $startTime,
        public DateTime $endTime,
        public int $numValuesPerNode,
        public bool $returnBounds,
    ) {
        if ($numValuesPerNode < 0) {
            throw new InvalidArgumentException('numValuesPerNode must be non-negative');
        }
    }

    /**
     * Create for reading raw historical values
     *
     * @param DateTime $startTime Start of time range
     * @param DateTime $endTime End of time range
     * @param int $numValuesPerNode Max values per node (0 = all)
     * @param bool $returnBounds Include boundary values
     */
    public static function raw(
        DateTime $startTime,
        DateTime $endTime,
        int $numValuesPerNode = 0,
        bool $returnBounds = false,
    ): self {
        return new self(
            isReadModified: false,
            startTime: $startTime,
            endTime: $endTime,
            numValuesPerNode: $numValuesPerNode,
            returnBounds: $returnBounds,
        );
    }

    /**
     * Create for reading modified historical values
     *
     * Modified values include audit information about changes.
     *
     * @param DateTime $startTime Start of time range
     * @param DateTime $endTime End of time range
     * @param int $numValuesPerNode Max values per node (0 = all)
     * @param bool $returnBounds Include boundary values
     */
    public static function modified(
        DateTime $startTime,
        DateTime $endTime,
        int $numValuesPerNode = 0,
        bool $returnBounds = false,
    ): self {
        return new self(
            isReadModified: true,
            startTime: $startTime,
            endTime: $endTime,
            numValuesPerNode: $numValuesPerNode,
            returnBounds: $returnBounds,
        );
    }

    /**
     * Create for reading last N values
     *
     * @param int $numValues Number of most recent values to read
     * @param DateTime|null $endTime End time (null = now)
     */
    public static function lastNValues(
        int $numValues,
        ?DateTime $endTime = null,
    ): self {
        return new self(
            isReadModified: false,
            startTime: DateTime::minValue(), // Read from beginning
            endTime: $endTime ?? DateTime::now(),
            numValuesPerNode: $numValues,
            returnBounds: false,
        );
    }

    /**
     * Create for reading values in last N seconds
     *
     * @param int $seconds Number of seconds to look back
     * @param int $maxValues Maximum values to return (0 = all)
     */
    public static function lastNSeconds(
        int $seconds,
        int $maxValues = 0,
    ): self {
        $endTime = DateTime::now();
        $startTime = DateTime::fromTimestamp(time() - $seconds);

        return new self(
            isReadModified: false,
            startTime: $startTime,
            endTime: $endTime,
            numValuesPerNode: $maxValues,
            returnBounds: false,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode isReadModified
        $encoder->writeBoolean($this->isReadModified);

        // Encode start time
        $this->startTime->encode($encoder);

        // Encode end time
        $this->endTime->encode($encoder);

        // Encode numValuesPerNode
        $encoder->writeUInt32($this->numValuesPerNode);

        // Encode returnBounds
        $encoder->writeBoolean($this->returnBounds);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $isReadModified = $decoder->readBoolean();
        $startTime = DateTime::decode($decoder);
        $endTime = DateTime::decode($decoder);
        $numValuesPerNode = $decoder->readUInt32();
        $returnBounds = $decoder->readBoolean();

        return new self(
            isReadModified: $isReadModified,
            startTime: $startTime,
            endTime: $endTime,
            numValuesPerNode: $numValuesPerNode,
            returnBounds: $returnBounds,
        );
    }

    /**
     * Get a description of this read operation
     */
    public function describe(): string
    {
        $type = $this->isReadModified ? 'Modified' : 'Raw';
        $range = "{$this->startTime} to {$this->endTime}";
        $limit = $this->numValuesPerNode > 0 ? " (max {$this->numValuesPerNode} values)" : " (all values)";
        $bounds = $this->returnBounds ? " with bounds" : "";

        return "$type history: $range$limit$bounds";
    }
}
