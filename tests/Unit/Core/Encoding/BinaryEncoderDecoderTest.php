<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Encoding;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use PHPUnit\Framework\TestCase;

final class BinaryEncoderDecoderTest extends TestCase
{
    public function testEncodeDecodeBoolean(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeBoolean(true);
        $encoder->writeBoolean(false);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertTrue($decoder->readBoolean());
        $this->assertFalse($decoder->readBoolean());
    }

    public function testEncodeDecodeByte(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeByte(0);
        $encoder->writeByte(127);
        $encoder->writeByte(255);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertSame(0, $decoder->readByte());
        $this->assertSame(127, $decoder->readByte());
        $this->assertSame(255, $decoder->readByte());
    }

    public function testEncodeDecodeInt16(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeInt16(-32768);
        $encoder->writeInt16(0);
        $encoder->writeInt16(32767);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertSame(-32768, $decoder->readInt16());
        $this->assertSame(0, $decoder->readInt16());
        $this->assertSame(32767, $decoder->readInt16());
    }

    public function testEncodeDecodeUInt16(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeUInt16(0);
        $encoder->writeUInt16(32768);
        $encoder->writeUInt16(65535);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertSame(0, $decoder->readUInt16());
        $this->assertSame(32768, $decoder->readUInt16());
        $this->assertSame(65535, $decoder->readUInt16());
    }

    public function testEncodeDecodeInt32(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeInt32(-2147483648);
        $encoder->writeInt32(0);
        $encoder->writeInt32(2147483647);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertSame(-2147483648, $decoder->readInt32());
        $this->assertSame(0, $decoder->readInt32());
        $this->assertSame(2147483647, $decoder->readInt32());
    }

    public function testEncodeDecodeUInt32(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeUInt32(0);
        $encoder->writeUInt32(2147483648);
        $encoder->writeUInt32(4294967295);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertSame(0, $decoder->readUInt32());
        $this->assertSame(2147483648, $decoder->readUInt32());
        $this->assertSame(4294967295, $decoder->readUInt32());
    }

    public function testEncodeDecodeFloat(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeFloat(0.0);
        $encoder->writeFloat(3.14159);
        $encoder->writeFloat(-273.15);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertEqualsWithDelta(0.0, $decoder->readFloat(), 0.0001);
        $this->assertEqualsWithDelta(3.14159, $decoder->readFloat(), 0.0001);
        $this->assertEqualsWithDelta(-273.15, $decoder->readFloat(), 0.01);
    }

    public function testEncodeDecodeDouble(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeDouble(0.0);
        $encoder->writeDouble(3.141592653589793);
        $encoder->writeDouble(-273.15);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertEqualsWithDelta(0.0, $decoder->readDouble(), 0.0000001);
        $this->assertEqualsWithDelta(3.141592653589793, $decoder->readDouble(), 0.0000001);
        $this->assertEqualsWithDelta(-273.15, $decoder->readDouble(), 0.0000001);
    }

    public function testEncodeDecodeString(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeString('Hello, World!');
        $encoder->writeString('');
        $encoder->writeString(null);
        $encoder->writeString('UTF-8: äöü 中文 🚀');

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertSame('Hello, World!', $decoder->readString());
        $this->assertSame('', $decoder->readString());
        $this->assertNull($decoder->readString());
        $this->assertSame('UTF-8: äöü 中文 🚀', $decoder->readString());
    }

    public function testEncodeDecodeByteString(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeByteString("\x00\x01\x02\x03\xFF");
        $encoder->writeByteString('');
        $encoder->writeByteString(null);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertSame("\x00\x01\x02\x03\xFF", $decoder->readByteString());
        $this->assertSame('', $decoder->readByteString());
        $this->assertNull($decoder->readByteString());
    }

    public function testEncodeDecodeGuid(): void
    {
        $guid = '12345678-1234-5678-1234-567812345678';

        $encoder = new BinaryEncoder();
        $encoder->writeGuid($guid);

        $bytes = $encoder->getBytes();
        $decoder = new BinaryDecoder($bytes);

        $this->assertSame($guid, $decoder->readGuid());
    }

    public function testLittleEndianByteOrder(): void
    {
        // Test that multi-byte integers are encoded in little-endian
        $encoder = new BinaryEncoder();
        $encoder->writeUInt32(0x12345678);

        $bytes = $encoder->getBytes();

        // Little-endian: least significant byte first
        $this->assertSame("\x78\x56\x34\x12", $bytes);
    }
}
