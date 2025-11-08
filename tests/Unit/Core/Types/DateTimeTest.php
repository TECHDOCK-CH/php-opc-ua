<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use DateTimeImmutable;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\DateTime;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{
    public function testCreation(): void
    {
        $dt = new DateTime(116444736000000000);

        $this->assertSame(116444736000000000, $dt->ticks);
    }

    public function testNull(): void
    {
        $dt = DateTime::null();

        $this->assertSame(0, $dt->ticks);
        $this->assertTrue($dt->isNull());
    }

    public function testFromUnixTimestamp(): void
    {
        $timestamp = 1234567890.0;
        $dt = DateTime::fromUnixTimestamp($timestamp);

        $this->assertEqualsWithDelta($timestamp, $dt->toUnixTimestamp(), 0.0001);
    }

    public function testFromDateTime(): void
    {
        $phpDate = new DateTimeImmutable('2024-01-15 10:30:45');
        $dt = DateTime::fromDateTime($phpDate);

        $decoded = $dt->toDateTime();
        $this->assertSame($phpDate->format('Y-m-d H:i:s'), $decoded->format('Y-m-d H:i:s'));
    }

    public function testEncodeDecode(): void
    {
        $dt = DateTime::fromUnixTimestamp(1609459200.0); // 2021-01-01 00:00:00

        $encoder = new BinaryEncoder();
        $dt->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertSame(8, strlen($bytes)); // 64-bit = 8 bytes

        $decoder = new BinaryDecoder($bytes);
        $decoded = DateTime::decode($decoder);

        $this->assertTrue($dt->equals($decoded));
        $this->assertSame($dt->ticks, $decoded->ticks);
    }

    public function testToString(): void
    {
        $dt = DateTime::fromUnixTimestamp(0.0);
        $str = $dt->toString();

        $this->assertStringContainsString('1970-01-01', $str);
    }

    public function testNullToString(): void
    {
        $dt = DateTime::null();
        $str = $dt->toString();

        $this->assertSame('0000-00-00T00:00:00.000Z', $str);
    }

    public function testEquals(): void
    {
        $dt1 = DateTime::fromUnixTimestamp(1000.0);
        $dt2 = DateTime::fromUnixTimestamp(1000.0);
        $dt3 = DateTime::fromUnixTimestamp(2000.0);

        $this->assertTrue($dt1->equals($dt2));
        $this->assertFalse($dt1->equals($dt3));
    }
}
