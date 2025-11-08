<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * RSA padding schemes used in OPC UA
 */
enum RsaPadding
{
    /**
     * PKCS#1 v1.5 padding (legacy, used in Basic128Rsa15, Basic256)
     */
    case PKCS1;

    /**
     * OAEP with SHA-1 (used in Basic256Sha256 asymmetric encryption)
     */
    case OAEP;

    /**
     * OAEP with SHA-256 (used in Aes128_Sha256_RsaOaep)
     */
    case OAEP_SHA256;
}
