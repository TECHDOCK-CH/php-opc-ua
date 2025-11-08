<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * Key derivation functions for OPC UA
 *
 * Implements PSHA (Pseudo-Random Function with HMAC-based PRF) as defined in:
 * OPC UA Part 6 - Section 6.7.5 - Deriving Keys
 */
final class KeyDerivation
{
    /**
     * Derive keys using P_SHA256
     *
     * Used by most modern security policies (Basic256Sha256, Aes128_Sha256_RsaOaep, etc.)
     *
     * @param string $secret The shared secret (typically client nonce + server nonce)
     * @param string $seed Additional entropy
     * @param int $length Number of bytes to generate
     * @return string Derived key material
     */
    public static function pSha256(string $secret, string $seed, int $length): string
    {
        return self::pHash('sha256', $secret, $seed, $length);
    }

    /**
     * Derive keys using P_SHA1
     *
     * Used by legacy security policies (Basic128Rsa15, Basic256)
     *
     * @param string $secret The shared secret
     * @param string $seed Additional entropy
     * @param int $length Number of bytes to generate
     * @return string Derived key material
     */
    public static function pSha1(string $secret, string $seed, int $length): string
    {
        return self::pHash('sha1', $secret, $seed, $length);
    }

    /**
     * Derive session keys from nonces
     *
     * OPC UA Part 6 - Section 6.7.5:
     * The keys are derived by passing the nonces to a pseudo-random function which
     * produces a sequence of bytes from a set of inputs.
     *
     * @param string $clientNonce Client-generated random bytes
     * @param string $serverNonce Server-generated random bytes
     * @param int $signingKeyLength Length of signing key in bytes
     * @param int $encryptionKeyLength Length of encryption key in bytes
     * @param int $ivLength Length of initialization vector in bytes
     * @param string $hashAlgorithm 'sha1' or 'sha256'
     * @return array{
     *     clientSigningKey: string,
     *     clientEncryptionKey: string,
     *     clientIV: string,
     *     serverSigningKey: string,
     *     serverEncryptionKey: string,
     *     serverIV: string
     * }
     */
    public static function deriveSessionKeys(
        string $clientNonce,
        string $serverNonce,
        int $signingKeyLength,
        int $encryptionKeyLength,
        int $ivLength,
        string $hashAlgorithm = 'sha256'
    ): array {
        $secret = $serverNonce . $clientNonce;
        $seed = $serverNonce . $clientNonce;

        // Calculate total length needed
        $totalLength = 2 * ($signingKeyLength + $encryptionKeyLength + $ivLength);

        // Derive key material
        $keyMaterial = $hashAlgorithm === 'sha256'
            ? self::pSha256($secret, $seed, $totalLength)
            : self::pSha1($secret, $seed, $totalLength);

        $offset = 0;

        // Extract keys in the order specified by OPC UA
        $clientSigningKey = substr($keyMaterial, $offset, $signingKeyLength);
        $offset += $signingKeyLength;

        $clientEncryptionKey = substr($keyMaterial, $offset, $encryptionKeyLength);
        $offset += $encryptionKeyLength;

        $clientIV = substr($keyMaterial, $offset, $ivLength);
        $offset += $ivLength;

        $serverSigningKey = substr($keyMaterial, $offset, $signingKeyLength);
        $offset += $signingKeyLength;

        $serverEncryptionKey = substr($keyMaterial, $offset, $encryptionKeyLength);
        $offset += $encryptionKeyLength;

        $serverIV = substr($keyMaterial, $offset, $ivLength);

        return [
            'clientSigningKey' => $clientSigningKey,
            'clientEncryptionKey' => $clientEncryptionKey,
            'clientIV' => $clientIV,
            'serverSigningKey' => $serverSigningKey,
            'serverEncryptionKey' => $serverEncryptionKey,
            'serverIV' => $serverIV,
        ];
    }

    /**
     * Generic P_HASH implementation
     *
     * From TLS RFC 2246 Section 5:
     * P_hash(secret, seed) = HMAC_hash(secret, A(1) + seed) +
     *                        HMAC_hash(secret, A(2) + seed) +
     *                        HMAC_hash(secret, A(3) + seed) + ...
     *
     * Where:
     * A(0) = seed
     * A(i) = HMAC_hash(secret, A(i-1))
     *
     * @param string $algorithm Hash algorithm ('sha1' or 'sha256')
     * @param string $secret The secret key
     * @param string $seed The seed data
     * @param int $length Number of bytes to generate
     * @return string Generated pseudo-random bytes
     */
    private static function pHash(
        string $algorithm,
        string $secret,
        string $seed,
        int $length
    ): string {
        $result = '';
        $a = $seed; // A(0) = seed

        while (strlen($result) < $length) {
            // A(i) = HMAC_hash(secret, A(i-1))
            $a = hash_hmac($algorithm, $a, $secret, true);

            // P_hash chunk = HMAC_hash(secret, A(i) + seed)
            $result .= hash_hmac($algorithm, $a . $seed, $secret, true);
        }

        return substr($result, 0, $length);
    }
}
