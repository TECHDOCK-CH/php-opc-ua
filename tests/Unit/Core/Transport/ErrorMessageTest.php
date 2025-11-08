<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Transport;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Transport\ErrorMessage;
use TechDock\OpcUa\Core\Types\StatusCode;
use PHPUnit\Framework\TestCase;

final class ErrorMessageTest extends TestCase
{
    public function testCreation(): void
    {
        $error = StatusCode::bad();
        $msg = new ErrorMessage($error, 'Connection refused');

        $this->assertSame($error, $msg->error);
        $this->assertSame('Connection refused', $msg->reason);
    }

    public function testCreateFactory(): void
    {
        $error = StatusCode::bad(StatusCode::BAD_TIMEOUT);
        $msg = ErrorMessage::create($error, 'Timeout occurred');

        $this->assertSame($error, $msg->error);
        $this->assertSame('Timeout occurred', $msg->reason);
    }

    public function testEncodeDecode(): void
    {
        $error = StatusCode::bad(StatusCode::BAD_SECURE_CHANNEL_CLOSED);
        $msg = ErrorMessage::create($error, 'Secure channel closed unexpectedly');

        $encoded = $msg->encode();

        $this->assertGreaterThan(8, strlen($encoded)); // At least header size

        $decoder = new BinaryDecoder($encoded);
        $decoded = ErrorMessage::decode($decoder);

        $this->assertTrue($msg->error->equals($decoded->error));
        $this->assertSame($msg->reason, $decoded->reason);
    }

    public function testEncodeDecodeEmptyReason(): void
    {
        $error = StatusCode::bad();
        $msg = new ErrorMessage($error, '');

        $encoded = $msg->encode();
        $decoder = new BinaryDecoder($encoded);
        $decoded = ErrorMessage::decode($decoder);

        $this->assertSame('', $decoded->reason);
    }

    public function testToString(): void
    {
        $error = StatusCode::bad();
        $msg = ErrorMessage::create($error, 'Test error');
        $str = $msg->toString();

        $this->assertStringContainsString('Error', $str);
        $this->assertStringContainsString('Test error', $str);
    }
}
