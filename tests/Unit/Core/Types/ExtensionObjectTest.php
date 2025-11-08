<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\QualifiedName;
use PHPUnit\Framework\TestCase;

final class ExtensionObjectTest extends TestCase
{
    public function testCreationBinary(): void
    {
        $typeId = NodeId::numeric(0, 123);
        $body = 'binary data';

        $ext = new ExtensionObject($typeId, $body, 0x01);

        $this->assertTrue($ext->typeId->equals($typeId));
        $this->assertSame($body, $ext->body);
        $this->assertSame(0x01, $ext->encoding);
        $this->assertTrue($ext->isBinary());
        $this->assertTrue($ext->hasBody());
    }

    public function testCreationEmpty(): void
    {
        $typeId = NodeId::numeric(0, 456);
        $ext = ExtensionObject::empty($typeId);

        $this->assertTrue($ext->typeId->equals($typeId));
        $this->assertNull($ext->body);
        $this->assertSame(0x00, $ext->encoding);
        $this->assertFalse($ext->hasBody());
    }

    public function testCreationXml(): void
    {
        $typeId = NodeId::numeric(0, 789);
        $ext = ExtensionObject::xml($typeId, '<test>data</test>');

        $this->assertTrue($ext->isXml());
        $this->assertSame(0x02, $ext->encoding);
    }

    public function testFromEncodeable(): void
    {
        $typeId = NodeId::numeric(0, 12);
        $qn = new QualifiedName(2, 'TestName');

        $ext = ExtensionObject::fromEncodeable($typeId, $qn);

        $this->assertTrue($ext->isBinary());
        $this->assertNotNull($ext->body);
        $this->assertGreaterThan(0, $ext->getBodyLength());
    }

    public function testEncodeDecode(): void
    {
        $typeId = NodeId::numeric(0, 999);
        $ext = ExtensionObject::binary($typeId, 'test data');

        $encoder = new BinaryEncoder();
        $ext->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = ExtensionObject::decode($decoder);

        $this->assertTrue($ext->equals($decoded));
        $this->assertTrue($ext->typeId->equals($decoded->typeId));
        $this->assertSame($ext->body, $decoded->body);
        $this->assertSame($ext->encoding, $decoded->encoding);
    }

    public function testEncodeDecodeEmpty(): void
    {
        $typeId = NodeId::numeric(0, 111);
        $ext = ExtensionObject::empty($typeId);

        $encoder = new BinaryEncoder();
        $ext->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = ExtensionObject::decode($decoder);

        $this->assertTrue($ext->equals($decoded));
        $this->assertFalse($decoded->hasBody());
    }

    public function testToString(): void
    {
        $typeId = NodeId::numeric(0, 123);
        $ext = ExtensionObject::binary($typeId, 'hello');

        $str = $ext->toString();

        $this->assertStringContainsString('Binary', $str);
        $this->assertStringContainsString('5 bytes', $str);
    }

    public function testGetBodyLength(): void
    {
        $typeId = NodeId::numeric(0, 1);
        $ext1 = ExtensionObject::binary($typeId, '12345');
        $ext2 = ExtensionObject::empty($typeId);

        $this->assertSame(5, $ext1->getBodyLength());
        $this->assertSame(0, $ext2->getBodyLength());
    }

    public function testInvalidEncoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $typeId = NodeId::numeric(0, 1);
        new ExtensionObject($typeId, 'data', 0x99);
    }

    public function testInvalidNullBodyWithEncoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $typeId = NodeId::numeric(0, 1);
        new ExtensionObject($typeId, null, 0x01);
    }
}
