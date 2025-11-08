<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * CloseSecureChannelRequest - Closes a secure channel
 *
 * Structure:
 * - RequestHeader
 *
 * Note: This is a "fire and forget" message - no response is expected
 */
final readonly class CloseSecureChannelRequest implements IEncodeable
{
    public function __construct(
        public RequestHeader $requestHeader,
    ) {
    }

    /**
     * Create a CloseSecureChannelRequest
     */
    public static function create(): self
    {
        return new self(
            requestHeader: RequestHeader::create(),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);

        return new self(
            requestHeader: $requestHeader,
        );
    }
}
