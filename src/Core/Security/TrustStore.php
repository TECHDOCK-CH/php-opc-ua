<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use phpseclib3\File\X509;
use RuntimeException;

/**
 * Certificate Trust Store
 *
 * Manages trusted certificates for OPC UA connections.
 * Certificates can be stored in memory or persisted to disk.
 */
final class TrustStore
{
    /** @var array<string, string> Map of thumbprint => DER certificate */
    private array $trustedCertificates = [];

    /** @var array<string, string> Map of DN hash => DER certificate (for lookups) */
    private array $subjectIndex = [];

    private readonly X509 $x509;
    private bool $autoAccept = false;

    public function __construct(
        private readonly ?string $persistPath = null,
    ) {
        $this->x509 = new X509();

        // Load certificates from disk if path provided
        if ($this->persistPath !== null && is_dir($this->persistPath)) {
            $this->loadFromDisk();
        }
    }

    /**
     * Enable auto-accept mode (automatically trust all certificates)
     *
     * WARNING: Only use in development! Disables security.
     */
    public function enableAutoAccept(): void
    {
        $this->autoAccept = true;
    }

    /**
     * Disable auto-accept mode
     */
    public function disableAutoAccept(): void
    {
        $this->autoAccept = false;
    }

    /**
     * Check if auto-accept is enabled
     */
    public function isAutoAcceptEnabled(): bool
    {
        return $this->autoAccept;
    }

    /**
     * Add a trusted certificate
     *
     * @param string $certificateDer DER-encoded certificate
     * @param bool $persist Save to disk (if persist path configured)
     */
    public function addTrustedCertificate(string $certificateDer, bool $persist = true): void
    {
        $thumbprint = $this->getThumbprint($certificateDer);

        // Add to memory store
        $this->trustedCertificates[$thumbprint] = $certificateDer;

        // Index by subject for chain validation
        $subjectHash = $this->getSubjectHash($certificateDer);
        $this->subjectIndex[$subjectHash] = $certificateDer;

        // Persist to disk if enabled
        if ($persist && $this->persistPath !== null) {
            $this->saveToDisk($thumbprint, $certificateDer);
        }
    }

