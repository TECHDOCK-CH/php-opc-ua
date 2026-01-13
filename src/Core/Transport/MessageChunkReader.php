<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * Reads and assembles OPC UA TCP message chunks.
 */
final class MessageChunkReader
{
    public function __construct(
        private readonly TcpConnectionInterface $connection,
        private readonly int $receiveBufferSize = 0,
        private readonly int $maxMessageSize = 0,
        private readonly int $maxChunkCount = 0,
    ) {
    }

    /**
     * Read all chunks for a single message.
     *
     * @return MessageChunk[]
     */
    public function read(MessageType $expectedType): array
    {
        $chunks = [];
        $totalBytes = 0;
        $chunkCount = 0;

        do {
            $header = $this->connection->receiveHeader();

            if ($header->messageType === MessageType::Error) {
                $payload = $this->connection->receive($header->getPayloadSize());
                $headerEncoder = new BinaryEncoder();
                $header->encode($headerEncoder);
                $decoder = new BinaryDecoder($headerEncoder->getBytes() . $payload);
                $error = ErrorMessage::decode($decoder);
                throw new RuntimeException("Server returned error: {$error->reason}");
            }

            if ($header->messageType !== $expectedType) {
                throw new RuntimeException(
                    "Expected {$expectedType->value} response, got {$header->messageType->value}"
                );
            }

            if ($this->receiveBufferSize > 0 && $header->messageSize > $this->receiveBufferSize) {
                throw new RuntimeException(
                    "Message chunk exceeds receive buffer size ({$header->messageSize} > {$this->receiveBufferSize})"
                );
            }

            $payload = $this->connection->receive($header->getPayloadSize());
            $chunks[] = new MessageChunk($header, $payload);

            $chunkCount++;
            $totalBytes += $header->messageSize;

            if ($this->maxChunkCount > 0 && $chunkCount > $this->maxChunkCount) {
                throw new RuntimeException(
                    "Message exceeds max chunk count ({$chunkCount} > {$this->maxChunkCount})"
                );
            }

            if ($this->maxMessageSize > 0 && $totalBytes > $this->maxMessageSize) {
                throw new RuntimeException(
                    "Message exceeds max size ({$totalBytes} > {$this->maxMessageSize})"
                );
            }
        } while (!$header->isFinal());

        return $chunks;
    }
}
