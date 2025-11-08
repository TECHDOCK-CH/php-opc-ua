<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use RuntimeException;

/**
 * Manages cryptographic state and operations for a secure channel
 *
 * Handles key derivation, encryption, and signing for both asymmetric
 * (OpenSecureChannel) and symmetric (service messages) operations.
 */
final class SecureChannelCrypto
{
    private ?string $clientNonce = null;
    private ?string $serverNonce = null;

    // Derived symmetric keys (populated after OpenSecureChannel)
    private ?string $clientSigningKey = null;
    private ?string $clientEncryptionKey = null;
    private ?string $clientIV = null;
    private ?string $serverSigningKey = null;
    private ?string $serverEncryptionKey = null;
    private ?string $serverIV = null;

    // Server certificate for asymmetric operations
    private ?string $serverCertificatePem = null;

    public function __construct(
        private readonly SecurityPolicyHandlerInterface $policyHandler,
        private readonly MessageSecurityMode $securityMode,
    ) {
    }

    /**
     * Set client nonce (generated during OpenSecureChannel request)
     */
    public function setClientNonce(string $nonce): void
    {
        $this->clientNonce = $nonce;
    }

    /**
     * Set server nonce and certificate (received in OpenSecureChannel response)
     * This will trigger key derivation.
     */
    public function setServerNonceAndCertificate(string $nonce, ?string $certificateDer = null): void
    {
        $this->serverNonce = $nonce;

        if ($certificateDer !== null && $certificateDer !== '') {
            $this->serverCertificatePem = $this->derToPem($certificateDer);
        }

        // Derive session keys if both nonces are available
        if ($this->clientNonce !== null) {
            $this->deriveKeys();
        }
    }

    /**
     * Check if encryption is enabled
     */
    public function isEncryptionEnabled(): bool
    {
        return $this->securityMode === MessageSecurityMode::SignAndEncrypt;
    }

    /**
     * Check if signing is enabled
     */
    public function isSigningEnabled(): bool
    {
        return $this->securityMode !== MessageSecurityMode::None;
    }

    /**
     * Encrypt and sign a message body (asymmetric - for OpenSecureChannel)
     *
     * @param string $plaintext Message body to encrypt
     * @return array{encrypted: string, signature: string}
     */
    public function encryptAsymmetric(string $plaintext): array
    {
        if (!$this->isEncryptionEnabled()) {
            return ['encrypted' => $plaintext, 'signature' => ''];
        }

        if ($this->serverCertificatePem === null) {
            throw new RuntimeException('Server certificate not set for asymmetric encryption');
        }

        // Add padding if needed for block size
        $plaintextBlockSize = $this->policyHandler->getAsymmetricPlaintextBlockSize(
            $this->serverCertificatePem
        );
        $ciphertextBlockSize = $this->policyHandler->getAsymmetricCiphertextBlockSize(
            $this->serverCertificatePem
        );

        // Encrypt in blocks
        $encrypted = '';
        for ($i = 0; $i < strlen($plaintext); $i += $plaintextBlockSize) {
            $block = substr($plaintext, $i, $plaintextBlockSize);
            $encrypted .= $this->policyHandler->encryptAsymmetric($block, $this->serverCertificatePem);
        }

        return [
            'encrypted' => $encrypted,
            'signature' => '', // Signature added separately to sequence header
        ];
    }

    /**
     * Sign data asymmetrically (for OpenSecureChannel with client certificate)
     *
     * @param string $data Data to sign
     * @param string $clientPrivateKeyPem Client private key in PEM format
     * @return string Signature bytes
     */
    public function signAsymmetric(string $data, string $clientPrivateKeyPem): string
    {
        if (!$this->isSigningEnabled()) {
            return '';
        }

        return $this->policyHandler->signAsymmetric($data, $clientPrivateKeyPem);
    }

    /**
     * Verify asymmetric signature (for OpenSecureChannel response)
     *
     * @param string $data Data that was signed
     * @param string $signature Signature to verify
     * @return bool True if signature is valid
     */
    public function verifyAsymmetric(string $data, string $signature): bool
    {
        if (!$this->isSigningEnabled()) {
            return true; // No signature = always valid
        }

        if ($this->serverCertificatePem === null) {
            throw new RuntimeException('Server certificate not set for signature verification');
        }

        return $this->policyHandler->verifyAsymmetric($data, $signature, $this->serverCertificatePem);
    }

