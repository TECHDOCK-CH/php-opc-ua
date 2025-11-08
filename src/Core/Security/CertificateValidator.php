<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use phpseclib3\File\X509;

/**
 * Certificate Validator
 *
 * Validates X.509 certificates according to OPC UA security requirements:
 * - Certificate chain verification
 * - Expiration checking
 * - Signature validation
 * - Trust store verification
 */
final class CertificateValidator
{
    private readonly X509 $x509;

    public function __construct(
        private readonly TrustStore $trustStore,
        private readonly bool $checkExpiration = true,
        private readonly bool $checkChain = true,
    ) {
        $this->x509 = new X509();
    }

    /**
     * Validate a certificate
     *
     * @param string $certificateDer DER-encoded certificate
     * @throws CertificateValidationException if validation fails
     */
    public function validate(string $certificateDer): void
    {
        // Load certificate
        $cert = $this->x509->loadX509($certificateDer);

        if ($cert === false) {
            throw new CertificateValidationException('Failed to parse certificate');
        }

        // Check expiration
        if ($this->checkExpiration) {
            $this->validateExpiration($cert);
        }

        // Check trust
        $this->validateTrust($certificateDer);

        // Check chain (if enabled and issuer is not self)
        if ($this->checkChain && !$this->isSelfSigned($cert)) {
            $this->validateChain($cert);
        }
    }

    /**
     * Validate certificate is not expired
     *
     * @param array<string, mixed> $cert
     * @throws CertificateValidationException
     */
    private function validateExpiration(array $cert): void
    {
        $now = time();

        // Check notBefore
        $notBefore = $cert['tbsCertificate']['validity']['notBefore'];
        $notBeforeTime = $this->parseTime($notBefore);

        if ($now < $notBeforeTime) {
            throw new CertificateValidationException(
                'Certificate not yet valid (notBefore: ' . date('Y-m-d H:i:s', $notBeforeTime) . ')'
            );
        }

        // Check notAfter
        $notAfter = $cert['tbsCertificate']['validity']['notAfter'];
        $notAfterTime = $this->parseTime($notAfter);

        if ($now > $notAfterTime) {
            throw new CertificateValidationException(
                'Certificate expired (notAfter: ' . date('Y-m-d H:i:s', $notAfterTime) . ')'
            );
        }
    }

    /**
     * Validate certificate is trusted
     *
     * @throws CertificateValidationException
     */
    private function validateTrust(string $certificateDer): void
    {
        if (!$this->trustStore->isTrusted($certificateDer)) {
            throw new CertificateValidationException(
                'Certificate not trusted. Add to trust store or enable auto-accept.'
            );
        }
    }

    /**
     * Validate certificate chain
     *
     * @param array<string, mixed> $cert
     * @throws CertificateValidationException
     */
    private function validateChain(array $cert): void
    {
        // Get issuer
        $issuer = $cert['tbsCertificate']['issuer'];

        // Try to find issuer certificate in trust store
        $issuerCert = $this->trustStore->findBySubject($issuer);

        if ($issuerCert === null) {
            throw new CertificateValidationException(
                'Certificate chain incomplete: issuer certificate not found in trust store'
            );
        }

        // Verify signature using issuer's public key
        $issuerX509 = new X509();
        $issuerCertData = $issuerX509->loadX509($issuerCert);

        if ($issuerCertData === false) {
            throw new CertificateValidationException('Failed to parse issuer certificate');
        }

        $publicKey = $issuerX509->getPublicKey();

        if ($publicKey === false) {
            throw new CertificateValidationException('Failed to extract issuer public key');
        }

        // Verify signature
        $this->x509->loadX509($cert);
        $this->x509->setPublicKey($publicKey);

        $signatureValid = $this->x509->validateSignature();
        if ($signatureValid !== true) {
            throw new CertificateValidationException('Certificate signature validation failed');
        }

        // Recursively validate issuer (unless self-signed)
        if (!$this->isSelfSigned($issuerCertData)) {
            $this->validateChain($issuerCertData);
        }
    }

    /**
     * Check if certificate is self-signed
     *
     * @param array<string, mixed> $cert
     */
    private function isSelfSigned(array $cert): bool
    {
        $subject = $cert['tbsCertificate']['subject'];
        $issuer = $cert['tbsCertificate']['issuer'];

        return $this->dnEquals($subject, $issuer);
    }

    /**
     * Compare two Distinguished Names for equality
     *
     * @param array<int|string, mixed> $dn1
     * @param array<int|string, mixed> $dn2
     */
    private function dnEquals(array $dn1, array $dn2): bool
    {
        // Normalize and compare DN components
        $norm1 = $this->normalizeDN($dn1);
        $norm2 = $this->normalizeDN($dn2);

        return $norm1 === $norm2;
    }

