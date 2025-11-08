<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * Hello message (HEL) - sent by client to initiate connection
 *
 * Structure:
 * - Header (8 bytes)
 * - Protocol version (UInt32) - usually 0
 * - Receive buffer size (UInt32)
 * - Send buffer size (UInt32)
 * - Max message size (UInt32)
 * - Max chunk count (UInt32)
 * - Endpoint URL (String)
 */
final readonly class HelloMessage
{
    public const int DEFAULT_PROTOCOL_VERSION = 0;
    public const int DEFAULT_BUFFER_SIZE = 65536; // 64KB
    public const int DEFAULT_MAX_MESSAGE_SIZE = 16777216; // 16MB
    public const int DEFAULT_MAX_CHUNK_COUNT = 4096;

    public function __construct(
        public int $protocolVersion,
        public int $receiveBufferSize,
        public int $sendBufferSize,
        public int $maxMessageSize,
        public int $maxChunkCount,
        public string $endpointUrl,
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

        if ($maxMessageSize < 8192) {
            throw new InvalidArgumentException(
                "Max message size must be at least 8192 bytes, got {$maxMessageSize}"
            );
        }

        if ($maxChunkCount < 1) {
            throw new InvalidArgumentException("Max chunk count must be at least 1, got {$maxChunkCount}");
        }

        if ($endpointUrl === '') {
            throw new InvalidArgumentException('Endpoint URL cannot be empty');
        }
    }

    /**
     * Create Hello message with default values
     */
    public static function create(string $endpointUrl): self
    {
        return new self(
            protocolVersion: self::DEFAULT_PROTOCOL_VERSION,
            receiveBufferSize: self::DEFAULT_BUFFER_SIZE,
            sendBufferSize: self::DEFAULT_BUFFER_SIZE,
            maxMessageSize: self::DEFAULT_MAX_MESSAGE_SIZE,
            maxChunkCount: self::DEFAULT_MAX_CHUNK_COUNT,
            endpointUrl: $endpointUrl,
        );
    }

    /**
     * Encode the complete message (header + body)
     */
    public function encode(): string
    {
        // Encode body first to calculate size
        $bodyEncoder = new BinaryEncoder();
        $bodyEncoder->writeUInt32($this->protocolVersion);
        $bodyEncoder->writeUInt32($this->receiveBufferSize);
        $bodyEncoder->writeUInt32($this->sendBufferSize);
        $bodyEncoder->writeUInt32($this->maxMessageSize);
        $bodyEncoder->writeUInt32($this->maxChunkCount);
        $bodyEncoder->writeString($this->endpointUrl);

        $body = $bodyEncoder->getBytes();
        $totalSize = MessageHeader::HEADER_SIZE + strlen($body);

        // Create and encode header
        $header = MessageHeader::final(MessageType::Hello, $totalSize);
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

        if ($header->messageType !== MessageType::Hello) {
            throw new RuntimeException("Expected Hello message, got {$header->messageType->value}");
        }

        if (!$header->isFinal()) {
            throw new RuntimeException('Hello message must be a final chunk');
        }

        // Decode body
        $protocolVersion = $decoder->readUInt32();
        $receiveBufferSize = $decoder->readUInt32();
        $sendBufferSize = $decoder->readUInt32();
        $maxMessageSize = $decoder->readUInt32();
        $maxChunkCount = $decoder->readUInt32();
        $endpointUrl = $decoder->readString() ?? '';

        return new self(
            protocolVersion: $protocolVersion,
            receiveBufferSize: $receiveBufferSize,
            sendBufferSize: $sendBufferSize,
            maxMessageSize: $maxMessageSize,
            maxChunkCount: $maxChunkCount,
            endpointUrl: $endpointUrl,
        );
    }

    public function toString(): string
    {
        return "Hello(url={$this->endpointUrl}, buffers={$this->receiveBufferSize}/{$this->sendBufferSize})";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
