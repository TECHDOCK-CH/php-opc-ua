<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\DataValue;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\StatusCode;
use TechDock\OpcUa\Core\Types\Variant;
use PHPUnit\Framework\TestCase;

final class DataValueTest extends TestCase
{
    public function testCreationEmpty(): void
    {
        $dv = new DataValue();

        $this->assertNull($dv->value);
        $this->assertNull($dv->statusCode);
        $this->assertNull($dv->sourceTimestamp);
        $this->assertNull($dv->serverTimestamp);
    }

    public function testCreationWithValue(): void
    {
        $variant = Variant::int32(42);
        $dv = DataValue::fromVariant($variant);

        $this->assertNotNull($dv->value);
        $this->assertSame($variant, $dv->value);
    }

    public function testCreationWithStatus(): void
    {
        $variant = Variant::string('test');
        $status = StatusCode::good();
        $dv = DataValue::withStatus($variant, $status);

        $this->assertSame($variant, $dv->value);
        $this->assertSame($status, $dv->statusCode);
        $this->assertTrue($dv->isGood());
    }

    public function testInt32Factory(): void
    {
        $dv = DataValue::int32(123);

        $this->assertNotNull($dv->value);
        $this->assertSame(123, $dv->value->value);
        $this->assertTrue($dv->isGood());
    }

    public function testStringFactory(): void
    {
        $dv = DataValue::string('hello');

        $this->assertNotNull($dv->value);
        $this->assertSame('hello', $dv->value->value);
        $this->assertTrue($dv->isGood());
    }

    public function testEncodeDecodeValueOnly(): void
    {
        $dv = new DataValue(value: Variant::int32(999));

        $encoder = new BinaryEncoder();
        $dv->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = DataValue::decode($decoder);

        $this->assertNotNull($decoded->value);
        $this->assertSame(999, $decoded->value->value);
        $this->assertNull($decoded->statusCode);
    }

    public function testEncodeDecodeComplete(): void
    {
        $dv = new DataValue(
            value: Variant::string('test'),
            statusCode: StatusCode::good(),
            sourceTimestamp: DateTime::fromUnixTimestamp(1000.0),
            serverTimestamp: DateTime::fromUnixTimestamp(2000.0),
            sourcePicoseconds: 1234,
            serverPicoseconds: 5678,
        );

        $encoder = new BinaryEncoder();
        $dv->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = DataValue::decode($decoder);

        $this->assertNotNull($decoded->value);
        $this->assertSame('test', $decoded->value->value);
        $this->assertNotNull($decoded->statusCode);
        $this->assertTrue($decoded->statusCode->isGood());
        $this->assertNotNull($decoded->sourceTimestamp);
        $this->assertNotNull($decoded->serverTimestamp);
        $this->assertSame(1234, $decoded->sourcePicoseconds);
        $this->assertSame(5678, $decoded->serverPicoseconds);
    }

    public function testIsGood(): void
    {
        $dv1 = new DataValue(value: Variant::int32(1));
        $this->assertTrue($dv1->isGood()); // No status = good

        $dv2 = new DataValue(value: Variant::int32(2), statusCode: StatusCode::good());
        $this->assertTrue($dv2->isGood());

        $dv3 = new DataValue(value: Variant::int32(3), statusCode: StatusCode::bad());
        $this->assertFalse($dv3->isGood());
    }

    public function testToString(): void
    {
        $dv = new DataValue(
            value: Variant::int32(42),
            statusCode: StatusCode::good(),
        );

        $str = $dv->toString();
        $this->assertStringContainsString('Value', $str);
        $this->assertStringContainsString('Status', $str);
    }

    public function testInvalidSourcePicoseconds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DataValue(sourcePicoseconds: 10000);
    }

    public function testInvalidServerPicoseconds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DataValue(serverPicoseconds: -1);
    }
}
