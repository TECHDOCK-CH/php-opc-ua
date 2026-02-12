<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * CloseSessionRequest - Closes an active session
 */
final readonly class CloseSessionRequest implements IEncodeable, ServiceRequest
{
    private const int TYPE_ID = 473;

    public function __construct(
        public RequestHeader $requestHeader,
        public bool $deleteSubscriptions,
    ) {
    }

    /**
     * Create a CloseSessionRequest
     */
    public static function create(
        RequestHeader $requestHeader,
        bool $deleteSubscriptions = true,
    ): self {
        return new self(
            requestHeader: $requestHeader,
            deleteSubscriptions: $deleteSubscriptions,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeBoolean($this->deleteSubscriptions);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $deleteSubscriptions = $decoder->readBoolean();

        return new self(
            requestHeader: $requestHeader,
            deleteSubscriptions: $deleteSubscriptions,
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
