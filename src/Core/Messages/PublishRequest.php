<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\SubscriptionAcknowledgement;

/**
 * PublishRequest - requests notification messages from subscriptions.
 */
final readonly class PublishRequest implements ServiceRequest
{
    private const int TYPE_ID = 826;

    /**
     * @param SubscriptionAcknowledgement[] $subscriptionAcknowledgements
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public array $subscriptionAcknowledgements,
    ) {
    }

    /**
     * Create a publish request with defaults.
     *
     * @param SubscriptionAcknowledgement[] $subscriptionAcknowledgements
     */
    public static function create(
        array $subscriptionAcknowledgements = [],
        ?RequestHeader $requestHeader = null,
    ): self {
        foreach ($subscriptionAcknowledgements as $ack) {
            if (!$ack instanceof SubscriptionAcknowledgement) {
                throw new InvalidArgumentException(
                    'subscriptionAcknowledgements must only contain SubscriptionAcknowledgement instances.'
                );
            }
        }

        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            subscriptionAcknowledgements: array_values($subscriptionAcknowledgements),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        $encoder->writeInt32(count($this->subscriptionAcknowledgements));
        foreach ($this->subscriptionAcknowledgements as $ack) {
            $ack->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);

        $count = $decoder->readInt32();
        $subscriptionAcknowledgements = [];
        for ($i = 0; $i < $count; $i++) {
            $subscriptionAcknowledgements[] = SubscriptionAcknowledgement::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            subscriptionAcknowledgements: $subscriptionAcknowledgements,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
