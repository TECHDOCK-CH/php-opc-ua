<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * AggregateConfiguration - Configuration for aggregate calculations
 *
 * Specifies how aggregate calculations should be performed,
 * including treatment of uncertain/bad data and boundary values.
 *
 * OPC UA Specification Part 13 (Historical Access)
 */
final readonly class AggregateConfiguration implements IEncodeable
{
    /**
     * @param bool $useServerCapabilitiesDefaults Use server's default aggregate configuration
     * @param bool $treatUncertainAsBad Treat Uncertain status as Bad
     * @param int $percentDataBad Minimum percentage of Bad data required to return Bad aggregate
     * @param int $percentDataGood Minimum percentage of Good data required to return Good aggregate
     * @param bool $useSlopedExtrapolation Use sloped extrapolation for interpolated values
     */
    public function __construct(
        public bool $useServerCapabilitiesDefaults,
        public bool $treatUncertainAsBad,
        public int $percentDataBad,
        public int $percentDataGood,
        public bool $useSlopedExtrapolation,
    ) {
        if ($percentDataBad < 0 || $percentDataBad > 100) {
            throw new InvalidArgumentException('percentDataBad must be between 0 and 100');
        }
        if ($percentDataGood < 0 || $percentDataGood > 100) {
            throw new InvalidArgumentException('percentDataGood must be between 0 and 100');
        }
    }

    /**
     * Create default configuration using server defaults
     */
    public static function defaults(): self
    {
        return new self(
            useServerCapabilitiesDefaults: true,
            treatUncertainAsBad: false,
            percentDataBad: 100,
            percentDataGood: 100,
            useSlopedExtrapolation: false,
        );
    }

    /**
     * Create strict configuration (all data must be good)
     */
    public static function strict(): self
    {
        return new self(
            useServerCapabilitiesDefaults: false,
            treatUncertainAsBad: true,
            percentDataBad: 0,
            percentDataGood: 100,
            useSlopedExtrapolation: false,
        );
    }

    /**
     * Create lenient configuration (accept some bad data)
     *
     * @param int $percentDataGood Minimum percentage of good data required (default: 70)
     */
    public static function lenient(int $percentDataGood = 70): self
    {
        return new self(
            useServerCapabilitiesDefaults: false,
            treatUncertainAsBad: false,
            percentDataBad: 50,
            percentDataGood: $percentDataGood,
            useSlopedExtrapolation: true,
        );
    }

    /**
     * Create custom configuration
     *
     * @param bool $treatUncertainAsBad Treat uncertain data as bad
     * @param int $percentDataBad Max percentage of bad data before aggregate is bad
     * @param int $percentDataGood Min percentage of good data required
     * @param bool $useSlopedExtrapolation Use sloped interpolation
     */
    public static function custom(
        bool $treatUncertainAsBad,
        int $percentDataBad,
        int $percentDataGood,
        bool $useSlopedExtrapolation = false,
    ): self {
        return new self(
            useServerCapabilitiesDefaults: false,
            treatUncertainAsBad: $treatUncertainAsBad,
            percentDataBad: $percentDataBad,
            percentDataGood: $percentDataGood,
            useSlopedExtrapolation: $useSlopedExtrapolation,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeBoolean($this->useServerCapabilitiesDefaults);
        $encoder->writeBoolean($this->treatUncertainAsBad);
        $encoder->writeByte($this->percentDataBad);
        $encoder->writeByte($this->percentDataGood);
        $encoder->writeBoolean($this->useSlopedExtrapolation);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $useServerCapabilitiesDefaults = $decoder->readBoolean();
        $treatUncertainAsBad = $decoder->readBoolean();
        $percentDataBad = $decoder->readByte();
        $percentDataGood = $decoder->readByte();
        $useSlopedExtrapolation = $decoder->readBoolean();

        return new self(
            useServerCapabilitiesDefaults: $useServerCapabilitiesDefaults,
            treatUncertainAsBad: $treatUncertainAsBad,
            percentDataBad: $percentDataBad,
            percentDataGood: $percentDataGood,
            useSlopedExtrapolation: $useSlopedExtrapolation,
        );
    }

    /**
     * Get a description of this configuration
     */
    public function describe(): string
    {
        if ($this->useServerCapabilitiesDefaults) {
            return "Using server defaults";
        }

        return sprintf(
            "TreatUncertainAsBad: %s, PercentDataBad: %d%%, PercentDataGood: %d%%, SlopedExtrapolation: %s",
            $this->treatUncertainAsBad ? 'Yes' : 'No',
            $this->percentDataBad,
            $this->percentDataGood,
            $this->useSlopedExtrapolation ? 'Yes' : 'No'
        );
    }
}
