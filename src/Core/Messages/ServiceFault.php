<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * ServiceFault - Error response from server
 *
 * TypeId: ns=0;i=397
 *
 * Structure:
 * - ResponseHeader (with error StatusCode)
 */
final readonly class ServiceFault implements IEncodeable
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

        return new self(
            responseHeader: $responseHeader,
        );
    }
}
