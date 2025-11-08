<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * CloseSessionResponse - Response to CloseSessionRequest
 */
final readonly class CloseSessionResponse implements IEncodeable
{
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

        // Check if response is an error
        if (!$responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "Server returned error: {$responseHeader->serviceResult}"
            );
        }

        return new self(
            responseHeader: $responseHeader,
        );
    }
}
