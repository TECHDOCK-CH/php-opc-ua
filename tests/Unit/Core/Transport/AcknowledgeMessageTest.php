<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Transport;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Transport\AcknowledgeMessage;
use PHPUnit\Framework\TestCase;

final class AcknowledgeMessageTest extends TestCase
{
    public function testCreation(): void
    {
        $msg = new AcknowledgeMessage(
            protocolVersion: 0,
            receiveBufferSize: 65536,
            sendBufferSize: 65536,
            maxMessageSize: 16777216,
            maxChunkCount: 4096,
        );

        $this->assertSame(0, $msg->protocolVersion);
        $this->assertSame(65536, $msg->receiveBufferSize);
        $this->assertSame(65536, $msg->sendBufferSize);
        $this->assertSame(16777216, $msg->maxMessageSize);
        $this->assertSame(4096, $msg->maxChunkCount);
    }

    public function testEncodeDecode(): void
    {
        $msg = new AcknowledgeMessage(
            protocolVersion: 0,
            receiveBufferSize: 32768,
            sendBufferSize: 32768,
            maxMessageSize: 8388608,
            maxChunkCount: 2048,
        );

        $encoded = $msg->encode();

        $this->assertSame(28, strlen($encoded)); // 8 (header) + 20 (body: 5 * 4 bytes)

        $decoder = new BinaryDecoder($encoded);
        $decoded = AcknowledgeMessage::decode($decoder);

        $this->assertSame($msg->protocolVersion, $decoded->protocolVersion);
        $this->assertSame($msg->receiveBufferSize, $decoded->receiveBufferSize);
        $this->assertSame($msg->sendBufferSize, $decoded->sendBufferSize);
        $this->assertSame($msg->maxMessageSize, $decoded->maxMessageSize);
        $this->assertSame($msg->maxChunkCount, $decoded->maxChunkCount);
    }

    public function testToString(): void
    {
        $msg = new AcknowledgeMessage(0, 65536, 65536, 16777216, 4096);
        $str = $msg->toString();

        $this->assertStringContainsString('Acknowledge', $str);
        $this->assertStringContainsString('65536', $str);
    }

    public function testInvalidReceiveBufferSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AcknowledgeMessage(0, 1000, 65536, 16777216, 4096);
    }

    public function testInvalidSendBufferSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AcknowledgeMessage(0, 65536, 1000, 16777216, 4096);
    }

    public function testInvalidMaxMessageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AcknowledgeMessage(0, 65536, 65536, 1000, 4096);
    }

    public function testInvalidMaxChunkCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AcknowledgeMessage(0, 65536, 65536, 16777216, -1);
    }

    public function testMaxChunkCountZeroAllowed(): void
    {
        // 0 means unlimited chunks according to OPC UA spec
        $msg = new AcknowledgeMessage(0, 65536, 65536, 16777216, 0);

        $this->assertSame(0, $msg->maxChunkCount);
    }
}
