<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Security;

use TechDock\OpcUa\Core\Security\CertificateValidationException;
use TechDock\OpcUa\Core\Security\CertificateValidator;
use TechDock\OpcUa\Core\Security\TrustStore;
use phpseclib4\Crypt\RSA;
use phpseclib4\File\X509;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CertificateValidator::class)]
#[CoversClass(TrustStore::class)]
final class CertificateValidatorTest extends TestCase
{
    private static function createKey(): RSA\PrivateKey
    {
        // OPC UA certificates use RSA with PKCS#1 v1.5 / SHA-256 signatures
        return RSA::createKey(2048)
            ->withPadding(RSA::SIGNATURE_PKCS1)
            ->withHash('sha256');
    }

    private function generateSelfSignedCertificate(): string
    {
        $privateKey = self::createKey();

        $x509 = new X509($privateKey->getPublicKey());

        $x509->setStartDate('-1 day');
        $x509->setEndDate('+1 year');

        $x509->setSerialNumber(chr(1), 256);

        // addDomains() sets CN=localhost (the subject has no CN yet) and the subjectAltName
        $x509->addDomains('localhost');
        $x509->addSubjectDNProp('id-at-organizationName', 'Test Organization');
        $x509->setIssuerDN($x509->getSubjectDN(X509::DN_ARRAY));

        $privateKey->sign($x509);

        return $x509->toString(['binary' => true]);
    }

    private function generateExpiredCertificate(): string
    {
        $privateKey = self::createKey();

        $x509 = new X509($privateKey->getPublicKey());

        $x509->setStartDate('-2 years');
        $x509->setEndDate('-1 year'); // Expired

        $x509->setSerialNumber(chr(2), 256);

        $x509->setSubjectDN(['id-at-commonName' => 'Expired Certificate']);
        $x509->setIssuerDN($x509->getSubjectDN(X509::DN_ARRAY));

        $privateKey->sign($x509);

        return $x509->toString(['binary' => true]);
    }

    /**
     * @return array{key: RSA\PrivateKey, x509: X509, der: string}
     */
    private function generateCaCertificate(string $commonName, ?RSA\PrivateKey $privateKey = null): array
    {
        $privateKey ??= self::createKey();

        $x509 = new X509($privateKey->getPublicKey());
        $x509->setStartDate('-1 day');
        $x509->setEndDate('+1 year');
        $x509->setSubjectDN(['id-at-commonName' => $commonName]);
        $x509->setIssuerDN($x509->getSubjectDN(X509::DN_ARRAY));
        $x509->makeCA();

        $privateKey->sign($x509);

        return ['key' => $privateKey, 'x509' => $x509, 'der' => $x509->toString(['binary' => true])];
    }