    /**
     * Remove a trusted certificate
     *
     * @param string $thumbprint SHA-1 thumbprint (hex string)
     */
    public function removeTrustedCertificate(string $thumbprint): void
    {
        $thumbprint = strtoupper($thumbprint);

        if (isset($this->trustedCertificates[$thumbprint])) {
            $cert = $this->trustedCertificates[$thumbprint];

            // Remove from memory
            unset($this->trustedCertificates[$thumbprint]);

            // Remove from subject index
            $subjectHash = $this->getSubjectHash($cert);
            unset($this->subjectIndex[$subjectHash]);

            // Remove from disk
            if ($this->persistPath !== null) {
                $filePath = $this->persistPath . '/' . $thumbprint . '.der';
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
    }

    /**
     * Check if a certificate is trusted
     *
     * @param string $certificateDer DER-encoded certificate
     */
    public function isTrusted(string $certificateDer): bool
    {
        // Auto-accept mode trusts everything
        if ($this->autoAccept) {
            return true;
        }

        $thumbprint = $this->getThumbprint($certificateDer);
        return isset($this->trustedCertificates[$thumbprint]);
    }

    /**
     * Find certificate by subject DN
     *
     * @param array<string, string> $subject Subject DN components
     * @return string|null DER certificate or null if not found
     */
    public function findBySubject(array $subject): ?string
    {
        $subjectHash = $this->hashDN($subject);
        return $this->subjectIndex[$subjectHash] ?? null;
    }

    /**
     * Get all trusted certificates
     *
     * @return array<string, string> Map of thumbprint => DER certificate
     */
    public function getTrustedCertificates(): array
    {
        return $this->trustedCertificates;
    }

    /**
     * Get number of trusted certificates
     */
    public function count(): int
    {
        return count($this->trustedCertificates);
    }

    /**
     * Clear all trusted certificates
     */
    public function clear(): void
    {
        $this->trustedCertificates = [];
        $this->subjectIndex = [];

        // Clear disk store
        if ($this->persistPath !== null && is_dir($this->persistPath)) {
            $files = glob($this->persistPath . '/*.der');
            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Get certificate thumbprint (SHA-1 hash)
     */
    private function getThumbprint(string $certificateDer): string
    {
        return strtoupper(hash('sha1', $certificateDer));
    }

    /**
     * Get subject hash for indexing
     */
    private function getSubjectHash(string $certificateDer): string
    {
        $cert = $this->x509->loadX509($certificateDer);

        if ($cert === false) {
            throw new RuntimeException('Failed to parse certificate');
        }

        $subject = $cert['tbsCertificate']['subject'];
        return $this->hashDN($subject);
    }

    /**
     * Hash a DN for indexing
     *
     * @param array<int|string, mixed> $dn Distinguished Name array
     */
    private function hashDN(array $dn): string
    {
        // Normalize DN and hash it
        $parts = [];

        foreach ($dn as $rdn) {
            foreach ($rdn as $attr) {
                $type = $attr['type'] ?? '';
                $value = $attr['value'] ?? '';
                $parts[] = strtoupper($type) . '=' . trim($value);
            }
        }

        sort($parts);
        return hash('sha256', implode(',', $parts));
    }

    /**
     * Load certificates from disk
     */
    private function loadFromDisk(): void
    {
        if ($this->persistPath === null || !is_dir($this->persistPath)) {
            return;
        }

        $files = glob($this->persistPath . '/*.der');

        if ($files !== false) {
            foreach ($files as $file) {
                $der = file_get_contents($file);
                if ($der !== false) {
                    $this->addTrustedCertificate($der, persist: false);
                }
            }
        }
    }

    /**
     * Save certificate to disk
     */
    private function saveToDisk(string $thumbprint, string $certificateDer): void
    {
        if ($this->persistPath === null) {
            return;
        }

        // Create directory if it doesn't exist
        if (!is_dir($this->persistPath)) {
            mkdir($this->persistPath, 0700, true);
        }

        $filePath = $this->persistPath . '/' . $thumbprint . '.der';
        file_put_contents($filePath, $certificateDer);
        chmod($filePath, 0600);
    }

    /**
     * Create a trust store from PEM file
     */
    public static function fromPemFile(string $pemPath): self
    {
        $trustStore = new self();

        $pemData = file_get_contents($pemPath);
        if ($pemData === false) {
            throw new RuntimeException("Failed to read PEM file: $pemPath");
        }

        // Parse PEM and extract all certificates
        $x509 = new X509();
        $certs = $x509->loadX509($pemData);

        // Handle single cert vs cert chain
        if (!is_array($certs) || !isset($certs[0])) {
            $certs = [$certs];
        }

        foreach ($certs as $cert) {
            if (is_array($cert)) {
                $der = $x509->saveX509($cert);
                $trustStore->addTrustedCertificate($der, persist: false);
            }
        }

        return $trustStore;
    }

    /**
     * Create a trust store from directory of PEM/DER files
     */
    public static function fromDirectory(string $dirPath): self
    {
        if (!is_dir($dirPath)) {
            throw new RuntimeException("Directory does not exist: $dirPath");
        }

        $trustStore = new self();
        $x509 = new X509();

        // Load .pem files
        $pemFiles = glob($dirPath . '/*.pem');
        if ($pemFiles !== false) {
            foreach ($pemFiles as $file) {
                $pemData = file_get_contents($file);
                if ($pemData !== false) {
                    $cert = $x509->loadX509($pemData);
                    if (is_array($cert)) {
                        $der = $x509->saveX509($cert);
                        $trustStore->addTrustedCertificate($der, persist: false);
                    }
                }
            }
        }

        // Load .der files
        $derFiles = glob($dirPath . '/*.der');
        if ($derFiles !== false) {
            foreach ($derFiles as $file) {
                $der = file_get_contents($file);
                if ($der !== false) {
                    $trustStore->addTrustedCertificate($der, persist: false);
                }
            }
        }

        // Load .crt files (usually PEM)
        $crtFiles = glob($dirPath . '/*.crt');
        if ($crtFiles !== false) {
            foreach ($crtFiles as $file) {
                $data = file_get_contents($file);
                if ($data !== false) {
                    $cert = $x509->loadX509($data);
                    if (is_array($cert)) {
                        $der = $x509->saveX509($cert);
                        $trustStore->addTrustedCertificate($der, persist: false);
                    }
                }
            }
        }

        return $trustStore;
    }
}
