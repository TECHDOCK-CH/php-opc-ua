<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Security;

use TechDock\OpcUa\Core\Security\CertificateValidationException;
use TechDock\OpcUa\Core\Security\CertificateValidator;
use TechDock\OpcUa\Core\Security\TrustStore;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CertificateValidator::class)]
#[CoversClass(TrustStore::class)]
final class CertificateValidatorTest extends TestCase
{
    private function generateSelfSignedCertificate(): string
    {
        $privateKey = RSA::createKey(2048);
        $publicKey = $privateKey->getPublicKey();

        $x509 = new X509();
        $x509->setPrivateKey($privateKey);
        $x509->setPublicKey($publicKey);

        $x509->setStartDate('-1 day');
        $x509->setEndDate('+1 year');

        $x509->setSerialNumber(chr(1), 256);

        $x509->setDNProp('CN', 'Test Certificate');
        $x509->setDNProp('O', 'Test Organization');

        $x509->setDomain('localhost');

        $result = $x509->sign($x509, $x509);

        if ($result === false) {
            throw new RuntimeException('Failed to sign certificate');
        }

        return $x509->saveX509($result);
    }

    private function generateExpiredCertificate(): string
    {
        $privateKey = RSA::createKey(2048);
        $publicKey = $privateKey->getPublicKey();

        $x509 = new X509();
        $x509->setPrivateKey($privateKey);
        $x509->setPublicKey($publicKey);

        $x509->setStartDate('-2 years');
        $x509->setEndDate('-1 year'); // Expired

        $x509->setSerialNumber(chr(2), 256);

        $x509->setDNProp('CN', 'Expired Certificate');

        $result = $x509->sign($x509, $x509);

        return $x509->saveX509($result);
    }

    public function testValidateTrustedCertificate(): void
    {
        $certDer = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($certDer);

        $validator = new CertificateValidator($trustStore);
        $validator->validate($certDer); // Should not throw

        $this->assertTrue(true); // If we got here, validation passed
    }

    public function testValidateUntrustedCertificate(): void
    {
        $certDer = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();
        // Don't add certificate to trust store

        $validator = new CertificateValidator($trustStore);

        $this->expectException(CertificateValidationException::class);
        $this->expectExceptionMessage('not trusted');

        $validator->validate($certDer);
    }

    public function testValidateExpiredCertificate(): void
    {
        $certDer = $this->generateExpiredCertificate();

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($certDer);

        $validator = new CertificateValidator($trustStore);

        $this->expectException(CertificateValidationException::class);
        $this->expectExceptionMessage('expired');

        $validator->validate($certDer);
    }

    public function testValidateWithExpirationCheckDisabled(): void
    {
        $certDer = $this->generateExpiredCertificate();

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($certDer);

        $validator = new CertificateValidator(
            $trustStore,
            checkExpiration: false
        );

        $validator->validate($certDer); // Should not throw

        $this->assertTrue(true);
    }

    public function testAutoAcceptMode(): void
    {
        $certDer = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();
        $trustStore->enableAutoAccept();

        $validator = new CertificateValidator($trustStore);

        $validator->validate($certDer); // Should not throw even if not in trust store

        $this->assertTrue(true);
    }

    public function testGetThumbprint(): void
    {
        $certDer = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();
        $validator = new CertificateValidator($trustStore);

        $thumbprint = $validator->getThumbprint($certDer);

        $this->assertNotEmpty($thumbprint);
        $this->assertEquals(40, strlen($thumbprint)); // SHA-1 is 40 hex chars
        $this->assertMatchesRegularExpression('/^[0-9A-F]+$/', $thumbprint);
    }

    public function testGetSubjectString(): void
    {
        $certDer = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();
        $validator = new CertificateValidator($trustStore);

        $subject = $validator->getSubjectString($certDer);

        // The cert has CN=localhost (from setDomain) and O=Test Organization
        $this->assertStringContainsString('CN=localhost', $subject);
        $this->assertStringContainsString('O=Test Organization', $subject);
    }

    public function testValidateAndGetInfo(): void
    {
        $certDer = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($certDer);

        $validator = new CertificateValidator($trustStore);

        $info = $validator->validateAndGetInfo($certDer);

        $this->assertArrayHasKey('subject', $info);
        $this->assertArrayHasKey('issuer', $info);
        $this->assertArrayHasKey('thumbprint', $info);
        $this->assertArrayHasKey('notBefore', $info);
        $this->assertArrayHasKey('notAfter', $info);
        $this->assertArrayHasKey('serialNumber', $info);
        $this->assertArrayHasKey('selfSigned', $info);

        $this->assertTrue($info['selfSigned']); // Self-signed cert
        $this->assertIsInt($info['notBefore']);
        $this->assertIsInt($info['notAfter']);
    }

    public function testTrustStoreCount(): void
    {
        $cert1 = $this->generateSelfSignedCertificate();
        $cert2 = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();

        $this->assertEquals(0, $trustStore->count());

        $trustStore->addTrustedCertificate($cert1);
        $this->assertEquals(1, $trustStore->count());

        $trustStore->addTrustedCertificate($cert2);
        $this->assertEquals(2, $trustStore->count());
    }

    public function testTrustStoreRemove(): void
    {
        $certDer = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($certDer);

        $validator = new CertificateValidator($trustStore);
        $thumbprint = $validator->getThumbprint($certDer);

        $this->assertTrue($trustStore->isTrusted($certDer));

        $trustStore->removeTrustedCertificate($thumbprint);

        $this->assertFalse($trustStore->isTrusted($certDer));
    }

    public function testTrustStoreClear(): void
    {
        $cert1 = $this->generateSelfSignedCertificate();
        $cert2 = $this->generateSelfSignedCertificate();

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($cert1);
        $trustStore->addTrustedCertificate($cert2);

        $this->assertEquals(2, $trustStore->count());

        $trustStore->clear();

        $this->assertEquals(0, $trustStore->count());
        $this->assertFalse($trustStore->isTrusted($cert1));
        $this->assertFalse($trustStore->isTrusted($cert2));
    }
}
