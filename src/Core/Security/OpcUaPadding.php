<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * OPC UA message padding for symmetric and asymmetric encryption
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
 *
 * For asymmetric encryption (RSA), the padding includes an extra byte for large keys:
 * [Data][Padding Bytes][Padding Size][Extra Padding Size] (if key size > 2048 bits)
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

    /**
     * Add OPC UA asymmetric padding to data
     *
     * For asymmetric encryption, the plaintext is padded to be a multiple of the
     * plaintext block size. The padding accounts for the signature that will be
     * appended after the encrypted data.
     *
     * Structure: [Data][Padding Bytes][Padding Size (1 byte)][ExtraPaddingSize (0 or 1 byte)]
     *
     * @param string $data Data to pad
     * @param int $plaintextBlockSize Plaintext block size for RSA encryption
     * @param int $signatureLength Length of the signature that will be appended
     * @return string Padded data
     */
    public static function addAsymmetric(string $data, int $plaintextBlockSize, int $signatureLength): string
    {
        if ($plaintextBlockSize < 1) {
            throw new InvalidArgumentException("Invalid plaintext block size: {$plaintextBlockSize}");
        }

        // Determine if we need 1 or 2 bytes for padding size
        // For keys > 2048 bits (256 bytes), we need 2 bytes for ExtraPaddingSize
        // The padding size byte(s) indicate the total padding bytes count
        $paddingSizeBytes = ($plaintextBlockSize > 256) ? 2 : 1;

        // Calculate how many padding bytes are needed
        // Total plaintext = Data + PaddingBytes + PaddingSize(1-2 bytes)
        // This must be a multiple of plaintextBlockSize
        $currentLength = strlen($data) + $paddingSizeBytes;
        $paddingNeeded = $plaintextBlockSize - ($currentLength % $plaintextBlockSize);

        if ($paddingNeeded === $plaintextBlockSize) {
            $paddingNeeded = 0;
        }

        // Create padding bytes (each byte contains the padding count value, modulo 256)
        $paddingValue = $paddingNeeded % 256;
        $paddingBytes = str_repeat(chr($paddingValue), $paddingNeeded);

        // Append padding bytes + size byte(s)
        if ($paddingSizeBytes === 1) {
            // Single byte for padding size
            $paddingSizeByte = chr($paddingNeeded);
            return $data . $paddingBytes . $paddingSizeByte;
        } else {
            // Two bytes for padding size (little-endian)
            $paddingSizeLsb = chr($paddingNeeded & 0xFF);
            $paddingSizeMsb = chr(($paddingNeeded >> 8) & 0xFF);
            return $data . $paddingBytes . $paddingSizeLsb . $paddingSizeMsb;
        }
    }

    /**
     * Remove and verify OPC UA asymmetric padding from data
     *
     * @param string $data Padded data
     * @return string Original data without padding
     * @throws RuntimeException If padding is invalid
     */
    public static function removeAsymmetric(string $data): string
    {
        if (strlen($data) < 1) {
            throw new RuntimeException('Data too short to contain padding');
        }

        // Read padding size from last byte(s)
        // We need to determine if this is 1 or 2 byte padding size
        // For now, try single byte first (most common for 2048-bit keys)

        // Read the last byte as padding size
        $paddingSize = ord($data[strlen($data) - 1]);

        // Check if this looks like a valid single-byte padding
        $totalPaddingBytes = $paddingSize + 1;

        if ($totalPaddingBytes <= strlen($data)) {
            // Validate padding bytes
            if ($paddingSize > 0) {
                $paddingBytes = substr($data, -$totalPaddingBytes, $paddingSize);
                $expectedValue = $paddingSize % 256;
                $expectedPadding = str_repeat(chr($expectedValue), $paddingSize);

                if (hash_equals($expectedPadding, $paddingBytes)) {
                    // Valid single-byte padding
                    return substr($data, 0, -$totalPaddingBytes);
                }
            } else {
                // Zero padding bytes, just remove the size byte
                return substr($data, 0, -1);
            }
        }

        // Try 2-byte padding size (for larger keys)
        if (strlen($data) < 2) {
            throw new RuntimeException('Invalid padding: data too short for 2-byte padding size');
        }

        $paddingSizeLsb = ord($data[strlen($data) - 2]);
        $paddingSizeMsb = ord($data[strlen($data) - 1]);
        $paddingSize = $paddingSizeLsb | ($paddingSizeMsb << 8);

        $totalPaddingBytes = $paddingSize + 2;

        if ($totalPaddingBytes > strlen($data)) {
            throw new RuntimeException(
                "Invalid padding size: {$paddingSize} (data length: " . strlen($data) . ")"
            );
        }

        // Validate padding bytes for 2-byte case
        if ($paddingSize > 0) {
            $paddingBytes = substr($data, -$totalPaddingBytes, $paddingSize);
            $expectedValue = $paddingSize % 256;
            $expectedPadding = str_repeat(chr($expectedValue), $paddingSize);

            if (!hash_equals($expectedPadding, $paddingBytes)) {
                throw new RuntimeException('Invalid padding bytes - possible message corruption');
            }
        }

        return substr($data, 0, -$totalPaddingBytes);
    }

    /**
     * Calculate asymmetric padding length
     *
     * @param int $dataLength Current data length
     * @param int $plaintextBlockSize Plaintext block size for RSA encryption
     * @return int Total padding bytes (padding + size byte(s))
     */
    public static function calculateAsymmetricPaddingLength(int $dataLength, int $plaintextBlockSize): int
    {
        $paddingSizeBytes = ($plaintextBlockSize > 256) ? 2 : 1;
        $currentLength = $dataLength + $paddingSizeBytes;
        $paddingNeeded = $plaintextBlockSize - ($currentLength % $plaintextBlockSize);

        if ($paddingNeeded === $plaintextBlockSize) {
            $paddingNeeded = 0;
        }

        return $paddingNeeded + $paddingSizeBytes;
    }
}
