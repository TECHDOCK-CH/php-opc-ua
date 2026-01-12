<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * Acknowledge message (ACK) - sent by server in response to Hello
 *
 * Structure:
 * - Header (8 bytes)
 * - Protocol version (UInt32) - usually 0
 * - Receive buffer size (UInt32)
 * - Send buffer size (UInt32)
 * - Max message size (UInt32)
 * - Max chunk count (UInt32)
 */
final readonly class AcknowledgeMessage
{
    public function __construct(
        public int $protocolVersion,
        public int $receiveBufferSize,
        public int $sendBufferSize,
        public int $maxMessageSize,
        public int $maxChunkCount,
    ) {
        if ($receiveBufferSize < 8192) {
            throw new InvalidArgumentException(
                "Receive buffer size must be at least 8192 bytes, got {$receiveBufferSize}"
            );
        }

        if ($sendBufferSize < 8192) {
            throw new InvalidArgumentException(
                "Send buffer size must be at least 8192 bytes, got {$sendBufferSize}"
            );
        }

        // Per OPC UA spec: 0 means unlimited, otherwise must be at least 8192
        if ($maxMessageSize !== 0 && $maxMessageSize < 8192) {
            throw new InvalidArgumentException("Max message size must be 0 (unlimited) or at least 8192 bytes, got {$maxMessageSize}");
        }

        if ($maxChunkCount < 0) {
            throw new InvalidArgumentException("Max chunk count cannot be negative, got {$maxChunkCount}");
        }
    }

    /**
     * Encode the complete message (header + body)
     */
    public function encode(): string
    {
        // Encode body
        $bodyEncoder = new BinaryEncoder();
        $bodyEncoder->writeUInt32($this->protocolVersion);
        $bodyEncoder->writeUInt32($this->receiveBufferSize);
        $bodyEncoder->writeUInt32($this->sendBufferSize);
        $bodyEncoder->writeUInt32($this->maxMessageSize);
        $bodyEncoder->writeUInt32($this->maxChunkCount);

        $body = $bodyEncoder->getBytes();
        $totalSize = MessageHeader::HEADER_SIZE + strlen($body);

        // Create and encode header
        $header = MessageHeader::final(MessageType::Acknowledge, $totalSize);
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

        if ($header->messageType !== MessageType::Acknowledge) {
            throw new RuntimeException("Expected Acknowledge message, got {$header->messageType->value}");
        }

        if (!$header->isFinal()) {
            throw new RuntimeException('Acknowledge message must be a final chunk');
        }

        // Decode body
        $protocolVersion = $decoder->readUInt32();
        $receiveBufferSize = $decoder->readUInt32();
        $sendBufferSize = $decoder->readUInt32();
        $maxMessageSize = $decoder->readUInt32();
        $maxChunkCount = $decoder->readUInt32();

        return new self(
            protocolVersion: $protocolVersion,
            receiveBufferSize: $receiveBufferSize,
            sendBufferSize: $sendBufferSize,
            maxMessageSize: $maxMessageSize,
            maxChunkCount: $maxChunkCount,
        );
    }

    public function toString(): string
    {
        return "Acknowledge(buffers={$this->receiveBufferSize}/{$this->sendBufferSize}, " .
            "maxMsg={$this->maxMessageSize})";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
