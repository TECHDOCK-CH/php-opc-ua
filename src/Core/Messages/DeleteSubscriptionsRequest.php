<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * DeleteSubscriptionsRequest - deletes one or more subscriptions.
 */
final readonly class DeleteSubscriptionsRequest implements ServiceRequest
{
    private const int TYPE_ID = 799;

    /**
     * @param int[] $subscriptionIds
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public array $subscriptionIds,
    ) {
    }

    /**
     * Create a delete subscriptions request.
     *
     * @param int[] $subscriptionIds
     */
    public static function create(
        array $subscriptionIds,
        ?RequestHeader $requestHeader = null,
    ): self {
        if ($subscriptionIds === []) {
            throw new InvalidArgumentException('DeleteSubscriptionsRequest requires at least one subscription ID.');
        }

        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            subscriptionIds: array_values($subscriptionIds),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        $encoder->writeInt32(count($this->subscriptionIds));
        foreach ($this->subscriptionIds as $id) {
            $encoder->writeUInt32($id);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);

        $count = $decoder->readInt32();
        $subscriptionIds = [];
        for ($i = 0; $i < $count; $i++) {
            $subscriptionIds[] = $decoder->readUInt32();
        }

        return new self(
            requestHeader: $requestHeader,
            subscriptionIds: $subscriptionIds,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
