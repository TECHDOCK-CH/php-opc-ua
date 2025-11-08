<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * Asymmetric Security Header
 *
 * Used in OpenSecureChannel (OPN) messages
 *
 * Structure (after message type and size):
 * - SecureChannelId (UInt32) - 0 for initial OPN request
 * - SecurityPolicyUri (String)
 * - SenderCertificate (ByteString) - DER encoded X.509 certificate, null for None
 * - ReceiverCertificateThumbprint (ByteString) - SHA-1 hash of receiver cert, null for None
 */
final readonly class AsymmetricSecurityHeader
{
    public function __construct(
        public int $secureChannelId,
        public SecurityPolicy $securityPolicy,
        public ?string $senderCertificate = null,
        public ?string $receiverCertificateThumbprint = null,
    ) {
    }

    /**
     * Create header for None security policy with initial channel ID
     */
    public static function none(): self
    {
        return new self(0, SecurityPolicy::None, null, null);
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->secureChannelId);
        $encoder->writeString($this->securityPolicy->value);
        $encoder->writeByteString($this->senderCertificate);
        $encoder->writeByteString($this->receiverCertificateThumbprint);
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        $secureChannelId = $decoder->readUInt32();
        $policyUri = $decoder->readString() ?? '';
        $securityPolicy = SecurityPolicy::from($policyUri);
        $senderCertificate = $decoder->readByteString();
        $receiverCertificateThumbprint = $decoder->readByteString();

        return new self($secureChannelId, $securityPolicy, $senderCertificate, $receiverCertificateThumbprint);
    }
}
