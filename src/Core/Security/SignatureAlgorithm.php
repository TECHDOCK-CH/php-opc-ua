<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * Signature algorithms used in OPC UA
 */
enum SignatureAlgorithm: string
{
    /**
     * RSA-SHA1 with PKCS#1 v1.5 padding (legacy)
     */
    case RSA_SHA1_PKCS1 = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';

    /**
     * RSA-SHA256 with PKCS#1 v1.5 padding (Basic256Sha256)
     */
    case RSA_SHA256_PKCS1 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    /**
     * RSA-SHA256 with PSS padding (Aes256_Sha256_RsaPss)
     */
    case RSA_SHA256_PSS = 'http://opcfoundation.org/UA/security/rsa-pss-sha2-256';

    /**
     * Get signature length in bytes for a given key size
     */
    public function getSignatureLength(int $keySizeInBytes): int
    {
        // For RSA, signature length equals key size
        return $keySizeInBytes;
    }
}
