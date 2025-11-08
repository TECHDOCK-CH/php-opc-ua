<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\StatusCode;
use PHPUnit\Framework\TestCase;

final class StatusCodeTest extends TestCase
{
    public function testGoodStatusCode(): void
    {
        $code = StatusCode::good();

        $this->assertSame(StatusCode::GOOD, $code->value);
        $this->assertTrue($code->isGood());
        $this->assertFalse($code->isBad());
        $this->assertFalse($code->isUncertain());
        $this->assertSame(0, $code->getSeverity());
    }

    public function testBadStatusCode(): void
    {
        $code = StatusCode::bad(StatusCode::BAD_NODE_ID_UNKNOWN);

        $this->assertSame(StatusCode::BAD_NODE_ID_UNKNOWN, $code->value);
        $this->assertFalse($code->isGood());
        $this->assertTrue($code->isBad());
        $this->assertFalse($code->isUncertain());
        $this->assertGreaterThan(1, $code->getSeverity());
    }

    public function testUncertainStatusCode(): void
    {
        $code = StatusCode::uncertain();

        $this->assertSame(StatusCode::UNCERTAIN, $code->value);
        $this->assertFalse($code->isGood());
        $this->assertFalse($code->isBad());
        $this->assertTrue($code->isUncertain());
        $this->assertSame(1, $code->getSeverity());
    }

    public function testEncodeDecodeStatusCode(): void
    {
        $code = new StatusCode(StatusCode::BAD_TIMEOUT);

        $encoder = new BinaryEncoder();
        $code->encode($encoder);
        $bytes = $encoder->getBytes();

        $this->assertSame(4, strlen($bytes)); // 32-bit = 4 bytes

        $decoder = new BinaryDecoder($bytes);
        $decoded = StatusCode::decode($decoder);

        $this->assertTrue($code->equals($decoded));
        $this->assertSame($code->value, $decoded->value);
    }

    public function testStatusCodeToString(): void
    {
        $code = new StatusCode(0x80340000); // BAD_NODE_ID_UNKNOWN

        $this->assertSame('0x80340000', $code->toString());
    }

    public function testSpecificStatusCodes(): void
    {
        $codes = [
            StatusCode::GOOD,
            StatusCode::BAD_UNEXPECTED_ERROR,
            StatusCode::BAD_TIMEOUT,
            StatusCode::BAD_NODE_ID_INVALID,
            StatusCode::BAD_NODE_ID_UNKNOWN,
            StatusCode::BAD_SECURE_CHANNEL_CLOSED,
            StatusCode::BAD_SESSION_CLOSED,
        ];

        foreach ($codes as $codeValue) {
            $code = new StatusCode($codeValue);

            $encoder = new BinaryEncoder();
            $code->encode($encoder);
            $decoder = new BinaryDecoder($encoder->getBytes());
            $decoded = StatusCode::decode($decoder);

            $this->assertTrue($code->equals($decoded));
        }
    }

    public function testEquals(): void
    {
        $code1 = StatusCode::good();
        $code2 = StatusCode::good();
        $code3 = StatusCode::bad();

        $this->assertTrue($code1->equals($code2));
        $this->assertFalse($code1->equals($code3));
    }
}
