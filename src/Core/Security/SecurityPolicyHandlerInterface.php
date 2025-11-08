<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * Interface for security policy-specific cryptographic operations
 *
 * Each OPC UA security policy defines different algorithms for:
 * - Asymmetric encryption (used in OpenSecureChannel)
 * - Asymmetric signing (used in OpenSecureChannel)
 * - Symmetric encryption (used in MSG messages)
 * - Symmetric signing (used in MSG messages)
 * - Key derivation
 */
interface SecurityPolicyHandlerInterface
{
    /**
     * Get the security policy this handler implements
     */
    public function getPolicy(): SecurityPolicy;

    /**
     * Encrypt data using asymmetric encryption (RSA)
     *
     * Used for OpenSecureChannel messages with server's public certificate
     *
     * @param string $plaintext Data to encrypt
     * @param string $certificate PEM-encoded X.509 certificate
     * @return string Encrypted data
     */
    public function encryptAsymmetric(string $plaintext, string $certificate): string;

    /**
     * Decrypt data using asymmetric encryption (RSA)
     *
     * Used for OpenSecureChannel responses with client's private key
     *
     * @param string $ciphertext Data to decrypt
     * @param string $privateKey PEM-encoded private key
     * @param string|null $password Password for encrypted private key
     * @return string Decrypted data
     */
    public function decryptAsymmetric(
        string $ciphertext,
        string $privateKey,
        ?string $password = null
    ): string;

    /**
     * Sign data using asymmetric signature (RSA)
     *
     * Used for OpenSecureChannel messages
     *
     * @param string $data Data to sign
     * @param string $privateKey PEM-encoded private key
     * @param string|null $password Password for encrypted private key
     * @return string Signature bytes
     */
    public function signAsymmetric(
        string $data,
        string $privateKey,
        ?string $password = null
    ): string;

    /**
     * Verify asymmetric signature (RSA)
     *
     * Used for OpenSecureChannel responses
     *
     * @param string $data Original data
     * @param string $signature Signature to verify
     * @param string $certificate PEM-encoded X.509 certificate
     * @return bool True if signature is valid
     */
    public function verifyAsymmetric(
        string $data,
        string $signature,
        string $certificate
    ): bool;

    /**
     * Encrypt data using symmetric encryption (AES)
     *
     * Used for service messages (MSG)
     *
     * @param string $plaintext Data to encrypt
     * @param string $key Encryption key
     * @param string $iv Initialization vector
     * @return string Encrypted data
     */
    public function encryptSymmetric(string $plaintext, string $key, string $iv): string;

    /**
     * Decrypt data using symmetric encryption (AES)
     *
     * Used for service messages (MSG)
     *
     * @param string $ciphertext Data to decrypt
     * @param string $key Decryption key
     * @param string $iv Initialization vector
     * @return string Decrypted data
     */
    public function decryptSymmetric(string $ciphertext, string $key, string $iv): string;

    /**
     * Sign data using symmetric signature (HMAC)
     *
     * Used for service messages (MSG)
     *
     * @param string $data Data to sign
     * @param string $key Signing key
     * @return string Signature bytes
     */
    public function signSymmetric(string $data, string $key): string;

    /**
     * Verify symmetric signature (HMAC)
     *
     * Used for service messages (MSG)
     *
     * @param string $data Original data
     * @param string $signature Signature to verify
     * @param string $key Signing key
     * @return bool True if signature is valid
     */
    public function verifySymmetric(string $data, string $signature, string $key): bool;

    /**
     * Derive session keys from client and server nonces
     *
     * @param string $clientNonce Client-generated random bytes
     * @param string $serverNonce Server-generated random bytes
     * @return array{
     *     clientSigningKey: string,
     *     clientEncryptionKey: string,
     *     clientIV: string,
     *     serverSigningKey: string,
     *     serverEncryptionKey: string,
     *     serverIV: string
     * }
     */
    public function deriveKeys(string $clientNonce, string $serverNonce): array;

    /**
     * Get the required client nonce length in bytes
     */
    public function getClientNonceLength(): int;

    /**
     * Get the asymmetric signature length in bytes
     */
    public function getAsymmetricSignatureLength(string $certificate): int;

    /**
     * Get the symmetric signature length in bytes
     */
    public function getSymmetricSignatureLength(): int;

    /**
     * Get the symmetric encryption block size in bytes
     */
    public function getSymmetricBlockSize(): int;

    /**
     * Get the plaintext block size for asymmetric encryption
     */
    public function getAsymmetricPlaintextBlockSize(string $certificate): int;

    /**
     * Get the ciphertext block size for asymmetric encryption
     */
    public function getAsymmetricCiphertextBlockSize(string $certificate): int;
}