    private function generateCertificateSignedBy(RSA\PrivateKey $issuerKey, X509 $issuer, string $commonName): string
    {
        $x509 = new X509(self::createKey()->getPublicKey());
        $x509->setStartDate('-1 day');
        $x509->setEndDate('+1 year');
        $x509->setSubjectDN(['id-at-commonName' => $commonName]);
        $x509->copySigningX509Attributes($issuer);

        $issuerKey->sign($x509);

        return $x509->toString(['binary' => true]);
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
        $this->assertSame('1', $info['serialNumber']);
        $this->assertSame('CN=localhost, O=Test Organization', $info['subject']);
        $this->assertSame($info['subject'], $info['issuer']);
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

    public function testValidateCertificateIssuedByTrustedCa(): void
    {
        $ca = $this->generateCaCertificate('Test CA');
        $leaf = $this->generateCertificateSignedBy($ca['key'], $ca['x509'], 'Leaf');

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($ca['der']);
        $trustStore->addTrustedCertificate($leaf);

        $validator = new CertificateValidator($trustStore);
        $info = $validator->validateAndGetInfo($leaf);

        $this->assertFalse($info['selfSigned']);
        $this->assertSame('CN=Leaf', $info['subject']);
        $this->assertSame('CN=Test CA', $info['issuer']);
    }

    /**
     * @return iterable<string, array{RSA\PrivateKey}>
     */
    public static function issuerKeyProvider(): iterable
    {
        $key = RSA::createKey(2048);

        yield 'sha1WithRSAEncryption' => [$key->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha1')];
        yield 'sha512WithRSAEncryption' => [$key->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha512')];
        yield 'RSASSA-PSS sha256' => [
            $key->withPadding(RSA::SIGNATURE_PSS)->withHash('sha256')->withMGFHash('sha256')->withSaltLength(32),
        ];
    }

    #[DataProvider('issuerKeyProvider')]
    public function testValidateChainWithSignatureAlgorithm(RSA\PrivateKey $issuerKey): void
    {
        $ca = $this->generateCaCertificate('Algorithm CA', $issuerKey);
        $leaf = $this->generateCertificateSignedBy($issuerKey, $ca['x509'], 'Leaf');

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($ca['der']);
        $trustStore->addTrustedCertificate($leaf);

        (new CertificateValidator($trustStore))->validate($leaf);

        $this->addToAssertionCount(1);
    }

    public function testValidateFailsWhenIssuerMissing(): void
    {
        $ca = $this->generateCaCertificate('Test CA');
        $leaf = $this->generateCertificateSignedBy($ca['key'], $ca['x509'], 'Leaf');

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($leaf);

        $validator = new CertificateValidator($trustStore);

        $this->expectException(CertificateValidationException::class);
        $this->expectExceptionMessage('issuer certificate not found');

        $validator->validate($leaf);
    }

    public function testValidateFailsWhenSignedByDifferentKey(): void
    {
        // Two CAs with the same subject DN but different keys
        $realCa = $this->generateCaCertificate('Test CA');
        $fakeCa = $this->generateCaCertificate('Test CA');
        $leaf = $this->generateCertificateSignedBy($fakeCa['key'], $fakeCa['x509'], 'Leaf');

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($realCa['der']);
        $trustStore->addTrustedCertificate($leaf);

        $validator = new CertificateValidator($trustStore);

        $this->expectException(CertificateValidationException::class);
        $this->expectExceptionMessage('signature validation failed');

        $validator->validate($leaf);
    }

    public function testValidateRejectsUnparseableCertificate(): void
    {
        $validator = new CertificateValidator(new TrustStore());

        $this->expectException(CertificateValidationException::class);
        $this->expectExceptionMessage('Failed to parse certificate');

        $validator->validate("\x30\x03\x02\x01\x01");
    }

    public function testFindBySubjectUsesDistinguishedName(): void
    {
        $ca = $this->generateCaCertificate('Test CA');
        $other = $this->generateCaCertificate('Other CA');

        $trustStore = new TrustStore();
        $trustStore->addTrustedCertificate($ca['der']);
        $trustStore->addTrustedCertificate($other['der']);

        $validator = new CertificateValidator($trustStore);

        $this->assertSame($ca['der'], $trustStore->findBySubject($validator->getSubject($ca['der'])));
        $this->assertSame($other['der'], $trustStore->findBySubject(['id-at-commonName' => 'Other CA']));
        $this->assertNull($trustStore->findBySubject(['id-at-commonName' => 'Unknown CA']));
    }

    public function testFromPemFileLoadsAllCertificatesAsDer(): void
    {
        $ca1 = $this->generateCaCertificate('CA One');
        $ca2 = $this->generateCaCertificate('CA Two');

        $file = tempnam(sys_get_temp_dir(), 'opcua-pem');
        $this->assertNotFalse($file);
        file_put_contents($file, $ca1['x509']->toString() . "\n" . $ca2['x509']->toString() . "\n");

        try {
            $trustStore = TrustStore::fromPemFile($file);
        } finally {
            unlink($file);
        }

        $this->assertSame(2, $trustStore->count());
        $this->assertTrue($trustStore->isTrusted($ca1['der']));
        $this->assertTrue($trustStore->isTrusted($ca2['der']));
    }
}
