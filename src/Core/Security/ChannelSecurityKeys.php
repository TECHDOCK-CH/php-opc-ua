<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * Stores derived symmetric encryption/signing keys for a secure channel
 *
 * Keys are derived from client and server nonces using PSHA (Pseudo-Random Function)
 * as defined in OPC UA Part 6 - Section 6.7.5
 *
 * Key derivation:
 * - Client uses: secret=ClientNonce, seed=ServerNonce
 * - Server uses: secret=ServerNonce, seed=ClientNonce
 *
 * This creates unique keys for each direction of communication.
 */
final class ChannelSecurityKeys
{
    /**
     * @param string $clientSigningKey HMAC key for signing client→server messages
     * @param string $clientEncryptionKey AES key for encrypting client→server messages
     * @param string $clientIV Initialization vector for client→server encryption
     * @param string $serverSigningKey HMAC key for verifying server→client messages
     * @param string $serverEncryptionKey AES key for decrypting server→client messages
     * @param string $serverIV Initialization vector for server→client decryption
     * @param int $tokenId Security token ID these keys are associated with
     */
    public function __construct(
        public readonly string $clientSigningKey,
        public readonly string $clientEncryptionKey,
        public readonly string $clientIV,
        public readonly string $serverSigningKey,
        public readonly string $serverEncryptionKey,
        public readonly string $serverIV,
        public readonly int $tokenId,
    ) {
    }

    /**
     * Derive session keys from nonces using the specified security policy
     *
     * @param string $clientNonce Random bytes from client (typically 32 bytes)
     * @param string $serverNonce Random bytes from server (typically 32 bytes)
     * @param int $tokenId Security token ID from OpenSecureChannelResponse
     * @param SecurityPolicyHandlerInterface $handler Security policy handler
     * @return self Derived keys
     */
    public static function derive(
        string $clientNonce,
        string $serverNonce,
        int $tokenId,
        SecurityPolicyHandlerInterface $handler
    ): self {
        $keys = $handler->deriveKeys($clientNonce, $serverNonce);

        return new self(
            clientSigningKey: $keys['clientSigningKey'],
            clientEncryptionKey: $keys['clientEncryptionKey'],
            clientIV: $keys['clientIV'],
            serverSigningKey: $keys['serverSigningKey'],
            serverEncryptionKey: $keys['serverEncryptionKey'],
            serverIV: $keys['serverIV'],
            tokenId: $tokenId,
        );
    }

    /**
     * Get key sizes for debugging/logging
     *
     * @return array{
     *     clientSigningKey: int,
     *     clientEncryptionKey: int,
     *     clientIV: int,
     *     serverSigningKey: int,
     *     serverEncryptionKey: int,
     *     serverIV: int
     * }
     */
    public function getKeySizes(): array
    {
        return [
            'clientSigningKey' => strlen($this->clientSigningKey),
            'clientEncryptionKey' => strlen($this->clientEncryptionKey),
            'clientIV' => strlen($this->clientIV),
            'serverSigningKey' => strlen($this->serverSigningKey),
            'serverEncryptionKey' => strlen($this->serverEncryptionKey),
            'serverIV' => strlen($this->serverIV),
        ];
    }
}
