<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\QualifiedName;
use PHPUnit\Framework\TestCase;

final class QualifiedNameTest extends TestCase
{
    public function testCreation(): void
    {
        $qn = new QualifiedName(namespaceIndex: 2, name: 'MyVariable');

        $this->assertSame(2, $qn->namespaceIndex);
        $this->assertSame('MyVariable', $qn->name);
    }

    public function testToString(): void
    {
        $qn1 = new QualifiedName(namespaceIndex: 0, name: 'Root');
        $this->assertSame('Root', $qn1->toString());

        $qn2 = new QualifiedName(namespaceIndex: 2, name: 'MyVariable');
        $this->assertSame('2:MyVariable', $qn2->toString());
    }

    public function testEncodeDecodeQualifiedName(): void
    {
        $qn = new QualifiedName(namespaceIndex: 3, name: 'Test.Variable.Name');

        $encoder = new BinaryEncoder();
        $qn->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = QualifiedName::decode($decoder);

        $this->assertTrue($qn->equals($decoded));
        $this->assertSame($qn->namespaceIndex, $decoded->namespaceIndex);
        $this->assertSame($qn->name, $decoded->name);
    }

    public function testEncodeDecodeEmptyName(): void
    {
        $qn = new QualifiedName(namespaceIndex: 0, name: '');

        $encoder = new BinaryEncoder();
        $qn->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = QualifiedName::decode($decoder);

        $this->assertTrue($qn->equals($decoded));
        $this->assertSame('', $decoded->name);
    }

    public function testEquals(): void
    {
        $qn1 = new QualifiedName(namespaceIndex: 2, name: 'Test');
        $qn2 = new QualifiedName(namespaceIndex: 2, name: 'Test');
        $qn3 = new QualifiedName(namespaceIndex: 3, name: 'Test');
        $qn4 = new QualifiedName(namespaceIndex: 2, name: 'Other');

        $this->assertTrue($qn1->equals($qn2));
        $this->assertFalse($qn1->equals($qn3));
        $this->assertFalse($qn1->equals($qn4));
    }

    public function testInvalidNamespaceIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QualifiedName(namespaceIndex: -1, name: 'Test');
    }

    public function testUtf8Names(): void
    {
        $qn = new QualifiedName(namespaceIndex: 1, name: '测试变量');

        $encoder = new BinaryEncoder();
        $qn->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = QualifiedName::decode($decoder);

        $this->assertSame('测试变量', $decoded->name);
    }
}
