<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * Sequence Header (8 bytes)
 *
 * Appears after the message header in OPN/MSG/CLO messages
 *
 * Structure:
 * - SequenceNumber (UInt32)
 * - RequestId (UInt32)
 */
final readonly class SequenceHeader
{
    public const int HEADER_SIZE = 8;

    public function __construct(
        public int $sequenceNumber,
        public int $requestId,
    ) {
        if ($sequenceNumber < 0) {
            throw new InvalidArgumentException("Sequence number cannot be negative, got {$sequenceNumber}");
        }

        if ($requestId < 0) {
            throw new InvalidArgumentException("Request ID cannot be negative, got {$requestId}");
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->sequenceNumber);
        $encoder->writeUInt32($this->requestId);
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        $sequenceNumber = $decoder->readUInt32();
        $requestId = $decoder->readUInt32();

        return new self($sequenceNumber, $requestId);
    }
}
