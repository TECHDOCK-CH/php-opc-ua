<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Transport;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TechDock\OpcUa\Core\Transport\MessageChunkReader;
use TechDock\OpcUa\Core\Transport\MessageHeader;
use TechDock\OpcUa\Core\Transport\MessageType;
use TechDock\OpcUa\Core\Transport\TcpConnectionInterface;

final class MessageChunkReaderTest extends TestCase
{
    public function testReadsMultipleChunks(): void
    {
        $chunks = [
            $this->makeChunk('part1', MessageHeader::intermediate(MessageType::Message, 8 + 5)),
            $this->makeChunk('part2', MessageHeader::final(MessageType::Message, 8 + 5)),
        ];

        $connection = new FakeTcpConnection($chunks);
        $reader = new MessageChunkReader($connection);

        $result = $reader->read(MessageType::Message);

        self::assertCount(2, $result);
        self::assertSame('part1', $result[0]->payload);
        self::assertSame('part2', $result[1]->payload);
    }

    public function testEnforcesMaxChunkCount(): void
    {
        $chunks = [
            $this->makeChunk('one', MessageHeader::intermediate(MessageType::Message, 8 + 3)),
            $this->makeChunk('two', MessageHeader::final(MessageType::Message, 8 + 3)),
        ];

        $connection = new FakeTcpConnection($chunks);
        $reader = new MessageChunkReader($connection, maxChunkCount: 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Message exceeds max chunk count');

        $reader->read(MessageType::Message);
    }

    public function testEnforcesMaxMessageSize(): void
    {
        $chunks = [
            $this->makeChunk('one', MessageHeader::intermediate(MessageType::Message, 8 + 3)),
            $this->makeChunk('two', MessageHeader::final(MessageType::Message, 8 + 3)),
        ];

        $connection = new FakeTcpConnection($chunks);
        $reader = new MessageChunkReader($connection, maxMessageSize: 11);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Message exceeds max size');

        $reader->read(MessageType::Message);
    }

    public function testEnforcesReceiveBufferSize(): void
    {
        $chunks = [
            $this->makeChunk('long', MessageHeader::final(MessageType::Message, 8 + 4)),
        ];

        $connection = new FakeTcpConnection($chunks);
        $reader = new MessageChunkReader($connection, receiveBufferSize: 11);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Message chunk exceeds receive buffer size');

        $reader->read(MessageType::Message);
    }

    /**
     * @return array{header: MessageHeader, payload: string}
     */
    private function makeChunk(string $payload, MessageHeader $header): array
    {
        return [
            'header' => $header,
            'payload' => $payload,
        ];
    }
}

final class FakeTcpConnection implements TcpConnectionInterface
{
    private int $index = 0;
    private ?array $current = null;

    /**
     * @param array<int, array{header: MessageHeader, payload: string}> $chunks
     */
    public function __construct(private array $chunks)
    {
    }

    public function connect(): void
    {
    }

    public function send(string $data): void
    {
    }

    public function receive(int $length): string
    {
        if ($this->current === null) {
            throw new RuntimeException('No header read');
        }

        $payload = $this->current['payload'];
        $this->current = null;
        $this->index++;

        if (strlen($payload) !== $length) {
            throw new RuntimeException('Payload length mismatch');
        }

        return $payload;
    }

    public function receiveHeader(): MessageHeader
    {
        if (!isset($this->chunks[$this->index])) {
            throw new RuntimeException('No more chunks');
        }

        $this->current = $this->chunks[$this->index];

        return $this->current['header'];
    }

    public function receiveMessage(): string
    {
        throw new RuntimeException('Not implemented');
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function close(): void
    {
    }

    public function getEndpointUrl(): string
    {
        return 'opc.tcp://test';
    }
}
