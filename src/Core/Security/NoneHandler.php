<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * Security handler for None policy
 *
 * No encryption or signing - plaintext communication only.
 * Should only be used in trusted networks or for testing.
 */
final class NoneHandler implements SecurityPolicyHandlerInterface
{
    public function getPolicy(): SecurityPolicy
    {
        return SecurityPolicy::None;
    }

    public function encryptAsymmetric(string $plaintext, string $certificate): string
    {
        // No encryption
        return $plaintext;
    }

    public function decryptAsymmetric(
        string $ciphertext,
        string $privateKey,
        ?string $password = null
    ): string {
        // No encryption
        return $ciphertext;
    }

    public function signAsymmetric(
        string $data,
        string $privateKey,
        ?string $password = null
    ): string {
        // No signing
        return '';
    }

    public function verifyAsymmetric(
        string $data,
        string $signature,
        string $certificate
    ): bool {
        // No signing - always valid
        return true;
    }

    public function encryptSymmetric(string $plaintext, string $key, string $iv): string
    {
        // No encryption
        return $plaintext;
    }

    public function decryptSymmetric(string $ciphertext, string $key, string $iv): string
    {
        // No encryption
        return $ciphertext;
    }

    public function signSymmetric(string $data, string $key): string
    {
        // No signing
        return '';
    }

    public function verifySymmetric(string $data, string $signature, string $key): bool
    {
        // No signing - always valid
        return true;
    }

    public function deriveKeys(string $clientNonce, string $serverNonce): array
    {
        // No keys needed for plaintext communication
        return [
            'clientSigningKey' => '',
            'clientEncryptionKey' => '',
            'clientIV' => '',
            'serverSigningKey' => '',
            'serverEncryptionKey' => '',
            'serverIV' => '',
        ];
    }

    public function getClientNonceLength(): int
    {
        return 0; // No nonce needed
    }

    public function getAsymmetricSignatureLength(string $certificate): int
    {
        return 0; // No signature
    }

    public function getSymmetricSignatureLength(): int
    {
        return 0; // No signature
    }

    public function getSymmetricBlockSize(): int
    {
        return 1; // No blocking needed
    }

    public function getAsymmetricPlaintextBlockSize(string $certificate): int
    {
        return PHP_INT_MAX; // No size limit
    }

    public function getAsymmetricCiphertextBlockSize(string $certificate): int
    {
        return PHP_INT_MAX; // No size limit
    }
}
