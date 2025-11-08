<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * X.509 certificate identity token
 *
 * OPC UA Part 4 - Section 7.36.5
 */
final class X509IdentityToken implements IEncodeable
{
    public function __construct(
        public readonly string $policyId,
        /** @var string|null DER-encoded X.509 certificate */
        public readonly ?string $certificateData = null,
    ) {
    }

    /**
     * Create from PEM-encoded certificate
     *
     * @param string $policyId Policy ID from server endpoint
     * @param string $certificatePem PEM-encoded certificate
     * @return self
     * @throws RuntimeException
     */
    public static function fromPem(string $policyId, string $certificatePem): self
    {
        // Extract DER data from PEM
        $certificateData = self::pemToDer($certificatePem);

        return new self(
            policyId: $policyId,
            certificateData: $certificateData,
        );
    }

    /**
     * Create from DER-encoded certificate
     */
    public static function fromDer(string $policyId, string $certificateDer): self
    {
        return new self(
            policyId: $policyId,
            certificateData: $certificateDer,
        );
    }

    /**
     * Convert PEM to DER format
     */
    private static function pemToDer(string $pem): string
    {
        // Remove PEM headers and decode base64
        $pem = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $pem);
        if ($pem === null) {
            throw new RuntimeException('Failed to process certificate: preg_replace returned null');
        }
        $pem = preg_replace('/-----END CERTIFICATE-----/', '', $pem);
        if ($pem === null) {
            throw new RuntimeException('Failed to process certificate: preg_replace returned null');
        }
        $pem = preg_replace('/\s+/', '', $pem);
        if ($pem === null) {
            throw new RuntimeException('Failed to process certificate: preg_replace returned null');
        }

        $der = base64_decode($pem, true);
        if ($der === false) {
            throw new RuntimeException('Failed to decode certificate from PEM format');
        }

        return $der;
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, 327); // X509IdentityToken
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // TypeId is encoded by caller (ExtensionObject)

        // PolicyId
        $encoder->writeString($this->policyId);

        // CertificateData
        $encoder->writeByteString($this->certificateData);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // PolicyId
        $policyId = $decoder->readString();

        // CertificateData
        $certificateData = $decoder->readByteString();

        return new self(
            policyId: $policyId ?? '',
            certificateData: $certificateData,
        );
    }
}
