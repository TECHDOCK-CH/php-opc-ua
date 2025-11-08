<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use DateTimeImmutable;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\BuildInfo;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\DynamicStructure;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\LocalizedText;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\ServerState;
use TechDock\OpcUa\Core\Types\ServerStatusDataType;
use PHPUnit\Framework\TestCase;

final class DynamicStructureTest extends TestCase
{
    /**
     * Test decoding well-known ServerStatusDataType
     */
    public function testDecodeWellKnownServerStatusDataType(): void
    {
        // Create a ServerStatusDataType object
        $startTime = DateTime::fromDateTime(new DateTimeImmutable('2025-01-01T00:00:00Z'));
        $currentTime = DateTime::fromDateTime(new DateTimeImmutable('2025-01-01T12:00:00Z'));
        $buildInfo = new BuildInfo(
            productUri: 'http://example.com/product',
            manufacturerName: 'Test Manufacturer',
            productName: 'Test Product',
            softwareVersion: '1.0.0',
            buildNumber: '100',
            buildDate: DateTime::fromDateTime(new DateTimeImmutable('2024-12-01T00:00:00Z'))
        );
        $serverStatus = new ServerStatusDataType(
            startTime: $startTime,
            currentTime: $currentTime,
            state: ServerState::Running,
            buildInfo: $buildInfo,
            secondsTillShutdown: 0,
            shutdownReason: new LocalizedText(null, 'No shutdown planned')
        );

        // Encode ServerStatusDataType into binary
        $encoder = new BinaryEncoder();
        $serverStatus->encode($encoder);
        $body = $encoder->getBytes();

        // Create ExtensionObject with ServerStatusDataType encoding ID (864)
        $extensionObject = new ExtensionObject(
            typeId: NodeId::numeric(0, 864),
            encoding: 1, // Binary
            body: $body
        );

        // Decode using DynamicStructure (without TypeCache)
        $decoded = DynamicStructure::decode($extensionObject);

        // Verify it was decoded as ServerStatusDataType object
        $this->assertInstanceOf(ServerStatusDataType::class, $decoded);
        $this->assertSame(ServerState::Running, $decoded->state);
        $this->assertSame(0, $decoded->secondsTillShutdown);
    }

    /**
     * Test decoding well-known BuildInfo type
     */
    public function testDecodeWellKnownBuildInfo(): void
    {
        $buildInfo = new BuildInfo(
            productUri: 'http://example.com/product',
            manufacturerName: 'Manufacturer',
            productName: 'Product',
            softwareVersion: '2.0.0',
            buildNumber: '200',
            buildDate: DateTime::fromDateTime(new DateTimeImmutable('2024-11-01T00:00:00Z'))
        );

        // Encode BuildInfo into binary
        $encoder = new BinaryEncoder();
        $buildInfo->encode($encoder);
        $body = $encoder->getBytes();

        // Create ExtensionObject with BuildInfo encoding ID (340)
        $extensionObject = new ExtensionObject(
            typeId: NodeId::numeric(0, 340),
            encoding: 1, // Binary
            body: $body
        );

        // Decode using DynamicStructure
        $decoded = DynamicStructure::decode($extensionObject);

        // Verify it was decoded as BuildInfo object
        $this->assertInstanceOf(BuildInfo::class, $decoded);
        $this->assertSame('Product', $decoded->productName);
        $this->assertSame('2.0.0', $decoded->softwareVersion);
    }

    /**
     * Test decoding returns null for non-binary ExtensionObject
     */
    public function testDecodeNonBinaryExtensionObjectReturnsNull(): void
    {
        $extensionObject = new ExtensionObject(
            typeId: NodeId::numeric(0, 100),
            encoding: 0, // Not binary
            body: null
        );

        $decoded = DynamicStructure::decode($extensionObject);

        $this->assertNull($decoded);
    }

    /**
     * Test decoding returns null when body is null (empty encoding)
     */
    public function testDecodeEmptyEncodingReturnsNull(): void
    {
        $extensionObject = ExtensionObject::empty(NodeId::numeric(0, 100));

        $decoded = DynamicStructure::decode($extensionObject);

        $this->assertNull($decoded);
    }

    /**
     * Test decoding returns null for unknown type without TypeCache
     */
    public function testDecodeUnknownTypeWithoutTypeCacheReturnsNull(): void
    {
        // Create ExtensionObject with unknown type ID
        $encoder = new BinaryEncoder();
        $encoder->writeInt32(42); // Some data
        $body = $encoder->getBytes();

        $extensionObject = new ExtensionObject(
            typeId: NodeId::numeric(0, 999), // Unknown type
            encoding: 1, // Binary
            body: $body
        );

        // Decode without TypeCache
        $decoded = DynamicStructure::decode($extensionObject, null);

        $this->assertNull($decoded);
    }

    /**
     * Test decoding well-known type ignores TypeCache
     */
    public function testDecodeWellKnownTypeIgnoresTypeCache(): void
    {
        $buildInfo = new BuildInfo(
            productUri: 'http://example.com',
            manufacturerName: 'Test',
            productName: 'Test',
            softwareVersion: '1.0',
            buildNumber: '1',
            buildDate: DateTime::fromDateTime(new DateTimeImmutable('2024-01-01T00:00:00Z'))
        );

        $encoder = new BinaryEncoder();
        $buildInfo->encode($encoder);
        $body = $encoder->getBytes();

        $extensionObject = new ExtensionObject(
            typeId: NodeId::numeric(0, 340),
            encoding: 1,
            body: $body
        );

        // Decode with TypeCache = null (should still work for well-known types)
        $decoded = DynamicStructure::decode($extensionObject, null);

        $this->assertInstanceOf(BuildInfo::class, $decoded);
    }

    /**
     * Test decoding non-namespace-0 TypeId returns null without TypeCache
     */
    public function testDecodeNonNamespace0TypeIdWithoutTypeCacheReturnsNull(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeInt32(123);
        $body = $encoder->getBytes();

        $extensionObject = new ExtensionObject(
            typeId: NodeId::numeric(1, 100), // Namespace 1, not 0
            encoding: 1,
            body: $body
        );

        $decoded = DynamicStructure::decode($extensionObject, null);

        $this->assertNull($decoded);
    }

    /**
     * Test decoding string-type NodeId returns null without TypeCache
     */
    public function testDecodeStringTypeNodeIdWithoutTypeCacheReturnsNull(): void
    {
        $encoder = new BinaryEncoder();
        $encoder->writeInt32(456);
        $body = $encoder->getBytes();

        $extensionObject = new ExtensionObject(
            typeId: NodeId::string(0, 'CustomType'),
            encoding: 1,
            body: $body
        );

        $decoded = DynamicStructure::decode($extensionObject, null);

        $this->assertNull($decoded);
    }
}
