<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * NotificationMessage - contains notifications from the server.
 */
final readonly class NotificationMessage implements IEncodeable
{
    /**
     * @param ExtensionObject[] $notificationData
     */
    public function __construct(
        public int $sequenceNumber,
        public DateTime $publishTime,
        public array $notificationData,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->sequenceNumber);
        $this->publishTime->encode($encoder);

        $encoder->writeInt32(count($this->notificationData));
        foreach ($this->notificationData as $data) {
            $data->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $sequenceNumber = $decoder->readUInt32();
        $publishTime = DateTime::decode($decoder);

        $count = $decoder->readInt32();
        $notificationData = [];
        for ($i = 0; $i < $count; $i++) {
            $notificationData[] = ExtensionObject::decode($decoder);
        }

        return new self(
            sequenceNumber: $sequenceNumber,
            publishTime: $publishTime,
            notificationData: $notificationData,
        );
    }
}
