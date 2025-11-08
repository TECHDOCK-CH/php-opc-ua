<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\StructureDefinition;
use TechDock\OpcUa\Core\Types\StructureField;
use TechDock\OpcUa\Core\Types\StructureType;
use PHPUnit\Framework\TestCase;

final class StructureDefinitionTest extends TestCase
{
    public function testCreation(): void
    {
        $fields = [
            new StructureField(
                name: 'Field1',
                description: null,
                dataType: NodeId::numeric(0, 6), // Int32
                valueRank: -1,
                arrayDimensions: null,
                maxStringLength: 0,
                isOptional: false,
            ),
            new StructureField(
                name: 'Field2',
                description: null,
                dataType: NodeId::numeric(0, 12), // String
                valueRank: -1,
                arrayDimensions: null,
                maxStringLength: 256,
                isOptional: false,
            ),
        ];

        $def = new StructureDefinition(
            defaultEncodingId: NodeId::numeric(0, 100),
            baseDataType: NodeId::numeric(0, 22), // Structure
            structureType: StructureType::Structure,
            fields: $fields,
        );

        $this->assertTrue($def->defaultEncodingId->equals(NodeId::numeric(0, 100)));
        $this->assertTrue($def->baseDataType->equals(NodeId::numeric(0, 22)));
        $this->assertSame(StructureType::Structure, $def->structureType);
        $this->assertCount(2, $def->fields);
        $this->assertSame(2, $def->getFieldCount());
    }

    public function testGetField(): void
    {
        $field1 = new StructureField(
            name: 'TestField',
            description: null,
            dataType: NodeId::numeric(0, 6),
            valueRank: -1,
            arrayDimensions: null,
            maxStringLength: 0,
            isOptional: false,
        );

        $def = new StructureDefinition(
            defaultEncodingId: NodeId::numeric(0, 100),
            baseDataType: NodeId::numeric(0, 22),
            structureType: StructureType::Structure,
            fields: [$field1],
        );

        $found = $def->getField('TestField');
        $this->assertNotNull($found);
        $this->assertSame('TestField', $found->name);

        $notFound = $def->getField('NonExistent');
        $this->assertNull($notFound);
    }

    public function testGetFieldNames(): void
    {
        $fields = [
            new StructureField(
                name: 'Alpha',
                description: null,
                dataType: NodeId::numeric(0, 6),
                valueRank: -1,
                arrayDimensions: null,
                maxStringLength: 0,
                isOptional: false,
            ),
            new StructureField(
                name: 'Beta',
                description: null,
                dataType: NodeId::numeric(0, 12),
                valueRank: -1,
                arrayDimensions: null,
                maxStringLength: 0,
                isOptional: false,
            ),
        ];

        $def = new StructureDefinition(
            defaultEncodingId: NodeId::numeric(0, 100),
            baseDataType: NodeId::numeric(0, 22),
            structureType: StructureType::Structure,
            fields: $fields,
        );

        $names = $def->getFieldNames();
        $this->assertSame(['Alpha', 'Beta'], $names);
    }

    public function testEncodeDecode(): void
    {
        $fields = [
            new StructureField(
                name: 'IntField',
                description: null,
                dataType: NodeId::numeric(0, 6), // Int32
                valueRank: -1,
                arrayDimensions: null,
                maxStringLength: 0,
                isOptional: false,
            ),
        ];

        $def = new StructureDefinition(
            defaultEncodingId: NodeId::numeric(0, 862),
            baseDataType: NodeId::numeric(0, 22),
            structureType: StructureType::StructureWithOptionalFields,
            fields: $fields,
        );

        $encoder = new BinaryEncoder();
        $def->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = StructureDefinition::decode($decoder);

        $this->assertSame($def->defaultEncodingId->toString(), $decoded->defaultEncodingId->toString());
        $this->assertSame($def->baseDataType->toString(), $decoded->baseDataType->toString());
        $this->assertSame($def->structureType, $decoded->structureType);
        $this->assertCount(1, $decoded->fields);
        $this->assertSame('IntField', $decoded->fields[0]->name);
    }

    public function testToString(): void
    {
        $def = new StructureDefinition(
            defaultEncodingId: NodeId::numeric(0, 100),
            baseDataType: NodeId::numeric(0, 22),
            structureType: StructureType::Union,
            fields: [],
        );

        $str = $def->toString();
        $this->assertStringContainsString('Union', $str);
        $this->assertStringContainsString('0 fields', $str);
    }

    public function testInvalidFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StructureDefinition(
            defaultEncodingId: NodeId::numeric(0, 100),
            baseDataType: NodeId::numeric(0, 22),
            structureType: StructureType::Structure,
            fields: ['not a StructureField'], // Invalid!
        );
    }

    public function testGetTypeId(): void
    {
        $typeId = StructureDefinition::getTypeId();
        $this->assertTrue($typeId->equals(NodeId::numeric(0, 99)));
    }
}
