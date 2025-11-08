<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * MonitoredItemNotification - notification for a single monitored item.
 */
final readonly class MonitoredItemNotification implements IEncodeable
{
    public function __construct(
        public int $clientHandle,
        public DataValue $value,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->clientHandle);
        $this->value->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $clientHandle = $decoder->readUInt32();
        $value = DataValue::decode($decoder);

        return new self(
            clientHandle: $clientHandle,
            value: $value,
        );
    }
}
