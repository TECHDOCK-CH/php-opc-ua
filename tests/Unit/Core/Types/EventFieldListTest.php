<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\EventFieldList;
use TechDock\OpcUa\Core\Types\Variant;
use TechDock\OpcUa\Core\Types\VariantType;
use PHPUnit\Framework\TestCase;

final class EventFieldListTest extends TestCase
{
    public function testEventFieldListCreation(): void
    {
        $field1 = new Variant(value: 'event-id-123', type: VariantType::String);
        $field2 = new Variant(value: 500, type: VariantType::Int32);

        $eventFieldList = new EventFieldList(
            clientHandle: 1,
            eventFields: [$field1, $field2]
        );

        $this->assertSame(1, $eventFieldList->clientHandle);
        $this->assertCount(2, $eventFieldList->eventFields);
        $this->assertSame($field1, $eventFieldList->eventFields[0]);
        $this->assertSame($field2, $eventFieldList->eventFields[1]);
    }

    public function testEventFieldListWithEmptyFields(): void
    {
        $eventFieldList = new EventFieldList(
            clientHandle: 42,
            eventFields: []
        );

        $this->assertSame(42, $eventFieldList->clientHandle);
        $this->assertSame([], $eventFieldList->eventFields);
    }

    public function testCount(): void
    {
        $fields = [
            new Variant(value: 'EventId', type: VariantType::String),
            new Variant(value: 'Message', type: VariantType::String),
            new Variant(value: 500, type: VariantType::Int32),
        ];

        $eventFieldList = new EventFieldList(1, $fields);

        $this->assertSame(3, $eventFieldList->count());
    }

    public function testGetFieldValue(): void
    {
        $fields = [
            new Variant(value: 'event-123', type: VariantType::String),
            new Variant(value: 'Alarm triggered', type: VariantType::String),
            new Variant(value: 800, type: VariantType::Int32),
        ];

        $eventFieldList = new EventFieldList(1, $fields);

        $this->assertSame('event-123', $eventFieldList->getFieldValue(0));
        $this->assertSame('Alarm triggered', $eventFieldList->getFieldValue(1));
        $this->assertSame(800, $eventFieldList->getFieldValue(2));
    }

    public function testGetFieldValueWithInvalidIndex(): void
    {
        $eventFieldList = new EventFieldList(1, []);

        $this->assertNull($eventFieldList->getFieldValue(0));
        $this->assertNull($eventFieldList->getFieldValue(99));
    }

    public function testGetFieldValueWithNullVariant(): void
    {
        $fields = [
            new Variant(value: null, type: VariantType::Null),
        ];

        $eventFieldList = new EventFieldList(1, $fields);

        $this->assertNull($eventFieldList->getFieldValue(0));
    }

    public function testEncodeDecodeRoundtrip(): void
    {
        $fields = [
            new Variant(value: 'event-id', type: VariantType::String),
            new Variant(value: 'Source node', type: VariantType::String),
            new Variant(value: 600, type: VariantType::Int32),
        ];

        $eventFieldList = new EventFieldList(
            clientHandle: 123,
            eventFields: $fields
        );

        $encoder = new BinaryEncoder();
        $eventFieldList->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertNotEmpty($bytes);

        $decoder = new BinaryDecoder($bytes);
        $decoded = EventFieldList::decode($decoder);

        $this->assertSame(123, $decoded->clientHandle);
        $this->assertCount(3, $decoded->eventFields);
        $this->assertSame('event-id', $decoded->eventFields[0]->value);
        $this->assertSame('Source node', $decoded->eventFields[1]->value);
        $this->assertSame(600, $decoded->eventFields[2]->value);
    }

    public function testEncodeDecodeWithEmptyFields(): void
    {
        $eventFieldList = new EventFieldList(
            clientHandle: 99,
            eventFields: []
        );

        $encoder = new BinaryEncoder();
        $eventFieldList->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = EventFieldList::decode($decoder);

        $this->assertSame(99, $decoded->clientHandle);
        $this->assertSame([], $decoded->eventFields);
    }
}
