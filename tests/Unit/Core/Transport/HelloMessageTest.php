<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Transport;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Transport\HelloMessage;
use PHPUnit\Framework\TestCase;

final class HelloMessageTest extends TestCase
{
    public function testCreation(): void
    {
        $msg = new HelloMessage(
            protocolVersion: 0,
            receiveBufferSize: 65536,
            sendBufferSize: 65536,
            maxMessageSize: 16777216,
            maxChunkCount: 4096,
            endpointUrl: 'opc.tcp://localhost:4840',
        );

        $this->assertSame(0, $msg->protocolVersion);
        $this->assertSame(65536, $msg->receiveBufferSize);
        $this->assertSame(65536, $msg->sendBufferSize);
        $this->assertSame(16777216, $msg->maxMessageSize);
        $this->assertSame(4096, $msg->maxChunkCount);
        $this->assertSame('opc.tcp://localhost:4840', $msg->endpointUrl);
    }

    public function testCreateFactory(): void
    {
        $msg = HelloMessage::create('opc.tcp://localhost:4840');

        $this->assertSame(0, $msg->protocolVersion);
        $this->assertSame(65536, $msg->receiveBufferSize);
        $this->assertSame('opc.tcp://localhost:4840', $msg->endpointUrl);
    }

    public function testEncodeDecode(): void
    {
        $msg = HelloMessage::create('opc.tcp://example.com:4840/test');

        $encoded = $msg->encode();

        $this->assertGreaterThan(8, strlen($encoded)); // At least header size

        $decoder = new BinaryDecoder($encoded);
        $decoded = HelloMessage::decode($decoder);

        $this->assertSame($msg->protocolVersion, $decoded->protocolVersion);
        $this->assertSame($msg->receiveBufferSize, $decoded->receiveBufferSize);
        $this->assertSame($msg->sendBufferSize, $decoded->sendBufferSize);
        $this->assertSame($msg->maxMessageSize, $decoded->maxMessageSize);
        $this->assertSame($msg->maxChunkCount, $decoded->maxChunkCount);
        $this->assertSame($msg->endpointUrl, $decoded->endpointUrl);
    }

    public function testToString(): void
    {
        $msg = HelloMessage::create('opc.tcp://localhost:4840');
        $str = $msg->toString();

        $this->assertStringContainsString('Hello', $str);
        $this->assertStringContainsString('opc.tcp://localhost:4840', $str);
    }

    public function testInvalidReceiveBufferSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HelloMessage(0, 1000, 65536, 16777216, 4096, 'opc.tcp://localhost:4840');
    }

    public function testInvalidSendBufferSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HelloMessage(0, 65536, 1000, 16777216, 4096, 'opc.tcp://localhost:4840');
    }

    public function testInvalidMaxMessageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HelloMessage(0, 65536, 65536, 1000, 4096, 'opc.tcp://localhost:4840');
    }

    public function testInvalidMaxChunkCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HelloMessage(0, 65536, 65536, 16777216, 0, 'opc.tcp://localhost:4840');
    }

    public function testInvalidEmptyEndpointUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HelloMessage(0, 65536, 65536, 16777216, 4096, '');
    }
}