    /**
     * Normalize DN for comparison
     *
     * @param array<int|string, mixed> $dn
     */
    private function normalizeDN(array $dn): string
    {
        $parts = [];

        foreach ($dn as $rdn) {
            foreach ($rdn as $attr) {
                $type = $attr['type'] ?? '';
                $value = $attr['value'] ?? '';

                // Normalize: uppercase type, trim value
                $parts[] = strtoupper($type) . '=' . trim($value);
            }
        }

        sort($parts);
        return implode(',', $parts);
    }

    /**
     * Parse time from certificate validity field
     *
     * @param array<string, mixed> $timeField
     */
    private function parseTime(array $timeField): int
    {
        // phpseclib returns time as array with 'utcTime' or 'generalTime'
        if (isset($timeField['utcTime'])) {
            return strtotime($timeField['utcTime']);
        }

        if (isset($timeField['generalTime'])) {
            return strtotime($timeField['generalTime']);
        }

        throw new CertificateValidationException('Invalid time format in certificate');
    }

    /**
     * Get certificate thumbprint (SHA-1 hash)
     */
    public function getThumbprint(string $certificateDer): string
    {
        return strtoupper(hash('sha1', $certificateDer));
    }

    /**
     * Get certificate subject
     *
     * @return array<int|string, mixed> DN components
     */
    public function getSubject(string $certificateDer): array
    {
        $cert = $this->x509->loadX509($certificateDer);

        if ($cert === false) {
            throw new CertificateValidationException('Failed to parse certificate');
        }

        return $cert['tbsCertificate']['subject'];
    }

    /**
     * Get certificate issuer
     *
     * @return array<int|string, mixed> DN components
     */
    public function getIssuer(string $certificateDer): array
    {
        $cert = $this->x509->loadX509($certificateDer);

        if ($cert === false) {
            throw new CertificateValidationException('Failed to parse certificate');
        }

        return $cert['tbsCertificate']['issuer'];
    }

    /**
     * Get human-readable subject string
     */
    public function getSubjectString(string $certificateDer): string
    {
        $cert = $this->x509->loadX509($certificateDer);

        if ($cert === false) {
            throw new CertificateValidationException('Failed to parse certificate');
        }

        // Use phpseclib's built-in DN formatting
        $dn = $this->x509->getDN(X509::DN_STRING);
        if (!is_string($dn)) {
            throw new CertificateValidationException('Failed to format DN as string');
        }
        return $dn;
    }

    /**
     * Convert DN to human-readable string
     *
     * @param array<int|string, mixed> $dn
     */
    private function dnToString(array $dn): string
    {
        $parts = [];

        // phpseclib3 returns DN as nested arrays
        // Each RDN is an array of attribute arrays
        foreach ($dn as $rdn) {
            // RDN can be a single attribute or an array of attributes
            if (is_array($rdn)) {
                foreach ($rdn as $key => $value) {
                    if (is_string($key)) {
                        // Direct key-value format (phpseclib3 style)
                        $parts[] = "$key=$value";
                    } elseif (is_array($value)) {
                        // Nested array format
                        $type = $value['type'] ?? '';
                        $val = $value['value'] ?? '';

                        if ($type !== '' && $val !== '') {
                            // Map OID to common name
                            $typeName = match ($type) {
                                '2.5.4.3' => 'CN',
                                '2.5.4.10' => 'O',
                                '2.5.4.11' => 'OU',
                                '2.5.4.6' => 'C',
                                '2.5.4.7' => 'L',
                                '2.5.4.8' => 'ST',
                                default => $type,
                            };

                            $parts[] = "$typeName=$val";
                        }
                    }
                }
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Validate and extract certificate info
     *
     * @return array<string, mixed> Certificate information
     * @throws CertificateValidationException
     */
    public function validateAndGetInfo(string $certificateDer): array
    {
        $this->validate($certificateDer);

        $cert = $this->x509->loadX509($certificateDer);

        $validity = $cert['tbsCertificate']['validity'];

        return [
            'subject' => $this->getSubjectString($certificateDer),
            'issuer' => $this->dnToString($cert['tbsCertificate']['issuer']),
            'thumbprint' => $this->getThumbprint($certificateDer),
            'notBefore' => $this->parseTime($validity['notBefore']),
            'notAfter' => $this->parseTime($validity['notAfter']),
            'serialNumber' => $cert['tbsCertificate']['serialNumber']->toString(),
            'selfSigned' => $this->isSelfSigned($cert),
        ];
    }
}
