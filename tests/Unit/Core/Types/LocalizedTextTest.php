<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\LocalizedText;
use PHPUnit\Framework\TestCase;

final class LocalizedTextTest extends TestCase
{
    public function testCreation(): void
    {
        $text = new LocalizedText('en-US', 'Hello World');

        $this->assertSame('en-US', $text->locale);
        $this->assertSame('Hello World', $text->text);
    }

    public function testEncodeDecodeBoth(): void
    {
        $text = new LocalizedText('en-US', 'Test Message');

        $encoder = new BinaryEncoder();
        $text->encode($encoder);
        $bytes = $encoder->getBytes();

        $decoder = new BinaryDecoder($bytes);
        $decoded = LocalizedText::decode($decoder);

        $this->assertTrue($text->equals($decoded));
        $this->assertSame('en-US', $decoded->locale);
        $this->assertSame('Test Message', $decoded->text);
    }

    public function testEncodeDecodeTextOnly(): void
    {
        $text = new LocalizedText(null, 'No Locale');

        $encoder = new BinaryEncoder();
        $text->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = LocalizedText::decode($decoder);

        $this->assertTrue($text->equals($decoded));
        $this->assertNull($decoded->locale);
        $this->assertSame('No Locale', $decoded->text);
    }

    public function testEncodeDecodeLocaleOnly(): void
    {
        $text = new LocalizedText('de-DE', null);

        $encoder = new BinaryEncoder();
        $text->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = LocalizedText::decode($decoder);

        $this->assertTrue($text->equals($decoded));
        $this->assertSame('de-DE', $decoded->locale);
        $this->assertNull($decoded->text);
    }

    public function testEncodeDecodeEmpty(): void
    {
        $text = new LocalizedText(null, null);

        $encoder = new BinaryEncoder();
        $text->encode($encoder);
        $decoder = new BinaryDecoder($encoder->getBytes());
        $decoded = LocalizedText::decode($decoder);

        $this->assertTrue($text->equals($decoded));
        $this->assertNull($decoded->locale);
        $this->assertNull($decoded->text);
    }

    public function testToString(): void
    {
        $text1 = new LocalizedText('en-US', 'Hello');
        $this->assertSame('en-US: Hello', $text1->toString());

        $text2 = new LocalizedText(null, 'No Locale');
        $this->assertSame('No Locale', $text2->toString());

        $text3 = new LocalizedText('de-DE', null);
        $this->assertSame('[de-DE]', $text3->toString());

        $text4 = new LocalizedText(null, null);
        $this->assertSame('', $text4->toString());
    }

    public function testEquals(): void
    {
        $text1 = new LocalizedText('en-US', 'Test');
        $text2 = new LocalizedText('en-US', 'Test');
        $text3 = new LocalizedText('de-DE', 'Test');
        $text4 = new LocalizedText('en-US', 'Other');

        $this->assertTrue($text1->equals($text2));
        $this->assertFalse($text1->equals($text3));
        $this->assertFalse($text1->equals($text4));
    }
}
