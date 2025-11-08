<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use Exception;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use RuntimeException;

/**
 * RSA cryptographic operations for OPC UA
 *
 * Supports:
 * - PKCS#1 v1.5 padding (legacy)
 * - OAEP padding (recommended)
 * - PSS signatures (strongest)
 */
final class RsaCrypto
{
    /**
     * Encrypt data using RSA public key
     *
     * @param string $plaintext Data to encrypt
     * @param string $certificate PEM-encoded X.509 certificate
     * @param RsaPadding $padding Padding scheme
     * @return string Encrypted data
     * @throws RuntimeException
     */
    public static function encrypt(
        string $plaintext,
        string $certificate,
        RsaPadding $padding = RsaPadding::PKCS1
    ): string {
        $publicKey = self::loadPublicKeyFromCertificate($certificate);

        return match ($padding) {
            RsaPadding::PKCS1 => $publicKey
                ->withPadding(RSA::ENCRYPTION_PKCS1)
                ->encrypt($plaintext),
            RsaPadding::OAEP => $publicKey
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha1')
                ->withMGFHash('sha1')
                ->encrypt($plaintext),
            RsaPadding::OAEP_SHA256 => $publicKey
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->encrypt($plaintext),
        };
    }

    /**
     * Decrypt data using RSA private key
     *
     * @param string $ciphertext Data to decrypt
     * @param string $privateKey PEM-encoded private key
     * @param RsaPadding $padding Padding scheme
     * @param string|null $password Optional password for encrypted key
     * @return string Decrypted data
     * @throws RuntimeException
     */
    public static function decrypt(
        string $ciphertext,
        string $privateKey,
        RsaPadding $padding = RsaPadding::PKCS1,
        ?string $password = null
    ): string {
        $key = self::loadPrivateKey($privateKey, $password);

        $result = match ($padding) {
            RsaPadding::PKCS1 => $key
                ->withPadding(RSA::ENCRYPTION_PKCS1)
                ->decrypt($ciphertext),
            RsaPadding::OAEP => $key
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha1')
                ->withMGFHash('sha1')
                ->decrypt($ciphertext),
            RsaPadding::OAEP_SHA256 => $key
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->decrypt($ciphertext),
        };

        if ($result === false) {
            throw new RuntimeException('RSA decryption failed');
        }

        return $result;
    }

    /**
     * Sign data using RSA private key
     *
     * @param string $data Data to sign
     * @param string $privateKey PEM-encoded private key
     * @param SignatureAlgorithm $algorithm Signature algorithm
     * @param string|null $password Optional password for encrypted key
     * @return string Signature bytes
     * @throws RuntimeException
     */
    public static function sign(
        string $data,
        string $privateKey,
        SignatureAlgorithm $algorithm,
        ?string $password = null
    ): string {
        $key = self::loadPrivateKey($privateKey, $password);

        return match ($algorithm) {
            SignatureAlgorithm::RSA_SHA1_PKCS1 => $key
                ->withPadding(RSA::SIGNATURE_PKCS1)
                ->withHash('sha1')
                ->sign($data),
            SignatureAlgorithm::RSA_SHA256_PKCS1 => $key
                ->withPadding(RSA::SIGNATURE_PKCS1)
                ->withHash('sha256')
                ->sign($data),
            SignatureAlgorithm::RSA_SHA256_PSS => $key
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->sign($data),
        };
    }

    /**
     * Verify signature using RSA public key
     *
     * @param string $data Original data
     * @param string $signature Signature to verify
     * @param string $certificate PEM-encoded X.509 certificate
     * @param SignatureAlgorithm $algorithm Signature algorithm
     * @return bool True if signature is valid
     */
    public static function verify(
        string $data,
        string $signature,
        string $certificate,
        SignatureAlgorithm $algorithm
    ): bool {
        $publicKey = self::loadPublicKeyFromCertificate($certificate);

        return match ($algorithm) {
            SignatureAlgorithm::RSA_SHA1_PKCS1 => $publicKey
                ->withPadding(RSA::SIGNATURE_PKCS1)
                ->withHash('sha1')
                ->verify($data, $signature),
            SignatureAlgorithm::RSA_SHA256_PKCS1 => $publicKey
                ->withPadding(RSA::SIGNATURE_PKCS1)
                ->withHash('sha256')
                ->verify($data, $signature),
            SignatureAlgorithm::RSA_SHA256_PSS => $publicKey
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->verify($data, $signature),
        };
    }

    /**
     * Get RSA key size in bytes
     */
    public static function getKeySize(string $certificate): int
    {
        $publicKey = self::loadPublicKeyFromCertificate($certificate);
        return $publicKey->getLength() / 8;
    }

    /**
     * Get plaintext block size for encryption
     */
    public static function getPlaintextBlockSize(
        string $certificate,
        RsaPadding $padding
    ): int {
        $keySize = self::getKeySize($certificate);

        return match ($padding) {
            RsaPadding::PKCS1 => $keySize - 11,  // PKCS#1 v1.5 overhead
            RsaPadding::OAEP => $keySize - 42,    // OAEP SHA-1 overhead
            RsaPadding::OAEP_SHA256 => $keySize - 66, // OAEP SHA-256 overhead
        };
    }

    /**
     * Get ciphertext block size for decryption
     */
    public static function getCiphertextBlockSize(string $certificate): int
    {
        return self::getKeySize($certificate);
    }

    /**
     * Load public key from X.509 certificate
     */
    private static function loadPublicKeyFromCertificate(string $certificate): RSA
    {
        try {
            // Try loading as PEM-encoded certificate
            $key = PublicKeyLoader::load($certificate);

            if (!$key instanceof RSA) {
                throw new RuntimeException('Certificate does not contain RSA key');
            }

            return $key;
        } catch (Exception $e) {
            throw new RuntimeException(
                "Failed to load public key from certificate: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Load private key from PEM string
     */
    private static function loadPrivateKey(
        string $privateKey,
        ?string $password = null
    ): RSA {
        try {
            $key = PublicKeyLoader::load($privateKey, $password ?? '');

            if (!$key instanceof RSA) {
                throw new RuntimeException('Key is not an RSA key');
            }

            return $key;
        } catch (Exception $e) {
            throw new RuntimeException(
                "Failed to load private key: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
