<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * CreateSubscriptionRequest - creates a subscription for receiving notifications.
 */
final readonly class CreateSubscriptionRequest implements ServiceRequest
{
    private const int TYPE_ID = 787;

    public function __construct(
        public RequestHeader $requestHeader,
        public float $requestedPublishingInterval,
        public int $requestedLifetimeCount,
        public int $requestedMaxKeepAliveCount,
        public int $maxNotificationsPerPublish,
        public bool $publishingEnabled,
        public int $priority,
    ) {
    }

    /**
     * Create a subscription request with defaults.
     */
    public static function create(
        ?RequestHeader $requestHeader = null,
        float $requestedPublishingInterval = 1000.0,
        int $requestedLifetimeCount = 10000,
        int $requestedMaxKeepAliveCount = 10,
        int $maxNotificationsPerPublish = 0,
        bool $publishingEnabled = true,
        int $priority = 0,
    ): self {
        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            requestedPublishingInterval: $requestedPublishingInterval,
            requestedLifetimeCount: $requestedLifetimeCount,
            requestedMaxKeepAliveCount: $requestedMaxKeepAliveCount,
            maxNotificationsPerPublish: $maxNotificationsPerPublish,
            publishingEnabled: $publishingEnabled,
            priority: $priority,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeDouble($this->requestedPublishingInterval);
        $encoder->writeUInt32($this->requestedLifetimeCount);
        $encoder->writeUInt32($this->requestedMaxKeepAliveCount);
        $encoder->writeUInt32($this->maxNotificationsPerPublish);
        $encoder->writeBoolean($this->publishingEnabled);
        $encoder->writeByte($this->priority);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $requestedPublishingInterval = $decoder->readDouble();
        $requestedLifetimeCount = $decoder->readUInt32();
        $requestedMaxKeepAliveCount = $decoder->readUInt32();
        $maxNotificationsPerPublish = $decoder->readUInt32();
        $publishingEnabled = $decoder->readBoolean();
        $priority = $decoder->readByte();

        return new self(
            requestHeader: $requestHeader,
            requestedPublishingInterval: $requestedPublishingInterval,
            requestedLifetimeCount: $requestedLifetimeCount,
            requestedMaxKeepAliveCount: $requestedMaxKeepAliveCount,
            maxNotificationsPerPublish: $maxNotificationsPerPublish,
            publishingEnabled: $publishingEnabled,
            priority: $priority,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }

    public function getRequestHeader(): RequestHeader
    {
        return $this->requestHeader;
    }
}
