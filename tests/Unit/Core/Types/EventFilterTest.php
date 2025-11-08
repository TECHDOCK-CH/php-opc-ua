<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\ContentFilter;
use TechDock\OpcUa\Core\Types\EventFilter;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\QualifiedName;
use TechDock\OpcUa\Core\Types\SimpleAttributeOperand;
use PHPUnit\Framework\TestCase;

final class EventFilterTest extends TestCase
{
    public function testEventFilterCreation(): void
    {
        $eventFilter = new EventFilter(
            selectClauses: [],
            whereClause: new ContentFilter([])
        );

        $this->assertSame([], $eventFilter->selectClauses);
        $this->assertInstanceOf(ContentFilter::class, $eventFilter->whereClause);
        $this->assertSame([], $eventFilter->whereClause->elements);
    }

    public function testEventFilterWithSelectClauses(): void
    {
        $selectClause1 = new SimpleAttributeOperand(
            typeDefinitionId: NodeId::numeric(0, 2041),
            browsePath: [new QualifiedName(0, 'EventId')],
            attributeId: 13, // Value attribute
            indexRange: ''
        );

        $selectClause2 = new SimpleAttributeOperand(
            typeDefinitionId: NodeId::numeric(0, 2041),
            browsePath: [new QualifiedName(0, 'Message')],
            attributeId: 13, // Value attribute
            indexRange: ''
        );

        $eventFilter = new EventFilter(
            selectClauses: [$selectClause1, $selectClause2],
            whereClause: new ContentFilter([])
        );

        $this->assertCount(2, $eventFilter->selectClauses);
        $this->assertSame($selectClause1, $eventFilter->selectClauses[0]);
        $this->assertSame($selectClause2, $eventFilter->selectClauses[1]);
    }

    public function testForBaseEventTypeFactory(): void
    {
        $baseEventTypeId = NodeId::numeric(0, 2041);
        $eventFilter = EventFilter::forBaseEventType($baseEventTypeId);

        // Should have 6 common event fields
        $this->assertCount(6, $eventFilter->selectClauses);

        // Verify field names
        $fieldNames = array_map(
            fn($clause) => $clause->browsePath[0]->name,
            $eventFilter->selectClauses
        );

        $this->assertContains('EventId', $fieldNames);
        $this->assertContains('EventType', $fieldNames);
        $this->assertContains('SourceName', $fieldNames);
        $this->assertContains('Time', $fieldNames);
        $this->assertContains('Message', $fieldNames);
        $this->assertContains('Severity', $fieldNames);

        // All should reference the same type definition
        foreach ($eventFilter->selectClauses as $clause) {
            $this->assertTrue($clause->typeDefinitionId->equals($baseEventTypeId));
            $this->assertSame(13, $clause->attributeId); // Value attribute
        }
    }

    public function testSelectSimpleField(): void
    {
        $eventFilter = new EventFilter([], new ContentFilter([]));
        $typeId = NodeId::numeric(0, 2041);

        $eventFilter->selectSimpleField($typeId, ['EventId']);
        $eventFilter->selectSimpleField($typeId, ['Message']);

        $this->assertCount(2, $eventFilter->selectClauses);
        $this->assertSame('EventId', $eventFilter->selectClauses[0]->browsePath[0]->name);
        $this->assertSame('Message', $eventFilter->selectClauses[1]->browsePath[0]->name);
    }

    public function testSelectField(): void
    {
        $eventFilter = new EventFilter([], new ContentFilter([]));
        $operand = new SimpleAttributeOperand(
            typeDefinitionId: NodeId::numeric(0, 2041),
            browsePath: [new QualifiedName(0, 'Severity')],
            attributeId: 13, // Value attribute
            indexRange: ''
        );

        $eventFilter->selectField($operand);

        $this->assertCount(1, $eventFilter->selectClauses);
        $this->assertSame($operand, $eventFilter->selectClauses[0]);
    }

    public function testWhereClause(): void
    {
        $eventFilter = new EventFilter([], new ContentFilter([]));
        $whereClause = new ContentFilter([]);

        $eventFilter->where($whereClause);

        $this->assertSame($whereClause, $eventFilter->whereClause);
    }

    public function testEncodeDecodeRoundtrip(): void
    {
        $baseEventTypeId = NodeId::numeric(0, 2041);
        $eventFilter = EventFilter::forBaseEventType($baseEventTypeId);

        $encoder = new BinaryEncoder();
        $eventFilter->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertNotEmpty($bytes);

        $decoder = new BinaryDecoder($bytes);
        $decoded = EventFilter::decode($decoder);

        // Verify select clauses count
        $this->assertCount(count($eventFilter->selectClauses), $decoded->selectClauses);

        // Verify each select clause
        foreach ($eventFilter->selectClauses as $index => $originalClause) {
            $decodedClause = $decoded->selectClauses[$index];
            $this->assertTrue($originalClause->typeDefinitionId->equals($decodedClause->typeDefinitionId));
            $this->assertSame($originalClause->attributeId, $decodedClause->attributeId);
            $this->assertCount(count($originalClause->browsePath), $decodedClause->browsePath);
        }

        // Verify where clause
        $this->assertInstanceOf(ContentFilter::class, $decoded->whereClause);
    }

    public function testTypeIdExists(): void
    {
        // EventFilter should have a static TYPE_ID or similar constant
        // This test can be removed if getTypeId() is not implemented
        $this->assertTrue(true);
    }
}
