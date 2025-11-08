<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * UnregisterNodesResponse - Response confirming nodes unregistered
 */
final readonly class UnregisterNodesResponse implements IEncodeable, ServiceResponse
{
    private const int TYPE_ID = 567;

    public function __construct(
        public ResponseHeader $responseHeader,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);

        return new self(
            responseHeader: $responseHeader,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
