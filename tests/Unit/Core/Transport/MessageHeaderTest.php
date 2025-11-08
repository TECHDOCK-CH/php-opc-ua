<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Transport;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Transport\MessageHeader;
use TechDock\OpcUa\Core\Transport\MessageType;
use PHPUnit\Framework\TestCase;

final class MessageHeaderTest extends TestCase
{
    public function testCreation(): void
    {
        $header = new MessageHeader(MessageType::Hello, 'F', 100);

        $this->assertSame(MessageType::Hello, $header->messageType);
        $this->assertSame('F', $header->chunkType);
        $this->assertSame(100, $header->messageSize);
    }

    public function testFinalFactory(): void
    {
        $header = MessageHeader::final(MessageType::Hello, 1000);

        $this->assertTrue($header->isFinal());
        $this->assertFalse($header->isIntermediate());
        $this->assertFalse($header->isAbort());
    }

    public function testIntermediateFactory(): void
    {
        $header = MessageHeader::intermediate(MessageType::Message, 2000);

        $this->assertFalse($header->isFinal());
        $this->assertTrue($header->isIntermediate());
        $this->assertFalse($header->isAbort());
    }

    public function testAbortFactory(): void
    {
        $header = MessageHeader::abort(MessageType::Message, 3000);

        $this->assertFalse($header->isFinal());
        $this->assertFalse($header->isIntermediate());
        $this->assertTrue($header->isAbort());
    }

    public function testGetPayloadSize(): void
    {
        $header = new MessageHeader(MessageType::Hello, 'F', 100);

        $this->assertSame(92, $header->getPayloadSize()); // 100 - 8
    }

    public function testEncodeDecode(): void
    {
        $header = MessageHeader::final(MessageType::Acknowledge, 256);

        $encoder = new BinaryEncoder();
        $header->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertSame(8, strlen($bytes)); // Header is always 8 bytes

        $decoder = new BinaryDecoder($bytes);
        $decoded = MessageHeader::decode($decoder);

        $this->assertSame($header->messageType, $decoded->messageType);
        $this->assertSame($header->chunkType, $decoded->chunkType);
        $this->assertSame($header->messageSize, $decoded->messageSize);
    }

    public function testEncodeDecodeAllMessageTypes(): void
    {
        $types = [
            MessageType::Hello,
            MessageType::Acknowledge,
            MessageType::Error,
            MessageType::Message,
            MessageType::OpenSecureChannel,
            MessageType::CloseSecureChannel,
        ];

        foreach ($types as $type) {
            $header = MessageHeader::final($type, 1234);

            $encoder = new BinaryEncoder();
            $header->encode($encoder);
            $decoder = new BinaryDecoder($encoder->getBytes());
            $decoded = MessageHeader::decode($decoder);

            $this->assertSame($type, $decoded->messageType);
        }
    }

    public function testToString(): void
    {
        $header = MessageHeader::final(MessageType::Hello, 100);
        $str = $header->toString();

        $this->assertStringContainsString('HEL', $str);
        $this->assertStringContainsString('F', $str);
        $this->assertStringContainsString('100', $str);
    }

    public function testInvalidChunkTypeTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MessageHeader(MessageType::Hello, 'FF', 100);
    }

    public function testInvalidChunkTypeWrongChar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MessageHeader(MessageType::Hello, 'X', 100);
    }

    public function testInvalidMessageSizeTooSmall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MessageHeader(MessageType::Hello, 'F', 7);
    }
}
