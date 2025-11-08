<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * AggregateFilter - Filter for aggregate monitored items
 *
 * Specifies the aggregate function and processing parameters for
 * aggregate data subscriptions.
 *
 * OPC UA Specification Part 4, Section 7.17.3
 */
final readonly class AggregateFilter implements IEncodeable
{
    /**
     * @param DateTime $startTime Start of the aggregation interval
     * @param NodeId $aggregateType NodeId of the aggregate function to apply
     * @param float $processingInterval Processing interval in milliseconds
     * @param AggregateConfiguration $aggregateConfiguration Configuration for aggregate calculation
     */
    public function __construct(
        public DateTime $startTime,
        public NodeId $aggregateType,
        public float $processingInterval,
        public AggregateConfiguration $aggregateConfiguration,
    ) {
    }

    /**
     * Create an aggregate filter for common aggregate types
     *
     * @param NodeId $aggregateType The aggregate function NodeId
     * @param float $processingInterval Processing interval in milliseconds
     * @param DateTime|null $startTime Start time (null = now)
     * @param AggregateConfiguration|null $config Configuration (null = defaults)
     */
    public static function create(
        NodeId $aggregateType,
        float $processingInterval,
        ?DateTime $startTime = null,
        ?AggregateConfiguration $config = null,
    ): self {
        return new self(
            startTime: $startTime ?? DateTime::now(),
            aggregateType: $aggregateType,
            processingInterval: $processingInterval,
            aggregateConfiguration: $config ?? AggregateConfiguration::defaults(),
        );
    }

    /**
     * Create filter for Average aggregate
     *
     * @param float $processingInterval Processing interval in milliseconds
     */
    public static function average(float $processingInterval): self
    {
        return self::create(
            aggregateType: NodeId::numeric(0, 2341), // Average
            processingInterval: $processingInterval,
        );
    }

    /**
     * Create filter for Minimum aggregate
     *
     * @param float $processingInterval Processing interval in milliseconds
     */
    public static function minimum(float $processingInterval): self
    {
        return self::create(
            aggregateType: NodeId::numeric(0, 2346), // Minimum
            processingInterval: $processingInterval,
        );
    }

    /**
     * Create filter for Maximum aggregate
     *
     * @param float $processingInterval Processing interval in milliseconds
     */
    public static function maximum(float $processingInterval): self
    {
        return self::create(
            aggregateType: NodeId::numeric(0, 2347), // Maximum
            processingInterval: $processingInterval,
        );
    }

    /**
     * Create filter for Count aggregate
     *
     * @param float $processingInterval Processing interval in milliseconds
     */
    public static function count(float $processingInterval): self
    {
        return self::create(
            aggregateType: NodeId::numeric(0, 2351), // Count
            processingInterval: $processingInterval,
        );
    }

    /**
     * Create filter for Total (sum) aggregate
     *
     * @param float $processingInterval Processing interval in milliseconds
     */
    public static function total(float $processingInterval): self
    {
        return self::create(
            aggregateType: NodeId::numeric(0, 2355), // Total
            processingInterval: $processingInterval,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode start time
        $this->startTime->encode($encoder);

        // Encode aggregate type NodeId
        $this->aggregateType->encode($encoder);

        // Encode processing interval
        $encoder->writeDouble($this->processingInterval);

        // Encode aggregate configuration
        $this->aggregateConfiguration->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $startTime = DateTime::decode($decoder);
        $aggregateType = NodeId::decode($decoder);
        $processingInterval = $decoder->readDouble();
        $aggregateConfiguration = AggregateConfiguration::decode($decoder);

        return new self(
            startTime: $startTime,
            aggregateType: $aggregateType,
            processingInterval: $processingInterval,
            aggregateConfiguration: $aggregateConfiguration,
        );
    }

    /**
     * Get a description of this filter
     */
    public function describe(): string
    {
        return "Aggregate Type: {$this->aggregateType}, " .
            "Processing Interval: {$this->processingInterval}ms, " .
            "Start: {$this->startTime}";
    }
}
