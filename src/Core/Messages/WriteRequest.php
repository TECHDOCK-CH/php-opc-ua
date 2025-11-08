<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\WriteValue;

/**
 * WriteRequest - writes one or more node attributes.
 */
final readonly class WriteRequest implements ServiceRequest
{
    private const int TYPE_ID = 673;

    /**
     * @param WriteValue[] $nodesToWrite
     */
    public function __construct(
        public RequestHeader $requestHeader,
        public array $nodesToWrite,
    ) {
    }

    /**
     * Create a WriteRequest with defaults.
     *
     * @param WriteValue[] $nodesToWrite
     */
    public static function create(
        array $nodesToWrite,
        ?RequestHeader $requestHeader = null,
    ): self {
        if ($nodesToWrite === []) {
            throw new InvalidArgumentException('WriteRequest requires at least one WriteValue.');
        }

        foreach ($nodesToWrite as $value) {
            if (!$value instanceof WriteValue) {
                throw new InvalidArgumentException('nodesToWrite must only contain WriteValue instances.');
            }
        }

        return new self(
            requestHeader: $requestHeader ?? RequestHeader::create(),
            nodesToWrite: array_values($nodesToWrite),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        $encoder->writeInt32(count($this->nodesToWrite));
        foreach ($this->nodesToWrite as $value) {
            $value->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);

        $count = $decoder->readInt32();
        $nodesToWrite = [];
        for ($i = 0; $i < $count; $i++) {
            $nodesToWrite[] = WriteValue::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            nodesToWrite: $nodesToWrite,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