    /**
     * Encrypt and sign a message body (symmetric - for service messages)
     *
     * @param string $sequenceHeader Sequence header bytes (included in signature)
     * @param string $body Message body to encrypt
     * @return array{encrypted: string, signature: string}
     */
    public function encryptSymmetric(string $sequenceHeader, string $body): array
    {
        if ($this->clientEncryptionKey === null || $this->clientIV === null || $this->clientSigningKey === null) {
            throw new RuntimeException('Session keys not derived yet');
        }

        $encrypted = $body;
        $signature = '';

        // Encrypt body if encryption is enabled
        if ($this->isEncryptionEnabled()) {
            $encrypted = $this->policyHandler->encryptSymmetric(
                $body,
                $this->clientEncryptionKey,
                $this->clientIV
            );
        }

        // Sign (sequenceHeader + encrypted body) if signing is enabled
        if ($this->isSigningEnabled()) {
            $dataToSign = $sequenceHeader . $encrypted;
            $signature = $this->policyHandler->signSymmetric($dataToSign, $this->clientSigningKey);
        }

        return [
            'encrypted' => $encrypted,
            'signature' => $signature,
        ];
    }

    /**
     * Decrypt and verify a message (symmetric - for service responses)
     *
     * @param string $sequenceHeader Sequence header bytes (included in signature)
     * @param string $encryptedBody Encrypted message body
     * @param string $signature Message signature
     * @return string Decrypted plaintext
     * @throws RuntimeException If signature verification fails or decryption fails
     */
    public function decryptSymmetric(string $sequenceHeader, string $encryptedBody, string $signature): string
    {
        if ($this->serverEncryptionKey === null || $this->serverIV === null || $this->serverSigningKey === null) {
            throw new RuntimeException('Session keys not derived yet');
        }

        // Verify signature if signing is enabled
        if ($this->isSigningEnabled() && $signature !== '') {
            $dataToVerify = $sequenceHeader . $encryptedBody;
            $isValid = $this->policyHandler->verifySymmetric(
                $dataToVerify,
                $signature,
                $this->serverSigningKey
            );

            if (!$isValid) {
                throw new RuntimeException('Message signature verification failed');
            }
        }

        // Decrypt body if encryption is enabled
        if ($this->isEncryptionEnabled()) {
            $paddedPlaintext = $this->policyHandler->decryptSymmetric(
                $encryptedBody,
                $this->serverEncryptionKey,
                $this->serverIV
            );

            // Remove OPC UA padding
            return OpcUaPadding::removeSymmetric($paddedPlaintext);
        }

        return $encryptedBody;
    }

    /**
     * Get symmetric signature length
     */
    public function getSymmetricSignatureLength(): int
    {
        if (!$this->isSigningEnabled()) {
            return 0;
        }

        return $this->policyHandler->getSymmetricSignatureLength();
    }

    /**
     * Get asymmetric signature length
     */
    public function getAsymmetricSignatureLength(): int
    {
        if (!$this->isSigningEnabled() || $this->serverCertificatePem === null) {
            return 0;
        }

        return $this->policyHandler->getAsymmetricSignatureLength($this->serverCertificatePem);
    }

    /**
     * Derive session keys from client and server nonces
     */
    private function deriveKeys(): void
    {
        if ($this->clientNonce === null || $this->serverNonce === null) {
            throw new RuntimeException('Cannot derive keys: nonces not set');
        }

        $keys = $this->policyHandler->deriveKeys($this->clientNonce, $this->serverNonce);

        $this->clientSigningKey = $keys['clientSigningKey'];
        $this->clientEncryptionKey = $keys['clientEncryptionKey'];
        $this->clientIV = $keys['clientIV'];
        $this->serverSigningKey = $keys['serverSigningKey'];
        $this->serverEncryptionKey = $keys['serverEncryptionKey'];
        $this->serverIV = $keys['serverIV'];
    }

    /**
     * Convert DER-encoded certificate to PEM format
     */
    private function derToPem(string $der): string
    {
        $pem = "-----BEGIN CERTIFICATE-----\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");
        $pem .= "-----END CERTIFICATE-----\n";
        return $pem;
    }
}
