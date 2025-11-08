<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * SubscriptionAcknowledgement - acknowledges receipt of notification messages.
 */
final readonly class SubscriptionAcknowledgement implements IEncodeable
{
    public function __construct(
        public int $subscriptionId,
        public int $sequenceNumber,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->subscriptionId);
        $encoder->writeUInt32($this->sequenceNumber);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $subscriptionId = $decoder->readUInt32();
        $sequenceNumber = $decoder->readUInt32();

        return new self(
            subscriptionId: $subscriptionId,
            sequenceNumber: $sequenceNumber,
        );
    }
}
