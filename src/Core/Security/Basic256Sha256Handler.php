<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * Security handler for Basic256Sha256 policy
 *
 * Algorithms:
 * - Asymmetric encryption: RSA-2048 with OAEP SHA-1
 * - Asymmetric signature: RSA-2048 with PKCS#1 SHA-256
 * - Symmetric encryption: AES-256-CBC
 * - Symmetric signature: HMAC-SHA-256
 * - Key derivation: P_SHA256
 *
 * This is the recommended policy for most OPC UA applications.
 */
final class Basic256Sha256Handler implements SecurityPolicyHandlerInterface
{
    private const SYMMETRIC_KEY_LENGTH = 32; // 256 bits
    private const SIGNING_KEY_LENGTH = 32;   // 256 bits
    private const IV_LENGTH = 16;            // 128 bits
    private const NONCE_LENGTH = 32;         // 256 bits

    public function getPolicy(): SecurityPolicy
    {
        return SecurityPolicy::Basic256Sha256;
    }

    public function encryptAsymmetric(string $plaintext, string $certificate): string
    {
        return RsaCrypto::encrypt($plaintext, $certificate, RsaPadding::OAEP);
    }

    public function decryptAsymmetric(
        string $ciphertext,
        string $privateKey,
        ?string $password = null
    ): string {
        return RsaCrypto::decrypt($ciphertext, $privateKey, RsaPadding::OAEP, $password);
    }

    public function signAsymmetric(
        string $data,
        string $privateKey,
        ?string $password = null
    ): string {
        return RsaCrypto::sign(
            $data,
            $privateKey,
            SignatureAlgorithm::RSA_SHA256_PKCS1,
            $password
        );
    }

    public function verifyAsymmetric(
        string $data,
        string $signature,
        string $certificate
    ): bool {
        return RsaCrypto::verify(
            $data,
            $signature,
            $certificate,
            SignatureAlgorithm::RSA_SHA256_PKCS1
        );
    }

    public function encryptSymmetric(string $plaintext, string $key, string $iv): string
    {
        return AesCrypto::encrypt($plaintext, $key, $iv);
    }

    public function decryptSymmetric(string $ciphertext, string $key, string $iv): string
    {
        return AesCrypto::decrypt($ciphertext, $key, $iv);
    }

    public function signSymmetric(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key, true);
    }

    public function verifySymmetric(string $data, string $signature, string $key): bool
    {
        $expectedSignature = $this->signSymmetric($data, $key);
        return hash_equals($expectedSignature, $signature);
    }

    public function deriveKeys(string $clientNonce, string $serverNonce): array
    {
        return KeyDerivation::deriveSessionKeys(
            $clientNonce,
            $serverNonce,
            self::SIGNING_KEY_LENGTH,
            self::SYMMETRIC_KEY_LENGTH,
            self::IV_LENGTH,
            'sha256'
        );
    }

    public function getClientNonceLength(): int
    {
        return self::NONCE_LENGTH;
    }

    public function getAsymmetricSignatureLength(string $certificate): int
    {
        // RSA signature length equals key size
        return RsaCrypto::getKeySize($certificate);
    }

    public function getSymmetricSignatureLength(): int
    {
        // HMAC-SHA-256 produces 32 bytes
        return 32;
    }

    public function getSymmetricBlockSize(): int
    {
        // AES block size is always 16 bytes
        return 16;
    }

    public function getAsymmetricPlaintextBlockSize(string $certificate): int
    {
        return RsaCrypto::getPlaintextBlockSize($certificate, RsaPadding::OAEP);
    }

    public function getAsymmetricCiphertextBlockSize(string $certificate): int
    {
        return RsaCrypto::getCiphertextBlockSize($certificate);
    }
}
