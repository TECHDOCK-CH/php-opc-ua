<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * OPC UA message padding for symmetric encryption
 *
 * OPC UA uses a specific padding scheme for encrypted messages:
 * [Data][Padding Bytes][Padding Size]
 *
 * Where:
 * - Padding Bytes: Each byte has the value of (padding count)
 * - Padding Size: Single byte indicating number of padding bytes (NOT including size byte itself)
 *
 * Example: If we need 3 padding bytes, the padding looks like:
 * [Data][0x03][0x03][0x03][0x03]
 *        ^-padding bytes-^ ^size
 *
 * This is similar to PKCS#7 but with the count byte at the END instead of the last byte being the count.
 */
final class OpcUaPadding
{
    /**
     * Add OPC UA symmetric padding to data
     *
     * The padded data will be a multiple of blockSize.
     *
     * @param string $data Data to pad
     * @param int $blockSize Block size in bytes (usually 16 for AES)
     * @return string Padded data
     */
    public static function addSymmetric(string $data, int $blockSize): string
    {
        if ($blockSize < 1 || $blockSize > 256) {
            throw new InvalidArgumentException("Invalid block size: {$blockSize}");
        }

        // Calculate how many padding bytes we need
        // We need to account for the padding size byte itself
        $currentLength = strlen($data) + 1; // +1 for the padding size byte
        $paddingNeeded = $blockSize - ($currentLength % $blockSize);

        // If already aligned with the size byte, no padding bytes needed
        if ($paddingNeeded === $blockSize) {
            $paddingNeeded = 0;
        }

        // Create padding bytes (each byte contains the padding count value)
        $paddingValue = $paddingNeeded;
        $paddingBytes = str_repeat(chr($paddingValue), $paddingNeeded);

        // Append padding bytes + size byte
        $paddingSizeByte = chr($paddingValue);

        return $data . $paddingBytes . $paddingSizeByte;
    }

    /**
     * Remove and verify OPC UA symmetric padding from data
     *
     * @param string $data Padded data
     * @return string Original data without padding
     * @throws RuntimeException If padding is invalid
     */
    public static function removeSymmetric(string $data): string
    {
        if (strlen($data) < 1) {
            throw new RuntimeException('Data too short to contain padding');
        }

        // Read padding size from last byte
        $paddingSize = ord($data[strlen($data) - 1]);

        // Total bytes to remove: padding bytes + size byte
        $totalPaddingBytes = $paddingSize + 1;

        if ($totalPaddingBytes > strlen($data)) {
            throw new RuntimeException(
                "Invalid padding size: {$paddingSize} (data length: " . strlen($data) . ")"
            );
        }

        // Extract padding bytes (not including the size byte)
        if ($paddingSize > 0) {
            $paddingBytes = substr($data, -($totalPaddingBytes), $paddingSize);
            $expectedPadding = str_repeat(chr($paddingSize), $paddingSize);

            // Constant-time comparison to prevent timing attacks
            if (!hash_equals($expectedPadding, $paddingBytes)) {
                throw new RuntimeException('Invalid padding bytes - possible message corruption');
            }
        }

        // Remove padding bytes + size byte
        return substr($data, 0, -$totalPaddingBytes);
    }

    /**
     * Verify padding without removing it (for testing/debugging)
     *
     * @param string $data Padded data
     * @param int $blockSize Expected block size
     * @return bool True if padding is valid
     */
    public static function verifySymmetric(string $data, int $blockSize): bool
    {
        try {
            $unpaddedData = self::removeSymmetric($data);

            // Verify total length is multiple of block size
            if (strlen($data) % $blockSize !== 0) {
                return false;
            }

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Calculate how many padding bytes would be needed (including size byte)
     *
     * Useful for pre-allocating buffers
     *
     * @param int $dataLength Current data length
     * @param int $blockSize Block size in bytes
     * @return int Total padding bytes (padding + size byte)
     */
    public static function calculatePaddingLength(int $dataLength, int $blockSize): int
    {
        $currentLength = $dataLength + 1; // +1 for size byte
        $paddingNeeded = $blockSize - ($currentLength % $blockSize);

        if ($paddingNeeded === $blockSize) {
            $paddingNeeded = 0;
        }

        return $paddingNeeded + 1; // +1 for size byte
    }
}
