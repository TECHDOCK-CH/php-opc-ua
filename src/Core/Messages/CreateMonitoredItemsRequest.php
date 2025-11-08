<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\MonitoredItemCreateRequest;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\TimestampsToReturn;

/**
 * CreateMonitoredItemsRequest - creates monitored items within a subscription.
 */
final readonly class CreateMonitoredItemsRequest implements ServiceRequest
{
    private const int TYPE_ID = 751;

    /**
     * @param MonitoredItemCreateRequest[] $itemsToCreate
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public int $subscriptionId,
        public TimestampsToReturn $timestampsToReturn,
        public array $itemsToCreate,
    ) {
    }

    /**
     * Create monitored items request with defaults.
     *
     * @param MonitoredItemCreateRequest[] $itemsToCreate
     */
    public static function create(
        int $subscriptionId,
        array $itemsToCreate,
        ?RequestHeader $requestHeader = null,
        TimestampsToReturn $timestampsToReturn = TimestampsToReturn::Both,
    ): self {
        if ($itemsToCreate === []) {
            throw new InvalidArgumentException('CreateMonitoredItemsRequest requires at least one item.');
        }

        foreach ($itemsToCreate as $item) {
            if (!$item instanceof MonitoredItemCreateRequest) {
                throw new InvalidArgumentException(
                    'itemsToCreate must only contain MonitoredItemCreateRequest instances.'
                );
            }
        }

        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            subscriptionId: $subscriptionId,
            timestampsToReturn: $timestampsToReturn,
            itemsToCreate: array_values($itemsToCreate),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeUInt32($this->subscriptionId);
        $encoder->writeInt32($this->timestampsToReturn->value);

        $encoder->writeInt32(count($this->itemsToCreate));
        foreach ($this->itemsToCreate as $item) {
            $item->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $subscriptionId = $decoder->readUInt32();
        $timestampsToReturn = TimestampsToReturn::from($decoder->readInt32());

        $count = $decoder->readInt32();
        $itemsToCreate = [];
        for ($i = 0; $i < $count; $i++) {
            $itemsToCreate[] = MonitoredItemCreateRequest::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            subscriptionId: $subscriptionId,
            timestampsToReturn: $timestampsToReturn,
            itemsToCreate: $itemsToCreate,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
