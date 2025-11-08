<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * OPC UA TCP Message Header (8 bytes)
 *
 * Structure:
 * - Bytes 0-2: Message type (3 ASCII characters)
 * - Byte 3: Chunk type ('F' = Final, 'C' = Intermediate, 'A' = Abort)
 * - Bytes 4-7: Message size (UInt32, includes header)
 */
final readonly class MessageHeader
{
    public const int HEADER_SIZE = 8;

    public function __construct(
        public MessageType $messageType,
        public string $chunkType,
        public int $messageSize,
    ) {
        if (strlen($chunkType) !== 1) {
            throw new InvalidArgumentException("Chunk type must be 1 character, got '{$chunkType}'");
        }

        if (!in_array($chunkType, ['F', 'C', 'A'], true)) {
            throw new InvalidArgumentException("Invalid chunk type: '{$chunkType}'");
        }

        if ($messageSize < self::HEADER_SIZE) {
            throw new InvalidArgumentException(
                "Message size must be at least " . self::HEADER_SIZE . " bytes, got {$messageSize}"
            );
        }
    }

    /**
     * Create a header for a final (complete) message
     */
    public static function final(MessageType $type, int $messageSize): self
    {
        return new self($type, 'F', $messageSize);
    }

    /**
     * Create a header for an intermediate chunk
     */
    public static function intermediate(MessageType $type, int $messageSize): self
    {
        return new self($type, 'C', $messageSize);
    }

    /**
     * Create a header for an abort chunk
     */
    public static function abort(MessageType $type, int $messageSize): self
    {
        return new self($type, 'A', $messageSize);
    }

    /**
     * Get the payload size (message size - header size)
     */
    public function getPayloadSize(): int
    {
        return $this->messageSize - self::HEADER_SIZE;
    }

    /**
     * Check if this is a final chunk
     */
    public function isFinal(): bool
    {
        return $this->chunkType === 'F';
    }

    /**
     * Check if this is an intermediate chunk
     */
    public function isIntermediate(): bool
    {
        return $this->chunkType === 'C';
    }

    /**
     * Check if this is an abort chunk
     */
    public function isAbort(): bool
    {
        return $this->chunkType === 'A';
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Write message type (3 bytes)
        $encoder->writeByte(ord($this->messageType->value[0]));
        $encoder->writeByte(ord($this->messageType->value[1]));
        $encoder->writeByte(ord($this->messageType->value[2]));

        // Write chunk type (1 byte)
        $encoder->writeByte(ord($this->chunkType));

        // Write message size (4 bytes)
        $encoder->writeUInt32($this->messageSize);
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        // Read message type (3 bytes)
        $byte1 = chr($decoder->readByte());
        $byte2 = chr($decoder->readByte());
        $byte3 = chr($decoder->readByte());
        $messageTypeStr = $byte1 . $byte2 . $byte3;

        $messageType = MessageType::from($messageTypeStr);

        // Read chunk type (1 byte)
        $chunkType = chr($decoder->readByte());

        // Read message size (4 bytes)
        $messageSize = $decoder->readUInt32();

        return new self($messageType, $chunkType, $messageSize);
    }

    public function toString(): string
    {
        return "{$this->messageType->value}/{$this->chunkType} ({$this->messageSize} bytes)";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
