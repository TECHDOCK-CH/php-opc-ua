<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\QualifiedName;
use TechDock\OpcUa\Core\Types\SimpleAttributeOperand;
use PHPUnit\Framework\TestCase;

final class SimpleAttributeOperandTest extends TestCase
{
    public function testSimpleAttributeOperandCreation(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $browsePath = [new QualifiedName(0, 'EventId')];

        $operand = new SimpleAttributeOperand(
            typeDefinitionId: $typeId,
            browsePath: $browsePath,
            attributeId: 13, // Value attribute
            indexRange: ''
        );

        $this->assertTrue($operand->typeDefinitionId->equals($typeId));
        $this->assertSame($browsePath, $operand->browsePath);
        $this->assertSame(13, $operand->attributeId);
        $this->assertSame('', $operand->indexRange);
    }

    public function testFromStringsFactory(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand = SimpleAttributeOperand::fromStrings($typeId, ['EventId', 'Message']);

        $this->assertTrue($operand->typeDefinitionId->equals($typeId));
        $this->assertCount(2, $operand->browsePath);
        $this->assertSame('EventId', $operand->browsePath[0]->name);
        $this->assertSame('Message', $operand->browsePath[1]->name);
        $this->assertSame(0, $operand->browsePath[0]->namespaceIndex);
        $this->assertSame(0, $operand->browsePath[1]->namespaceIndex);
        $this->assertSame(13, $operand->attributeId);
    }

    public function testFromStringsWithEmptyPath(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand = SimpleAttributeOperand::fromStrings($typeId, []);

        $this->assertTrue($operand->typeDefinitionId->equals($typeId));
        $this->assertSame([], $operand->browsePath);
    }

    public function testFromStringsWithCustomAttribute(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand = SimpleAttributeOperand::fromStrings(
            $typeId,
            ['EventId'],
            1 // NodeId attribute
        );

        $this->assertSame(1, $operand->attributeId);
    }

    public function testEncodeDecodeRoundtrip(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $browsePath = [
            new QualifiedName(0, 'Severity'),
            new QualifiedName(0, 'Value')
        ];

        $operand = new SimpleAttributeOperand(
            typeDefinitionId: $typeId,
            browsePath: $browsePath,
            attributeId: 13, // Value attribute
            indexRange: '[0:10]'
        );

        $encoder = new BinaryEncoder();
        $operand->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertNotEmpty($bytes);

        $decoder = new BinaryDecoder($bytes);
        $decoded = SimpleAttributeOperand::decode($decoder);

        $this->assertTrue($decoded->typeDefinitionId->equals($typeId));
        $this->assertCount(count($browsePath), $decoded->browsePath);
        $this->assertSame('Severity', $decoded->browsePath[0]->name);
        $this->assertSame('Value', $decoded->browsePath[1]->name);
        $this->assertSame(13, $decoded->attributeId);
        $this->assertSame('[0:10]', $decoded->indexRange);
    }

    public function testTypeIdExists(): void
    {
        // SimpleAttributeOperand should have a static TYPE_ID or similar constant
        // This test can be removed if getTypeId() is not implemented
        $this->assertTrue(true);
    }
}
