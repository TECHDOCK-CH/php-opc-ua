<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use DateTime;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\ContentFilterElement;
use TechDock\OpcUa\Core\Types\FilterOperator;
use TechDock\OpcUa\Core\Types\LiteralOperand;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\SimpleAttributeOperand;
use PHPUnit\Framework\TestCase;

final class ContentFilterElementTest extends TestCase
{
    public function testContentFilterElementCreation(): void
    {
        $element = new ContentFilterElement(
            filterOperator: FilterOperator::Equals,
            filterOperands: []
        );

        $this->assertSame(FilterOperator::Equals, $element->filterOperator);
        $this->assertSame([], $element->filterOperands);
    }

    public function testContentFilterElementWithOperands(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['Severity']);
        $operand2 = LiteralOperand::fromValue(500);

        $element = new ContentFilterElement(
            filterOperator: FilterOperator::GreaterThan,
            filterOperands: [$operand1, $operand2]
        );

        $this->assertSame(FilterOperator::GreaterThan, $element->filterOperator);
        $this->assertCount(2, $element->filterOperands);
        $this->assertSame($operand1, $element->filterOperands[0]);
        $this->assertSame($operand2, $element->filterOperands[1]);
    }

    public function testEqualsFactory(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['EventType']);
        $operand2 = LiteralOperand::fromValue(NodeId::numeric(0, 2041));

        $element = ContentFilterElement::equals($operand1, $operand2);

        $this->assertSame(FilterOperator::Equals, $element->filterOperator);
        $this->assertCount(2, $element->filterOperands);
    }

    public function testGreaterThanFactory(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['Severity']);
        $operand2 = LiteralOperand::fromValue(500);

        $element = ContentFilterElement::greaterThan($operand1, $operand2);

        $this->assertSame(FilterOperator::GreaterThan, $element->filterOperator);
        $this->assertCount(2, $element->filterOperands);
    }

    public function testGreaterThanOrEqualFactory(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['Severity']);
        $operand2 = LiteralOperand::fromValue(500);

        $element = ContentFilterElement::greaterThanOrEqual($operand1, $operand2);

        $this->assertSame(FilterOperator::GreaterThanOrEqual, $element->filterOperator);
        $this->assertCount(2, $element->filterOperands);
    }

    public function testLessThanFactory(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['Time']);
        $operand2 = LiteralOperand::fromValue(new DateTime('2025-01-01'));

        $element = ContentFilterElement::lessThan($operand1, $operand2);

        $this->assertSame(FilterOperator::LessThan, $element->filterOperator);
        $this->assertCount(2, $element->filterOperands);
    }

    public function testLessThanOrEqualFactory(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['Severity']);
        $operand2 = LiteralOperand::fromValue(200);

        $element = ContentFilterElement::lessThanOrEqual($operand1, $operand2);

        $this->assertSame(FilterOperator::LessThanOrEqual, $element->filterOperator);
        $this->assertCount(2, $element->filterOperands);
    }

    public function testAllFilterOperators(): void
    {
        $operators = [
            FilterOperator::Equals,
            FilterOperator::IsNull,
            FilterOperator::GreaterThan,
            FilterOperator::LessThan,
            FilterOperator::GreaterThanOrEqual,
            FilterOperator::LessThanOrEqual,
            FilterOperator::Like,
            FilterOperator::Not,
            FilterOperator::Between,
            FilterOperator::InList,
            FilterOperator::And,
            FilterOperator::Or,
            FilterOperator::Cast,
            FilterOperator::BitwiseAnd,
            FilterOperator::BitwiseOr,
        ];

        foreach ($operators as $operator) {
            $element = new ContentFilterElement($operator, []);
            $this->assertSame($operator, $element->filterOperator);
        }
    }

    public function testEncodeDecodeRoundtrip(): void
    {
        $typeId = NodeId::numeric(0, 2041);
        $operand1 = SimpleAttributeOperand::fromStrings($typeId, ['Severity']);
        $operand2 = LiteralOperand::fromValue(500);

        $element = new ContentFilterElement(
            filterOperator: FilterOperator::GreaterThanOrEqual,
            filterOperands: [$operand1, $operand2]
        );

        $encoder = new BinaryEncoder();
        $element->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertNotEmpty($bytes);

        $decoder = new BinaryDecoder($bytes);
        $decoded = ContentFilterElement::decode($decoder);

        $this->assertSame(FilterOperator::GreaterThanOrEqual, $decoded->filterOperator);
        $this->assertCount(2, $decoded->filterOperands);
    }
}
