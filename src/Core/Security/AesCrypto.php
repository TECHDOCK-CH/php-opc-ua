<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use phpseclib3\Crypt\AES;
use RuntimeException;

/**
 * AES symmetric encryption for OPC UA
 *
 * Supports AES-128-CBC and AES-256-CBC
 */
final class AesCrypto
{
    /**
     * Encrypt data using AES-CBC
     *
     * IMPORTANT: This function expects pre-padded data.
     * For OPC UA, use OpcUaPadding::addSymmetric() before calling this method.
     *
     * @param string $plaintext Data to encrypt (must be already padded to block size)
     * @param string $key Encryption key (16 or 32 bytes)
     * @param string $iv Initialization vector (16 bytes)
     * @return string Encrypted data
     * @throws RuntimeException
     */
    public static function encrypt(string $plaintext, string $key, string $iv): string
    {
        $keyLength = strlen($key);

        if ($keyLength !== 16 && $keyLength !== 32) {
            throw new RuntimeException(
                "Invalid AES key length: {$keyLength} bytes (expected 16 or 32)"
            );
        }

        if (strlen($iv) !== 16) {
            throw new RuntimeException(
                "Invalid AES IV length: " . strlen($iv) . " bytes (expected 16)"
            );
        }

        // Verify data is already padded to block size
        if (strlen($plaintext) % 16 !== 0) {
            throw new RuntimeException(
                "Plaintext must be padded to block size (16 bytes). " .
                "Use OpcUaPadding::addSymmetric() before encryption."
            );
        }

        $cipher = new AES('cbc');
        $cipher->setKey($key);
        $cipher->setIV($iv);

        // Disable automatic padding - we're using manual OPC UA padding
        $cipher->disablePadding();

        return $cipher->encrypt($plaintext);
    }

    /**
     * Decrypt data using AES-CBC
     *
     * IMPORTANT: This function returns data with OPC UA padding still attached.
     * For OPC UA, use OpcUaPadding::removeSymmetric() after calling this method.
     *
     * @param string $ciphertext Data to decrypt
     * @param string $key Decryption key (16 or 32 bytes)
     * @param string $iv Initialization vector (16 bytes)
     * @return string Decrypted data (with padding still attached)
     * @throws RuntimeException
     */
    public static function decrypt(string $ciphertext, string $key, string $iv): string
    {
        $keyLength = strlen($key);

        if ($keyLength !== 16 && $keyLength !== 32) {
            throw new RuntimeException(
                "Invalid AES key length: {$keyLength} bytes (expected 16 or 32)"
            );
        }

        if (strlen($iv) !== 16) {
            throw new RuntimeException(
                "Invalid AES IV length: " . strlen($iv) . " bytes (expected 16)"
            );
        }

        // Verify ciphertext is multiple of block size
        if (strlen($ciphertext) % 16 !== 0) {
            throw new RuntimeException(
                "Ciphertext length must be multiple of block size (16 bytes)"
            );
        }

        $cipher = new AES('cbc');
        $cipher->setKey($key);
        $cipher->setIV($iv);

        // Disable automatic padding - we're using manual OPC UA padding
        $cipher->disablePadding();

        $decrypted = $cipher->decrypt($ciphertext);

        // Return with OPC UA padding still attached
        // Caller must use OpcUaPadding::removeSymmetric() to remove it
        return $decrypted;
    }

    /**
     * Generate a random initialization vector (16 bytes)
     */
    public static function generateIV(): string
    {
        return random_bytes(16);
    }
}
