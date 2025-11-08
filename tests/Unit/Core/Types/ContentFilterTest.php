<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\ContentFilter;
use TechDock\OpcUa\Core\Types\ContentFilterElement;
use TechDock\OpcUa\Core\Types\FilterOperator;
use TechDock\OpcUa\Core\Types\LiteralOperand;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\SimpleAttributeOperand;
use PHPUnit\Framework\TestCase;

final class ContentFilterTest extends TestCase
{
    public function testContentFilterCreation(): void
    {
        $filter = new ContentFilter([]);

        $this->assertSame([], $filter->elements);
    }

    public function testContentFilterWithElements(): void
    {
        $element1 = new ContentFilterElement(FilterOperator::Equals, []);
        $element2 = new ContentFilterElement(FilterOperator::GreaterThan, []);

        $filter = new ContentFilter([$element1, $element2]);

        $this->assertCount(2, $filter->elements);
        $this->assertSame($element1, $filter->elements[0]);
        $this->assertSame($element2, $filter->elements[1]);
    }

    public function testPushElement(): void
    {
        $filter = new ContentFilter([]);
        $element = new ContentFilterElement(FilterOperator::Equals, []);

        $filter->push($element);

        $this->assertCount(1, $filter->elements);
        $this->assertSame($element, $filter->elements[0]);
    }

    public function testPushOperatorWithOperands(): void
    {
        $filter = new ContentFilter([]);
        $typeId = NodeId::numeric(0, 2041);

        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['Severity']);
        $operand2 = LiteralOperand::fromValue(500);

        $element = new ContentFilterElement(FilterOperator::GreaterThan, [$operand1, $operand2]);
        $filter->push($element);

        $this->assertCount(1, $filter->elements);
        $this->assertSame(FilterOperator::GreaterThan, $filter->elements[0]->filterOperator);
        $this->assertCount(2, $filter->elements[0]->filterOperands);
    }

    public function testComplexFilterWithMultipleConditions(): void
    {
        $filter = new ContentFilter([]);
        $typeId = NodeId::numeric(0, 2041);

        // Condition 1: Severity > 500
        $severityOperand = SimpleAttributeOperand::fromStrings($typeId, ['Severity']);
        $severityThreshold = LiteralOperand::fromValue(500);
        $element1 = new ContentFilterElement(FilterOperator::GreaterThan, [$severityOperand, $severityThreshold]);
        $filter->push($element1);

        // Condition 2: EventType == AlarmConditionType
        $eventTypeOperand = SimpleAttributeOperand::fromStrings($typeId, ['EventType']);
        $alarmTypeId = LiteralOperand::fromValue(NodeId::numeric(0, 2915));
        $element2 = new ContentFilterElement(FilterOperator::Equals, [$eventTypeOperand, $alarmTypeId]);
        $filter->push($element2);

        $this->assertCount(2, $filter->elements);
    }

    public function testEncodeDecodeEmptyFilter(): void
    {
        $filter = new ContentFilter([]);

        $encoder = new BinaryEncoder();
        $filter->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = ContentFilter::decode($decoder);

        $this->assertSame([], $decoded->elements);
    }

    public function testEncodeDecodeFilterWithElements(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $filter = new ContentFilter([]);

        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['Severity']);
        $operand2 = LiteralOperand::fromValue(500);
        $element = new ContentFilterElement(FilterOperator::GreaterThan, [$operand1, $operand2]);
        $filter->push($element);

        $encoder = new BinaryEncoder();
        $filter->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertNotEmpty($bytes);

        $decoder = new BinaryDecoder($bytes);
        $decoded = ContentFilter::decode($decoder);

        $this->assertCount(1, $decoded->elements);
        $this->assertSame(FilterOperator::GreaterThan, $decoded->elements[0]->filterOperator);
        $this->assertCount(2, $decoded->elements[0]->filterOperands);
    }
}
