<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\NodeIdType;
use PHPUnit\Framework\TestCase;

final class NodeIdTest extends TestCase
{
    public function testNumericNodeIdCreation(): void
    {
        $nodeId = NodeId::numeric(ns: 0, identifier: 2258);

        $this->assertSame(0, $nodeId->namespaceIndex);
        $this->assertSame(2258, $nodeId->identifier);
        $this->assertSame(NodeIdType::Numeric, $nodeId->type);
        $this->assertSame('ns=0;i=2258', $nodeId->toString());
    }

    public function testStringNodeIdCreation(): void
    {
        $nodeId = NodeId::string(ns: 2, identifier: 'MyVariable');

        $this->assertSame(2, $nodeId->namespaceIndex);
        $this->assertSame('MyVariable', $nodeId->identifier);
        $this->assertSame(NodeIdType::String, $nodeId->type);
        $this->assertSame('ns=2;s=MyVariable', $nodeId->toString());
    }

    public function testGuidNodeIdCreation(): void
    {
        $guid = '12345678-1234-5678-1234-567812345678';
        $nodeId = NodeId::guid(ns: 3, identifier: $guid);

        $this->assertSame(3, $nodeId->namespaceIndex);
        $this->assertSame($guid, $nodeId->identifier);
        $this->assertSame(NodeIdType::Guid, $nodeId->type);
        $this->assertSame("ns=3;g={$guid}", $nodeId->toString());
    }

    public function testOpaqueNodeIdCreation(): void
    {
        $opaque = 'binary-data';
        $nodeId = NodeId::opaque(ns: 4, identifier: $opaque);

        $this->assertSame(4, $nodeId->namespaceIndex);
        $this->assertSame($opaque, $nodeId->identifier);
        $this->assertSame(NodeIdType::Opaque, $nodeId->type);
        $this->assertSame('ns=4;b=' . base64_encode($opaque), $nodeId->toString());
    }

    public function testTwoByteEncoding(): void
    {
        // ns=0, id <= 255 uses 2-byte format
        $nodeId = NodeId::numeric(ns: 0, identifier: 42);

        $encoder = new BinaryEncoder();
        $nodeId->encode($encoder);
        $bytes = $encoder->getBytes();

        // Should be: [0x00, 0x2A] (2 bytes)
        $this->assertSame("\x00\x2A", $bytes);

        $decoder = new BinaryDecoder($bytes);
        $decoded = NodeId::decode($decoder);

        $this->assertTrue($nodeId->equals($decoded));
    }

    public function testFourByteEncoding(): void
    {
        // ns <= 255, id <= 65535 uses 4-byte format
        $nodeId = NodeId::numeric(ns: 10, identifier: 1000);

        $encoder = new BinaryEncoder();
        $nodeId->encode($encoder);
        $bytes = $encoder->getBytes();

        // Should be: [0x01, ns, id_lo, id_hi] (4 bytes)
        $this->assertSame(4, strlen($bytes));
        $this->assertSame(0x01, ord($bytes[0]));

        $decoder = new BinaryDecoder($bytes);
        $decoded = NodeId::decode($decoder);

        $this->assertTrue($nodeId->equals($decoded));
    }

    public function testFullNumericEncoding(): void
    {
        // Large values use full numeric format
        $nodeId = NodeId::numeric(ns: 300, identifier: 100000);

        $encoder = new BinaryEncoder();
        $nodeId->encode($encoder);
        $bytes = $encoder->getBytes();

        // Should be: [0x02, ns_lo, ns_hi, id_bytes...] (7 bytes)
        $this->assertSame(7, strlen($bytes));
        $this->assertSame(0x02, ord($bytes[0]));

        $decoder = new BinaryDecoder($bytes);
        $decoded = NodeId::decode($decoder);

        $this->assertTrue($nodeId->equals($decoded));
    }

    public function testStringNodeIdEncoding(): void
    {
        $nodeId = NodeId::string(ns: 2, identifier: 'Test.Variable');

        $encoder = new BinaryEncoder();
        $nodeId->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = NodeId::decode($decoder);

        $this->assertTrue($nodeId->equals($decoded));
        $this->assertSame('Test.Variable', $decoded->identifier);
    }

    public function testGuidNodeIdEncoding(): void
    {
        $guid = '12345678-90ab-cdef-1234-567890abcdef';
        $nodeId = NodeId::guid(ns: 3, identifier: $guid);

        $encoder = new BinaryEncoder();
        $nodeId->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = NodeId::decode($decoder);

        $this->assertTrue($nodeId->equals($decoded));
        $this->assertSame($guid, $decoded->identifier);
    }

    public function testOpaqueNodeIdEncoding(): void
    {
        $opaque = "\x00\x01\x02\x03\xFF";
        $nodeId = NodeId::opaque(ns: 4, identifier: $opaque);

        $encoder = new BinaryEncoder();
        $nodeId->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = NodeId::decode($decoder);

        $this->assertTrue($nodeId->equals($decoded));
        $this->assertSame($opaque, $decoded->identifier);
    }

    public function testIsNull(): void
    {
        $nullNodeId = NodeId::numeric(ns: 0, identifier: 0);
        $this->assertTrue($nullNodeId->isNull());

        $nonNullNodeId = NodeId::numeric(ns: 0, identifier: 1);
        $this->assertFalse($nonNullNodeId->isNull());

        $nonNullNodeId2 = NodeId::numeric(ns: 1, identifier: 0);
        $this->assertFalse($nonNullNodeId2->isNull());
    }

    public function testEquals(): void
    {
        $nodeId1 = NodeId::numeric(ns: 0, identifier: 2258);
        $nodeId2 = NodeId::numeric(ns: 0, identifier: 2258);
        $nodeId3 = NodeId::numeric(ns: 0, identifier: 2259);

        $this->assertTrue($nodeId1->equals($nodeId2));
        $this->assertFalse($nodeId1->equals($nodeId3));
    }

    public function testInvalidNamespaceIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NodeId(-1, 0, NodeIdType::Numeric);
    }

    public function testInvalidIdentifierType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NodeId(0, 'string', NodeIdType::Numeric);
    }
}
