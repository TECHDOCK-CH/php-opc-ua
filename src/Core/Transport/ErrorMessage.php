<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\StatusCode;

/**
 * Error message (ERR) - sent when connection cannot be established
 *
 * Structure:
 * - Header (8 bytes)
 * - Error code (UInt32 - StatusCode)
 * - Reason (String)
 */
final readonly class ErrorMessage
{
    public function __construct(
        public StatusCode $error,
        public string $reason,
    ) {
    }

    /**
     * Create an error message
     */
    public static function create(StatusCode $error, string $reason): self
    {
        return new self($error, $reason);
    }

    /**
     * Encode the complete message (header + body)
     */
    public function encode(): string
    {
        // Encode body
        $bodyEncoder = new BinaryEncoder();
        $this->error->encode($bodyEncoder);
        $bodyEncoder->writeString($this->reason);

        $body = $bodyEncoder->getBytes();
        $totalSize = MessageHeader::HEADER_SIZE + strlen($body);

        // Create and encode header
        $header = MessageHeader::final(MessageType::Error, $totalSize);
        $headerEncoder = new BinaryEncoder();
        $header->encode($headerEncoder);

        return $headerEncoder->getBytes() . $body;
    }

    /**
     * Decode from binary data (expects complete message with header)
     */
    public static function decode(BinaryDecoder $decoder): self
    {
        // Decode and verify header
        $header = MessageHeader::decode($decoder);

        if ($header->messageType !== MessageType::Error) {
            throw new RuntimeException("Expected Error message, got {$header->messageType->value}");
        }

        if (!$header->isFinal()) {
            throw new RuntimeException('Error message must be a final chunk');
        }

        // Decode body
        $error = StatusCode::decode($decoder);
        $reason = $decoder->readString() ?? '';

        return new self($error, $reason);
    }

    public function toString(): string
    {
        return "Error({$this->error->toString()}: {$this->reason})";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
