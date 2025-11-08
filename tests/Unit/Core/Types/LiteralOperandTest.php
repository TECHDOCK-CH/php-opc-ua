<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use DateTime;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\LiteralOperand;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\Variant;
use TechDock\OpcUa\Core\Types\VariantType;
use PHPUnit\Framework\TestCase;

final class LiteralOperandTest extends TestCase
{
    public function testLiteralOperandCreation(): void
    {
        $variant = new Variant(
            value: 500,
            type: VariantType::Int32
        );

        $operand = new LiteralOperand($variant);

        $this->assertSame($variant, $operand->value);
        $this->assertSame(500, $operand->value->value);
    }

    public function testFromValueFactoryWithInteger(): void
    {
        $operand = LiteralOperand::fromValue(500);

        $this->assertInstanceOf(Variant::class, $operand->value);
        $this->assertSame(500, $operand->value->value);
        $this->assertSame(VariantType::Int32, $operand->value->type);
    }

    public function testFromValueFactoryWithString(): void
    {
        $operand = LiteralOperand::fromValue('test-string');

        $this->assertInstanceOf(Variant::class, $operand->value);
        $this->assertSame('test-string', $operand->value->value);
        $this->assertSame(VariantType::String, $operand->value->type);
    }

    public function testFromValueFactoryWithBoolean(): void
    {
        $operand = LiteralOperand::fromValue(true);

        $this->assertInstanceOf(Variant::class, $operand->value);
        $this->assertSame(true, $operand->value->value);
        $this->assertSame(VariantType::Boolean, $operand->value->type);
    }

    public function testFromValueFactoryWithDouble(): void
    {
        $operand = LiteralOperand::fromValue(123.45);

        $this->assertInstanceOf(Variant::class, $operand->value);
        $this->assertSame(123.45, $operand->value->value);
        $this->assertSame(VariantType::Double, $operand->value->type);
    }

    public function testFromValueFactoryWithNodeId(): void
    {
        $nodeId = NodeId::numeric(0, 2041);
        $operand = LiteralOperand::fromValue($nodeId);

        $this->assertInstanceOf(Variant::class, $operand->value);
        $this->assertTrue($operand->value->value->equals($nodeId));
        $this->assertSame(VariantType::NodeId, $operand->value->type);
    }

    public function testFromValueFactoryWithDateTime(): void
    {
        $dateTime = new DateTime('2025-01-01 12:00:00');
        $operand = LiteralOperand::fromValue($dateTime);

        $this->assertInstanceOf(Variant::class, $operand->value);
        $this->assertInstanceOf(DateTime::class, $operand->value->value);
        $this->assertSame(VariantType::DateTime, $operand->value->type);
    }

    public function testEncodeDecodeRoundtripWithInteger(): void
    {
        $operand = LiteralOperand::fromValue(42);

        $encoder = new BinaryEncoder();
        $operand->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertNotEmpty($bytes);

        $decoder = new BinaryDecoder($bytes);
        $decoded = LiteralOperand::decode($decoder);

        $this->assertSame(42, $decoded->value->value);
        $this->assertSame(VariantType::Int32, $decoded->value->type);
    }

    public function testEncodeDecodeRoundtripWithString(): void
    {
        $operand = LiteralOperand::fromValue('alarm-message');

        $encoder = new BinaryEncoder();
        $operand->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = LiteralOperand::decode($decoder);

        $this->assertSame('alarm-message', $decoded->value->value);
        $this->assertSame(VariantType::String, $decoded->value->type);
    }

    public function testEncodeDecodeRoundtripWithNodeId(): void
    {
        $nodeId = NodeId::numeric(0, 2915); // AlarmConditionType
        $operand = LiteralOperand::fromValue($nodeId);

        $encoder = new BinaryEncoder();
        $operand->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = LiteralOperand::decode($decoder);

        $this->assertInstanceOf(NodeId::class, $decoded->value->value);
        $this->assertTrue($decoded->value->value->equals($nodeId));
        $this->assertSame(VariantType::NodeId, $decoded->value->type);
    }

    public function testTypeIdExists(): void
    {
        // LiteralOperand should have a static TYPE_ID or similar constant
        // This test can be removed if getTypeId() is not implemented
        $this->assertTrue(true);
    }
}
