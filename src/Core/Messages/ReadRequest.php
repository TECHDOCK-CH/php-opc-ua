<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\ReadValueId;
use TechDock\OpcUa\Core\Types\TimestampsToReturn;

/**
 * ReadRequest - reads one or more node attributes.
 */
final readonly class ReadRequest implements ServiceRequest
{
    private const int TYPE_ID = 631;

    /**
     * @param ReadValueId[] $nodesToRead
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public float $maxAge,
        public TimestampsToReturn $timestampsToReturn,
        public array $nodesToRead,
    ) {
    }

    /**
     * Create a ReadRequest with defaults.
     *
     * @param ReadValueId[] $nodesToRead
     */
    public static function create(
        array $nodesToRead,
        ?RequestHeader $requestHeader = null,
        float $maxAge = 0.0,
        TimestampsToReturn $timestampsToReturn = TimestampsToReturn::Both,
    ): self {
        if ($nodesToRead === []) {
            throw new InvalidArgumentException('ReadRequest requires at least one ReadValueId.');
        }

        foreach ($nodesToRead as $node) {
            if (!$node instanceof ReadValueId) {
                throw new InvalidArgumentException('nodesToRead must only contain ReadValueId instances.');
            }
        }

        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            maxAge: $maxAge,
            timestampsToReturn: $timestampsToReturn,
            nodesToRead: array_values($nodesToRead),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeDouble($this->maxAge);
        $encoder->writeInt32($this->timestampsToReturn->value);

        $encoder->writeInt32(count($this->nodesToRead));
        foreach ($this->nodesToRead as $node) {
            $node->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $maxAge = $decoder->readDouble();
        $timestamps = TimestampsToReturn::from($decoder->readInt32());

        $count = $decoder->readInt32();
        $nodesToRead = [];
        for ($i = 0; $i < $count; $i++) {
            $nodesToRead[] = ReadValueId::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            maxAge: $maxAge,
            timestampsToReturn: $timestamps,
            nodesToRead: $nodesToRead,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
