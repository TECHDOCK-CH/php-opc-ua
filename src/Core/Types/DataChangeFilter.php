<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * DataChangeFilter - Filter for data change monitored items
 *
 * Specifies conditions under which data change notifications are reported,
 * including deadband filtering to reduce unnecessary notifications.
 *
 * OPC UA Specification Part 4, Section 7.17.2
 */
final readonly class DataChangeFilter implements IEncodeable
{
    /**
     * @param DataChangeTrigger $trigger When to report data changes
     * @param DeadbandType $deadbandType Type of deadband filtering
     * @param float $deadbandValue Deadband value (interpretation depends on deadbandType)
     */
    public function __construct(
        public DataChangeTrigger $trigger,
        public DeadbandType $deadbandType,
        public float $deadbandValue,
    ) {
    }

    /**
     * Create a filter with status reporting only
     *
     * Reports changes only when the status code changes.
     * Most restrictive - generates fewest notifications.
     */
    public static function statusOnly(): self
    {
        return new self(
            trigger: DataChangeTrigger::Status,
            deadbandType: DeadbandType::None,
            deadbandValue: 0.0,
        );
    }

    /**
     * Create a filter with status and value reporting
     *
     * Reports changes when status OR value changes (no timestamp check).
     * Default behavior for most applications.
     *
     * @param DeadbandType $deadbandType Optional deadband filtering
     * @param float $deadbandValue Deadband value (ignored if deadbandType is None)
     */
    public static function statusValue(
        DeadbandType $deadbandType = DeadbandType::None,
        float $deadbandValue = 0.0,
    ): self {
        return new self(
            trigger: DataChangeTrigger::StatusValue,
            deadbandType: $deadbandType,
            deadbandValue: $deadbandValue,
        );
    }

    /**
     * Create a filter with status, value, and timestamp reporting
     *
     * Reports changes when status, value, OR timestamp changes.
     * Generates most notifications - use when timestamp changes are important.
     *
     * @param DeadbandType $deadbandType Optional deadband filtering
     * @param float $deadbandValue Deadband value (ignored if deadbandType is None)
     */
    public static function statusValueTimestamp(
        DeadbandType $deadbandType = DeadbandType::None,
        float $deadbandValue = 0.0,
    ): self {
        return new self(
            trigger: DataChangeTrigger::StatusValueTimestamp,
            deadbandType: $deadbandType,
            deadbandValue: $deadbandValue,
        );
    }

    /**
     * Create a filter with absolute deadband
     *
     * Reports value changes only when the absolute change exceeds the deadband value.
     * Example: deadbandValue=5.0 means values must change by at least 5 units.
     *
     * @param float $absoluteDeadband Minimum absolute change required to report
     * @param DataChangeTrigger $trigger Trigger type (default: StatusValue)
     */
    public static function absoluteDeadband(
        float $absoluteDeadband,
        DataChangeTrigger $trigger = DataChangeTrigger::StatusValue,
    ): self {
        return new self(
            trigger: $trigger,
            deadbandType: DeadbandType::Absolute,
            deadbandValue: $absoluteDeadband,
        );
    }

    /**
     * Create a filter with percent deadband
     *
     * Reports value changes only when the percentage change exceeds the deadband value.
     * Percentage is calculated against the EURange (EngineeringUnits range).
     * Example: deadbandValue=1.0 means 1% of the EURange.
     *
     * @param float $percentDeadband Minimum percentage change required (0-100)
     * @param DataChangeTrigger $trigger Trigger type (default: StatusValue)
     */
    public static function percentDeadband(
        float $percentDeadband,
        DataChangeTrigger $trigger = DataChangeTrigger::StatusValue,
    ): self {
        if ($percentDeadband < 0.0 || $percentDeadband > 100.0) {
            throw new InvalidArgumentException('Percent deadband must be between 0 and 100');
        }

        return new self(
            trigger: $trigger,
            deadbandType: DeadbandType::Percent,
            deadbandValue: $percentDeadband,
        );
    }

    /**
     * Create default filter (StatusValue with no deadband)
     *
     * This is the most commonly used configuration.
     */
    public static function default(): self
    {
        return self::statusValue();
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode trigger
        $encoder->writeUInt32($this->trigger->value);

        // Encode deadband type
        $encoder->writeUInt32($this->deadbandType->value);

        // Encode deadband value
        $encoder->writeDouble($this->deadbandValue);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $triggerValue = $decoder->readUInt32();
        $trigger = DataChangeTrigger::from($triggerValue);

        $deadbandTypeValue = $decoder->readUInt32();
        $deadbandType = DeadbandType::from($deadbandTypeValue);

        $deadbandValue = $decoder->readDouble();

        return new self(
            trigger: $trigger,
            deadbandType: $deadbandType,
            deadbandValue: $deadbandValue,
        );
    }

    /**
     * Get a description of this filter
     */
    public function describe(): string
    {
        $desc = "Trigger: {$this->trigger->label()}";

        if ($this->deadbandType !== DeadbandType::None) {
            $desc .= ", Deadband: {$this->deadbandType->label()} ({$this->deadbandValue})";
        }

        return $desc;
    }
}
