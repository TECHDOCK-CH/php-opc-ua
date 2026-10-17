<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use ArrayAccess;
use DateTimeInterface;
use Exception;
use phpseclib4\Crypt\RSA;
use phpseclib4\File\ASN1;
use phpseclib4\File\ASN1\Maps\HashAlgorithm;
use phpseclib4\File\ASN1\Maps\RSASSA_PSS_params;
use phpseclib4\File\X509;

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
    /** Upper bound for issuer lookups, protects against issuer cycles in the trust store */
    private const MAX_CHAIN_DEPTH = 10;

    /** Hash algorithms accepted in certificate signatures */
    private const SIGNATURE_HASHES = ['sha1', 'sha224', 'sha256', 'sha384', 'sha512'];

    public function __construct(
        private readonly TrustStore $trustStore,
        private readonly bool $checkExpiration = true,
        private readonly bool $checkChain = true,
    ) {
    }

    /**
     * Validate a certificate
     *
     * @param string $certificateDer DER-encoded certificate
     * @throws CertificateValidationException if validation fails
     */
    public function validate(string $certificateDer): void
    {
        $cert = self::loadCertificate($certificateDer);

        // Check expiration
        if ($this->checkExpiration) {
            $this->validateExpiration($cert);
        }

        // Check trust
        $this->validateTrust($certificateDer);

        // Check chain (if enabled and issuer is not self)
        if ($this->checkChain && !$this->isSelfSigned($cert)) {
            $this->validateChain($cert, 0);
        }
    }

    /**
     * Load and decode a certificate (DER or PEM)
     *
     * phpseclib 4 decodes lazily, so the fields used by this class are touched here
     * to surface parse errors as CertificateValidationException.
     *
     * @throws CertificateValidationException
     */
    private static function loadCertificate(string $certificate, string $error = 'Failed to parse certificate'): X509
    {
        try {
            $cert = X509::load($certificate);
            $cert->getSubjectDN(X509::DN_ARRAY);
            $cert->getIssuerDN(X509::DN_ARRAY);
            $hasValidity = isset($cert['tbsCertificate']['validity']['notBefore'], $cert['tbsCertificate']['validity']['notAfter']);
        } catch (Exception $e) {
            throw new CertificateValidationException($error . ': ' . $e->getMessage(), 0, $e);
        }

        if (!$hasValidity) {
            throw new CertificateValidationException($error . ': missing validity');
        }

        return $cert;
    }

    /**
     * Validate certificate is not expired
     *
     * @throws CertificateValidationException
     */
    private function validateExpiration(X509 $cert): void
    {
        $now = time();

        // Check notBefore
        $notBeforeTime = $this->parseTime($cert['tbsCertificate']['validity']['notBefore']);

        if ($now < $notBeforeTime) {
            throw new CertificateValidationException(
                'Certificate not yet valid (notBefore: ' . date('Y-m-d H:i:s', $notBeforeTime) . ')'
            );
        }

        // Check notAfter
        $notAfterTime = $this->parseTime($cert['tbsCertificate']['validity']['notAfter']);

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
     * Looks up the issuer by subject DN in the trust store, verifies the certificate
     * signature with the issuer's public key and continues up to a self-signed issuer.
     *
     * @throws CertificateValidationException
     */
    private function validateChain(X509 $cert, int $depth): void
    {
        if ($depth >= self::MAX_CHAIN_DEPTH) {
            throw new CertificateValidationException('Certificate chain exceeds maximum depth of ' . self::MAX_CHAIN_DEPTH);
        }

        // Try to find issuer certificate in trust store
        $issuerDer = $this->trustStore->findBySubject(self::dnArray($cert->getIssuerDN(X509::DN_ARRAY)));

        if ($issuerDer === null) {
            throw new CertificateValidationException(
                'Certificate chain incomplete: issuer certificate not found in trust store'
            );
        }

        $issuer = self::loadCertificate($issuerDer, 'Failed to parse issuer certificate');

        // Verify signature using issuer's public key
        if (!$this->verifySignature($cert, $issuer)) {
            throw new CertificateValidationException('Certificate signature validation failed');
        }

        // Recursively validate issuer (unless self-signed)
        if (!$this->isSelfSigned($issuer)) {
            $this->validateChain($issuer, $depth + 1);
        }
    }

    /**
     * Verify that $cert was signed by the key of $issuer
     *
     * phpseclib 4 only validates signatures against its global CA store
     * (X509::addCA()), so the signature is checked here directly against the issuer key.
     *
     * @throws CertificateValidationException
     */
    private function verifySignature(X509 $cert, X509 $issuer): bool
    {
        try {
            $key = $issuer->getPublicKey();
        } catch (Exception $e) {
            throw new CertificateValidationException('Failed to extract issuer public key', 0, $e);
        }

        if (!$key instanceof RSA\PublicKey) {
            throw new CertificateValidationException('Unsupported issuer key type: only RSA is supported');
        }

        try {
            $algorithm = (string) $cert['signatureAlgorithm']['algorithm'];

            if (preg_match('/^(sha\d+)WithRSAEncryption$/', $algorithm, $match) === 1) {
                if (!in_array($match[1], self::SIGNATURE_HASHES, true)) {
                    throw new CertificateValidationException("Unsupported signature algorithm: $algorithm");
                }
                $key = $key->withPadding(RSA::SIGNATURE_PKCS1)->withHash($match[1]);
            } elseif ($algorithm === 'id-RSASSA-PSS') {
                [$hash, $mgfHash, $saltLength] = $this->parsePssParameters($cert['signatureAlgorithm']['parameters'] ?? null);
                $key = $key
                    ->withPadding(RSA::SIGNATURE_PSS)
                    ->withHash($hash)
                    ->withMGFHash($mgfHash)
                    ->withSaltLength($saltLength);
            } else {
                throw new CertificateValidationException("Unsupported signature algorithm: $algorithm");
            }

            // The BIT STRING value starts with the "unused bits" octet
            $signature = substr((string) $cert['signature'], 1);

            return $key->verify($cert->getSignableSection(), $signature);
        } catch (CertificateValidationException $e) {
            throw $e;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Decode RSASSA-PSS-params (RFC 4055), applying the RFC defaults (SHA-1, MGF1-SHA-1, salt 20)
     *
     * @return array{string, string, int} hash, MGF1 hash, salt length
     * @throws CertificateValidationException
     */
    private function parsePssParameters(mixed $parameters): array
    {
        $hash = 'sha1';
        $mgfHash = 'sha1';
        $saltLength = 20;

        if ($parameters !== null) {
            $decoded = ASN1::decodeBER((string) $parameters);
            $params = ASN1::map($decoded, RSASSA_PSS_params::MAP);
            if (!$params instanceof ArrayAccess) {
                throw new CertificateValidationException('Invalid RSASSA-PSS parameters');
            }

            if (isset($params['hashAlgorithm']['algorithm'])) {
                $hash = (string) $params['hashAlgorithm']['algorithm'];
            }
            if (isset($params['maskGenAlgorithm']['parameters'])) {
                $mgfDecoded = ASN1::decodeBER((string) $params['maskGenAlgorithm']['parameters']);
                $mgfParams = ASN1::map($mgfDecoded, HashAlgorithm::MAP);
                if (!$mgfParams instanceof ArrayAccess) {
                    throw new CertificateValidationException('Invalid RSASSA-PSS MGF parameters');
                }
                $mgfHash = (string) $mgfParams['algorithm'];
            }
            if (isset($params['saltLength'])) {
                $saltLength = (int) (string) $params['saltLength'];
            }
        }

        $hash = str_replace('id-', '', $hash);
        $mgfHash = str_replace('id-', '', $mgfHash);

        if (!in_array($hash, self::SIGNATURE_HASHES, true) || !in_array($mgfHash, self::SIGNATURE_HASHES, true)) {
            throw new CertificateValidationException("Unsupported RSASSA-PSS hash: $hash / $mgfHash");
        }

        return [$hash, $mgfHash, $saltLength];
    }

    /**
     * Check if certificate is self-signed (subject DN equals issuer DN)
     */
    private function isSelfSigned(X509 $cert): bool
    {
        return DistinguishedName::canonical(self::dnArray($cert->getSubjectDN(X509::DN_ARRAY)))
            === DistinguishedName::canonical(self::dnArray($cert->getIssuerDN(X509::DN_ARRAY)));
    }

    /**
     * Narrow X509::getSubjectDN()/getIssuerDN() output (array|string) for DN_ARRAY
     *
     * @param array<int|string, mixed>|string $dn
     * @return array<int|string, mixed>
     * @throws CertificateValidationException
     */
    private static function dnArray(array|string $dn): array
    {
        if (!is_array($dn)) {
            throw new CertificateValidationException('Failed to read DN');
        }
        return $dn;
    }

    /**
     * Parse time from certificate validity field
     *
     * phpseclib 4 returns a Choice with 'utcTime' or 'generalTime', each a DateTime.
     */
    private function parseTime(mixed $timeField): int
    {
        $time = $timeField['utcTime'] ?? $timeField['generalTime'] ?? null;

        if ($time instanceof DateTimeInterface) {
            return $time->getTimestamp();
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
     * @return array<int|string, mixed> DN in phpseclib's X509::DN_ARRAY format (['rdnSequence' => ...])
     */
    public function getSubject(string $certificateDer): array
    {
        return self::dnArray(self::loadCertificate($certificateDer)->getSubjectDN(X509::DN_ARRAY));
    }

    /**
     * Get certificate issuer
     *
     * @return array<int|string, mixed> DN in phpseclib's X509::DN_ARRAY format (['rdnSequence' => ...])
     */
    public function getIssuer(string $certificateDer): array
    {
        return self::dnArray(self::loadCertificate($certificateDer)->getIssuerDN(X509::DN_ARRAY));
    }

    /**
     * Get human-readable subject string, e.g. "CN=localhost, O=Example"
     */
    public function getSubjectString(string $certificateDer): string
    {
        return $this->dnToString(self::loadCertificate($certificateDer)->getSubjectDN(X509::DN_ARRAY));
    }

    /**
     * Convert DN to human-readable string
     *
     * @param array<int|string, mixed>|string $dn DN in X509::DN_ARRAY format
     */
    private function dnToString(array|string $dn): string
    {
        try {
            return DistinguishedName::toString(self::dnArray($dn));
        } catch (\RuntimeException $e) {
            throw new CertificateValidationException('Failed to format DN as string', 0, $e);
        }
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

        $cert = self::loadCertificate($certificateDer);

        $validity = $cert['tbsCertificate']['validity'];

        return [
            'subject' => $this->dnToString($cert->getSubjectDN(X509::DN_ARRAY)),
            'issuer' => $this->dnToString($cert->getIssuerDN(X509::DN_ARRAY)),
            'thumbprint' => $this->getThumbprint($certificateDer),
            'notBefore' => $this->parseTime($validity['notBefore']),
            'notAfter' => $this->parseTime($validity['notAfter']),
            'serialNumber' => (string) $cert['tbsCertificate']['serialNumber'], // decimal
            'selfSigned' => $this->isSelfSigned($cert),
        ];
    }
}
