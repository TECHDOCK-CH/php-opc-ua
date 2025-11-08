<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * BuildInfo - Server build information
 *
 * Contains information that describes the build of the Server.
 */
final readonly class BuildInfo implements IEncodeable
{
    public function __construct(
        public string $productUri,
        public string $manufacturerName,
        public string $productName,
        public string $softwareVersion,
        public string $buildNumber,
        public DateTime $buildDate,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeString($this->productUri);
        $encoder->writeString($this->manufacturerName);
        $encoder->writeString($this->productName);
        $encoder->writeString($this->softwareVersion);
        $encoder->writeString($this->buildNumber);
        $this->buildDate->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $productUri = $decoder->readString() ?? '';
        $manufacturerName = $decoder->readString() ?? '';
        $productName = $decoder->readString() ?? '';
        $softwareVersion = $decoder->readString() ?? '';
        $buildNumber = $decoder->readString() ?? '';
        $buildDate = DateTime::decode($decoder);

        return new self(
            productUri: $productUri,
            manufacturerName: $manufacturerName,
            productName: $productName,
            softwareVersion: $softwareVersion,
            buildNumber: $buildNumber,
            buildDate: $buildDate,
        );
    }

    /**
     * Get the TypeId for BuildInfo
     */
    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, 338); // BuildInfo DataType NodeId
    }

    /**
     * Get string representation
     */
    public function toString(): string
    {
        return "{$this->productName} {$this->softwareVersion} (Build {$this->buildNumber})";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
