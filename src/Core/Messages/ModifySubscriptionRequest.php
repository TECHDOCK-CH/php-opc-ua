<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * ModifySubscriptionRequest - modifies a subscription's parameters.
 */
final readonly class ModifySubscriptionRequest implements ServiceRequest
{
    private const int TYPE_ID = 793;

    public function __construct(
        public RequestHeader $requestHeader,
        public int $subscriptionId,
        public float $requestedPublishingInterval,
        public int $requestedLifetimeCount,
        public int $requestedMaxKeepAliveCount,
        public int $maxNotificationsPerPublish,
        public int $priority,
    ) {
    }

    public static function create(
        int $subscriptionId,
        ?RequestHeader $requestHeader = null,
        float $requestedPublishingInterval = 1000.0,
        int $requestedLifetimeCount = 10000,
        int $requestedMaxKeepAliveCount = 10,
        int $maxNotificationsPerPublish = 0,
        int $priority = 0,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            subscriptionId: $subscriptionId,
            requestedPublishingInterval: $requestedPublishingInterval,
            requestedLifetimeCount: $requestedLifetimeCount,
            requestedMaxKeepAliveCount: $requestedMaxKeepAliveCount,
            maxNotificationsPerPublish: $maxNotificationsPerPublish,
            priority: $priority,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeUInt32($this->subscriptionId);
        $encoder->writeDouble($this->requestedPublishingInterval);
        $encoder->writeUInt32($this->requestedLifetimeCount);
        $encoder->writeUInt32($this->requestedMaxKeepAliveCount);
        $encoder->writeUInt32($this->maxNotificationsPerPublish);
        $encoder->writeByte($this->priority);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $subscriptionId = $decoder->readUInt32();
        $requestedPublishingInterval = $decoder->readDouble();
        $requestedLifetimeCount = $decoder->readUInt32();
        $requestedMaxKeepAliveCount = $decoder->readUInt32();
        $maxNotificationsPerPublish = $decoder->readUInt32();
        $priority = $decoder->readByte();

        return new self(
            requestHeader: $requestHeader,
            subscriptionId: $subscriptionId,
            requestedPublishingInterval: $requestedPublishingInterval,
            requestedLifetimeCount: $requestedLifetimeCount,
            requestedMaxKeepAliveCount: $requestedMaxKeepAliveCount,
            maxNotificationsPerPublish: $maxNotificationsPerPublish,
            priority: $priority,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
