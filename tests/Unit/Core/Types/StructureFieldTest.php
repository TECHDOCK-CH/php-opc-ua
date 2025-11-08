<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\LocalizedText;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\StructureField;
use PHPUnit\Framework\TestCase;

final class StructureFieldTest extends TestCase
{
    public function testCreation(): void
    {
        $field = new StructureField(
            name: 'TestField',
            description: new LocalizedText('en', 'Test field description'),
            dataType: NodeId::numeric(0, 12), // String data type
            valueRank: -1, // Scalar
            arrayDimensions: null,
            maxStringLength: 256,
            isOptional: false,
        );

        $this->assertSame('TestField', $field->name);
        $this->assertSame('Test field description', $field->description?->text);
        $this->assertTrue($field->dataType->equals(NodeId::numeric(0, 12)));
        $this->assertSame(-1, $field->valueRank);
        $this->assertNull($field->arrayDimensions);
        $this->assertSame(256, $field->maxStringLength);
        $this->assertFalse($field->isOptional);
    }

    public function testIsScalar(): void
    {
        $field = new StructureField(
            name: 'ScalarField',
            description: null,
            dataType: NodeId::numeric(0, 6), // Int32
            valueRank: -1,
            arrayDimensions: null,
            maxStringLength: 0,
            isOptional: false,
        );

        $this->assertTrue($field->isScalar());
        $this->assertFalse($field->isArray());
    }

    public function testIsArray(): void
    {
        $field = new StructureField(
            name: 'ArrayField',
            description: null,
            dataType: NodeId::numeric(0, 12), // String
            valueRank: 1, // 1D array
            arrayDimensions: [10],
            maxStringLength: 0,
            isOptional: false,
        );

        $this->assertTrue($field->isArray());
        $this->assertFalse($field->isScalar());
        $this->assertSame([10], $field->arrayDimensions);
    }

    public function testOptionalField(): void
    {
        $field = new StructureField(
            name: 'OptionalField',
            description: null,
            dataType: NodeId::numeric(0, 1), // Boolean
            valueRank: -1,
            arrayDimensions: null,
            maxStringLength: 0,
            isOptional: true,
        );

        $this->assertTrue($field->isOptional);
    }

    public function testEncodeDecode(): void
    {
        $field = new StructureField(
            name: 'EncodedField',
            description: new LocalizedText('en-US', 'An encoded field'),
            dataType: NodeId::numeric(0, 11), // Double
            valueRank: -1,
            arrayDimensions: null,
            maxStringLength: 0,
            isOptional: false,
        );

        $encoder = new BinaryEncoder();
        $field->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = StructureField::decode($decoder);

        $this->assertSame($field->name, $decoded->name);
        $this->assertSame($field->description?->text, $decoded->description?->text);
        $this->assertSame($field->description?->locale, $decoded->description?->locale);
        $this->assertSame($field->dataType->toString(), $decoded->dataType->toString());
        $this->assertSame($field->valueRank, $decoded->valueRank);
        $this->assertSame($field->arrayDimensions, $decoded->arrayDimensions);
        $this->assertSame($field->maxStringLength, $decoded->maxStringLength);
        $this->assertSame($field->isOptional, $decoded->isOptional);
    }

    public function testEncodeDecodeWithArrayDimensions(): void
    {
        $field = new StructureField(
            name: 'MatrixField',
            description: null,
            dataType: NodeId::numeric(0, 11), // Double
            valueRank: 2, // 2D array
            arrayDimensions: [3, 4],
            maxStringLength: 0,
            isOptional: false,
        );

        $encoder = new BinaryEncoder();
        $field->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = StructureField::decode($decoder);

        $this->assertSame($field->arrayDimensions, $decoded->arrayDimensions);
        $this->assertSame(2, $decoded->valueRank);
    }

    public function testToString(): void
    {
        $field = new StructureField(
            name: 'TestField',
            description: null,
            dataType: NodeId::numeric(0, 12),
            valueRank: 1,
            arrayDimensions: null,
            maxStringLength: 0,
            isOptional: true,
        );

        $str = $field->toString();
        $this->assertStringContainsString('TestField', $str);
        $this->assertStringContainsString('array', $str);
        $this->assertStringContainsString('optional', $str);
    }

    public function testInvalidArrayDimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StructureField(
            name: 'BadField',
            description: null,
            dataType: NodeId::numeric(0, 12),
            valueRank: 1,
            arrayDimensions: [-1], // Negative dimension - invalid
            maxStringLength: 0,
            isOptional: false,
        );
    }

    public function testInvalidMaxStringLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StructureField(
            name: 'BadField',
            description: null,
            dataType: NodeId::numeric(0, 12),
            valueRank: -1,
            arrayDimensions: null,
            maxStringLength: -1, // Negative - invalid
            isOptional: false,
        );
    }
}
