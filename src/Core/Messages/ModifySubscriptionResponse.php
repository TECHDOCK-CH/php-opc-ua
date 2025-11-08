<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * ModifySubscriptionResponse - result of modifying a subscription.
 */
final readonly class ModifySubscriptionResponse implements ServiceResponse
{
    private const int TYPE_ID = 796;

    public function __construct(
        public ResponseHeader $responseHeader,
        public float $revisedPublishingInterval,
        public int $revisedLifetimeCount,
        public int $revisedMaxKeepAliveCount,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);
        $encoder->writeDouble($this->revisedPublishingInterval);
        $encoder->writeUInt32($this->revisedLifetimeCount);
        $encoder->writeUInt32($this->revisedMaxKeepAliveCount);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);
        $revisedPublishingInterval = $decoder->readDouble();
        $revisedLifetimeCount = $decoder->readUInt32();
        $revisedMaxKeepAliveCount = $decoder->readUInt32();

        return new self(
            responseHeader: $responseHeader,
            revisedPublishingInterval: $revisedPublishingInterval,
            revisedLifetimeCount: $revisedLifetimeCount,
            revisedMaxKeepAliveCount: $revisedMaxKeepAliveCount,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
