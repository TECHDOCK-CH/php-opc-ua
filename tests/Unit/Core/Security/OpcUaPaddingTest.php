<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Security;

use TechDock\OpcUa\Core\Security\OpcUaPadding;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OpcUaPaddingTest extends TestCase
{
    public function testAddAndRemovePaddingRoundtrip(): void
    {
        $data = 'Hello, OPC UA!';
        $blockSize = 16;

        $padded = OpcUaPadding::addSymmetric($data, $blockSize);
        $unpadded = OpcUaPadding::removeSymmetric($padded);

        self::assertSame($data, $unpadded);
    }

    public function testPaddedDataIsMultipleOfBlockSize(): void
    {
        $data = 'Test data';
        $blockSize = 16;

        $padded = OpcUaPadding::addSymmetric($data, $blockSize);

        self::assertSame(0, strlen($padded) % $blockSize);
    }

    public function testPaddingWithVariousDataLengths(): void
    {
        $blockSize = 16;

        // Test various data lengths from 0 to 50 bytes
        for ($length = 0; $length <= 50; $length++) {
            $data = str_repeat('x', $length);

            $padded = OpcUaPadding::addSymmetric($data, $blockSize);
            $unpadded = OpcUaPadding::removeSymmetric($padded);

            self::assertSame($data, $unpadded, "Failed for data length: {$length}");
            self::assertSame(0, strlen($padded) % $blockSize, "Padded data not aligned for length: {$length}");
        }
    }

    public function testPaddingFormat(): void
    {
        $data = 'ABC'; // 3 bytes
        $blockSize = 16;

        $padded = OpcUaPadding::addSymmetric($data, $blockSize);

        // Padded length should be 16 (3 data + padding + size byte = 16)
        self::assertSame(16, strlen($padded));

        // Last byte is the padding size
        $paddingSize = ord($padded[15]);

        // For 3 bytes of data + 1 size byte = 4 bytes total
        // Need 12 more bytes to reach 16 = 12 padding bytes
        self::assertSame(12, $paddingSize);

        // Verify padding bytes all have the same value (12)
        for ($i = 3; $i < 15; $i++) {
            self::assertSame(12, ord($padded[$i]), "Padding byte at position {$i} should be 12");
        }
    }

    public function testEmptyDataPadding(): void
    {
        $data = '';
        $blockSize = 16;

        $padded = OpcUaPadding::addSymmetric($data, $blockSize);

        // Empty data + size byte = 1 byte
        // Need 15 more bytes to reach 16
        self::assertSame(16, strlen($padded));

        $unpadded = OpcUaPadding::removeSymmetric($padded);
        self::assertSame('', $unpadded);
    }

    public function testDataAlreadyAlignedWithSizeByte(): void
    {
        // 15 bytes of data + 1 size byte = 16 (already aligned)
        $data = str_repeat('x', 15);
        $blockSize = 16;

        $padded = OpcUaPadding::addSymmetric($data, $blockSize);

        // Should add 0 padding bytes, but still need the size byte
        self::assertSame(16, strlen($padded));

        $paddingSize = ord($padded[15]);
        self::assertSame(0, $paddingSize);

        $unpadded = OpcUaPadding::removeSymmetric($padded);
        self::assertSame($data, $unpadded);
    }

    public function testRemovePaddingRejectsInvalidPaddingBytes(): void
    {
        $data = 'Test';
        $blockSize = 16;

        $padded = OpcUaPadding::addSymmetric($data, $blockSize);

        // Corrupt a padding byte
        $corrupted = $padded;
        $corrupted[10] = chr(99); // Change a padding byte

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid padding bytes');

        OpcUaPadding::removeSymmetric($corrupted);
    }

    public function testRemovePaddingRejectsInvalidPaddingSize(): void
    {
        // Create data with invalid padding size
        $data = str_repeat('x', 15);
        $invalidPaddingSize = chr(100); // Claim 100 bytes of padding (impossible)

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid padding size');

        OpcUaPadding::removeSymmetric($data . $invalidPaddingSize);
    }

    public function testRemovePaddingRejectsTooShortData(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Data too short');

        OpcUaPadding::removeSymmetric('');
    }

    public function testVerifySymmetric(): void
    {
        $data = 'Test data';
        $blockSize = 16;

        $padded = OpcUaPadding::addSymmetric($data, $blockSize);

        self::assertTrue(OpcUaPadding::verifySymmetric($padded, $blockSize));
    }

    public function testVerifySymmetricRejectsInvalidPadding(): void
    {
        $data = 'Test';
        $blockSize = 16;

        $padded = OpcUaPadding::addSymmetric($data, $blockSize);

        // Corrupt padding
        $corrupted = $padded;
        $corrupted[10] = chr(99);

        self::assertFalse(OpcUaPadding::verifySymmetric($corrupted, $blockSize));
    }

    public function testVerifySymmetricRejectsNonAlignedData(): void
    {
        $data = str_repeat('x', 13); // Not aligned to block size

        self::assertFalse(OpcUaPadding::verifySymmetric($data, 16));
    }

    public function testCalculatePaddingLength(): void
    {
        $blockSize = 16;

        // 5 bytes of data + 1 size byte = 6 bytes
        // Need 10 more bytes to reach 16 = 10 padding bytes + 1 size byte = 11 total
        self::assertSame(11, OpcUaPadding::calculatePaddingLength(5, $blockSize));

        // 15 bytes of data + 1 size byte = 16 bytes (aligned)
        // Need 0 padding bytes + 1 size byte = 1 total
        self::assertSame(1, OpcUaPadding::calculatePaddingLength(15, $blockSize));

        // 16 bytes of data + 1 size byte = 17 bytes
        // Need 15 more bytes to reach 32 = 15 padding bytes + 1 size byte = 16 total
        self::assertSame(16, OpcUaPadding::calculatePaddingLength(16, $blockSize));
    }

    public function testDifferentBlockSizes(): void
    {
        $data = 'Test';

        foreach ([8, 16, 32] as $blockSize) {
            $padded = OpcUaPadding::addSymmetric($data, $blockSize);

            self::assertSame(0, strlen($padded) % $blockSize, "Failed for block size: {$blockSize}");

            $unpadded = OpcUaPadding::removeSymmetric($padded);
            self::assertSame($data, $unpadded, "Roundtrip failed for block size: {$blockSize}");
        }
    }

    // ============== Asymmetric Padding Tests ==============

    public function testAsymmetricPaddingRoundtrip(): void
    {
        $data = 'Hello, OPC UA Asymmetric!';
        $plaintextBlockSize = 214; // Typical for 2048-bit RSA with OAEP SHA-1

        $padded = OpcUaPadding::addAsymmetric($data, $plaintextBlockSize, 256);
        $unpadded = OpcUaPadding::removeAsymmetric($padded);

        self::assertSame($data, $unpadded);
    }

    public function testAsymmetricPaddedDataIsMultipleOfBlockSize(): void
    {
        $data = 'Test asymmetric data';
        $plaintextBlockSize = 214;

        $padded = OpcUaPadding::addAsymmetric($data, $plaintextBlockSize, 256);

        self::assertSame(0, strlen($padded) % $plaintextBlockSize);
    }

    public function testAsymmetricPaddingWithVariousDataLengths(): void
    {
        $plaintextBlockSize = 214;

        // Test various data lengths
        for ($length = 0; $length <= 500; $length += 50) {
            $data = str_repeat('x', $length);

            $padded = OpcUaPadding::addAsymmetric($data, $plaintextBlockSize, 256);
            $unpadded = OpcUaPadding::removeAsymmetric($padded);

            self::assertSame($data, $unpadded, "Failed for data length: {$length}");
            self::assertSame(0, strlen($padded) % $plaintextBlockSize, "Padded data not aligned for length: {$length}");
        }
    }

    public function testAsymmetricPaddingSmallBlockSize(): void
    {
        // For keys <= 2048 bits, use 1-byte padding size
        $data = 'Small key test';
        $plaintextBlockSize = 100; // Smaller block size (< 256)

        $padded = OpcUaPadding::addAsymmetric($data, $plaintextBlockSize, 128);
        $unpadded = OpcUaPadding::removeAsymmetric($padded);

        self::assertSame($data, $unpadded);
        self::assertSame(0, strlen($padded) % $plaintextBlockSize);
    }

    public function testAsymmetricPaddingEmptyData(): void
    {
        $data = '';
        $plaintextBlockSize = 214;

        $padded = OpcUaPadding::addAsymmetric($data, $plaintextBlockSize, 256);
        $unpadded = OpcUaPadding::removeAsymmetric($padded);

        self::assertSame('', $unpadded);
        self::assertSame(0, strlen($padded) % $plaintextBlockSize);
    }

    public function testCalculateAsymmetricPaddingLength(): void
    {
        $plaintextBlockSize = 214;

        // 10 bytes of data + 1 size byte = 11 bytes
        // Need (214 - 11) = 203 padding bytes + 1 size byte = 204 total
        self::assertSame(204, OpcUaPadding::calculateAsymmetricPaddingLength(10, $plaintextBlockSize));

        // 213 bytes of data + 1 size byte = 214 bytes (aligned)
        // Need 0 padding bytes + 1 size byte = 1 total
        self::assertSame(1, OpcUaPadding::calculateAsymmetricPaddingLength(213, $plaintextBlockSize));
    }

    public function testAsymmetricPaddingWithLargeBlockSize(): void
    {
        // For keys > 2048 bits (block size > 256), use 2-byte padding size
        $data = 'Large key test';
        $plaintextBlockSize = 300; // Larger block size (> 256)

        $padded = OpcUaPadding::addAsymmetric($data, $plaintextBlockSize, 384);
        $unpadded = OpcUaPadding::removeAsymmetric($padded);

        self::assertSame($data, $unpadded);
        self::assertSame(0, strlen($padded) % $plaintextBlockSize);
    }
}
