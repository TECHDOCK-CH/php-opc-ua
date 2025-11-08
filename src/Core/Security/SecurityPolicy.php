<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * OPC UA Security Policies
 *
 * Each policy defines the cryptographic algorithms used for:
 * - Asymmetric encryption (RSA)
 * - Symmetric encryption (AES)
 * - Signing (SHA)
 */
enum SecurityPolicy: string
{
    /**
     * No security - plaintext communication
     */
    case None = 'http://opcfoundation.org/UA/SecurityPolicy#None';

    /**
     * Basic256Sha256 - RSA-2048, AES-256-CBC, SHA-256
     * Recommended for most applications
     */
    case Basic256Sha256 = 'http://opcfoundation.org/UA/SecurityPolicy#Basic256Sha256';

    /**
     * Basic128Rsa15 - RSA-1024, AES-128-CBC, SHA-1
     * @deprecated Legacy policy, weak security
     */
    case Basic128Rsa15 = 'http://opcfoundation.org/UA/SecurityPolicy#Basic128Rsa15';

    /**
     * Basic256 - RSA-2048, AES-256-CBC, SHA-1
     * @deprecated Legacy policy
     */
    case Basic256 = 'http://opcfoundation.org/UA/SecurityPolicy#Basic256';

    /**
     * Aes128_Sha256_RsaOaep - RSA-2048 OAEP, AES-128-CBC, SHA-256
     */
    case Aes128Sha256RsaOaep = 'http://opcfoundation.org/UA/SecurityPolicy#Aes128_Sha256_RsaOaep';

    /**
     * Aes256_Sha256_RsaPss - RSA-2048 PSS, AES-256-CBC, SHA-256
     * Strongest available policy
     */
    case Aes256Sha256RsaPss = 'http://opcfoundation.org/UA/SecurityPolicy#Aes256_Sha256_RsaPss';

    /**
     * Check if this policy requires encryption
     */
    public function requiresEncryption(): bool
    {
        return $this !== self::None;
    }

    /**
     * Get the URI string (alias for value)
     */
    public function getUri(): string
    {
        return $this->value;
    }

    /**
     * Get the URI string
     */
    public function uri(): string
    {
        return $this->value;
    }

    /**
     * Create from URI string
     */
    public static function fromUri(string $uri): self
    {
        return self::from($uri);
    }
}
