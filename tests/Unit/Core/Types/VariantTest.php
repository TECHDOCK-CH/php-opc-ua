<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\Variant;
use TechDock\OpcUa\Core\Types\VariantType;
use PHPUnit\Framework\TestCase;

final class VariantTest extends TestCase
{
    public function testNull(): void
    {
        $variant = Variant::null();

        $this->assertTrue($variant->isNull());
        $this->assertSame(VariantType::Null, $variant->type);
        $this->assertNull($variant->value);
        $this->assertFalse($variant->isArray());
    }

    public function testBoolean(): void
    {
        $variant = Variant::boolean(true);

        $this->assertSame(VariantType::Boolean, $variant->type);
        $this->assertTrue($variant->value);
        $this->assertFalse($variant->isArray());
    }

    public function testInt32(): void
    {
        $variant = Variant::int32(42);

        $this->assertSame(VariantType::Int32, $variant->type);
        $this->assertSame(42, $variant->value);
    }

    public function testString(): void
    {
        $variant = Variant::string('Hello World');

        $this->assertSame(VariantType::String, $variant->type);
        $this->assertSame('Hello World', $variant->value);
    }

    public function testEncodeDecodeScalars(): void
    {
        $variants = [
            Variant::boolean(true),
            Variant::int32(123),
            Variant::uint32(456),
            Variant::float(3.14),
            Variant::double(2.718),
            Variant::string('test'),
        ];

        foreach ($variants as $variant) {
            $encoder = new BinaryEncoder();
            $variant->encode($encoder);
            $bytes = $encoder->getBytes();

            $decoder = new BinaryDecoder($bytes);
            $decoded = Variant::decode($decoder);

            $this->assertSame($variant->type, $decoded->type);

            // Use delta comparison for floats due to precision loss
            if ($variant->type === VariantType::Float || $variant->type === VariantType::Double) {
                $this->assertEqualsWithDelta($variant->value, $decoded->value, 0.0001);
            } else {
                $this->assertSame($variant->value, $decoded->value);
            }
        }
    }

    public function testEncodeDecodeArray(): void
    {
        $variant = new Variant(VariantType::Int32, [1, 2, 3, 4, 5]);

        $this->assertTrue($variant->isArray());
        $this->assertFalse($variant->hasDimensions());

        $encoder = new BinaryEncoder();
        $variant->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = Variant::decode($decoder);

        $this->assertTrue($decoded->isArray());
        $this->assertSame([1, 2, 3, 4, 5], $decoded->value);
    }

    public function testEncodeDecodeArrayWithDimensions(): void
    {
        $variant = new Variant(VariantType::Int32, [1, 2, 3, 4, 5, 6], [2, 3]);

        $this->assertTrue($variant->isArray());
        $this->assertTrue($variant->hasDimensions());
        $this->assertSame([2, 3], $variant->dimensions);

        $encoder = new BinaryEncoder();
        $variant->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = Variant::decode($decoder);

        $this->assertTrue($decoded->isArray());
        $this->assertTrue($decoded->hasDimensions());
        $this->assertSame([2, 3], $decoded->dimensions);
        $this->assertSame([1, 2, 3, 4, 5, 6], $decoded->value);
    }

    public function testEncodeDecodeNodeIdArray(): void
    {
        $nodeIds = [
            NodeId::numeric(0, 1),
            NodeId::numeric(0, 2),
            NodeId::numeric(0, 3),
        ];

        $variant = new Variant(VariantType::NodeId, $nodeIds);

        $encoder = new BinaryEncoder();
        $variant->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = Variant::decode($decoder);

        $this->assertTrue($decoded->isArray());
        $this->assertCount(3, $decoded->value);
        $this->assertInstanceOf(NodeId::class, $decoded->value[0]);
    }

    public function testToString(): void
    {
        $variant1 = Variant::int32(42);
        $this->assertStringContainsString('Int32', $variant1->toString());
        $this->assertStringContainsString('42', $variant1->toString());

        $variant2 = Variant::null();
        $this->assertSame('null', $variant2->toString());

        $variant3 = new Variant(VariantType::Int32, [1, 2, 3]);
        $str = $variant3->toString();
        $this->assertStringContainsString('Int32', $str);
        $this->assertStringContainsString('[3]', $str);
    }

    public function testInvalidNullWithValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Variant(VariantType::Null, 42);
    }

    public function testInvalidDimensionsWithoutArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Variant(VariantType::Int32, 42, [1, 2]);
    }
}
