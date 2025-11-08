<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * GetEndpoints service response
 */
final class GetEndpointsResponse
{
    /**
     * @param EndpointDescription[] $endpoints
     */
    public function __construct(
        public readonly ResponseHeader $responseHeader,
        public readonly array $endpoints,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode type ID for GetEndpointsResponse (431)
        NodeId::numeric(0, 431)->encode($encoder);

        // Encode response header
        $this->responseHeader->encode($encoder);

        // Encode endpoints array
        $encoder->writeInt32(count($this->endpoints));
        foreach ($this->endpoints as $endpoint) {
            $endpoint->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        // TypeId is handled by SecureChannel layer, not here
        // Decode response header
        $responseHeader = ResponseHeader::decode($decoder);

        // Decode endpoints array
        $endpointCount = $decoder->readInt32();
        $endpoints = [];
        for ($i = 0; $i < $endpointCount; $i++) {
            $endpoints[] = EndpointDescription::decode($decoder);
        }

        return new self(
            responseHeader: $responseHeader,
            endpoints: $endpoints,
        );
    }
}
