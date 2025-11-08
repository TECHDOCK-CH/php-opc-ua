<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Security;

use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Security\Basic256Sha256Handler;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\OpcUaPadding;
use TechDock\OpcUa\Core\Security\RsaCrypto;
use TechDock\OpcUa\Core\Security\RsaPadding;
use TechDock\OpcUa\Core\Security\SecureChannelCrypto;
use TechDock\OpcUa\Core\Security\SecurityPolicy;
use TechDock\OpcUa\Core\Security\SecurityPolicyFactory;
use TechDock\OpcUa\Core\Security\SequenceHeader;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecureChannelCryptoTest extends TestCase
{
    private const CLIENT_NONCE = '1234567890abcdefghijklmnopqrstuv'; // 32 bytes
    private const SERVER_NONCE = 'zyxwvutsrqponmlkjihgfedcba098765'; // 32 bytes

    private static function generateCertificate(): array
    {
        $privateKey = RSA::createKey(2048);
        $publicKey = $privateKey->getPublicKey();

        $subject = new X509();
        $subject->setDN(['cn' => 'PHP OPC UA Test']);
        $subject->setPublicKey($publicKey);
        $subject->setStartDate('-1 day');
        $subject->setEndDate('+1 year');
        $subject->setSerialNumber(random_bytes(16));

        $issuer = new X509();
        $issuer->setPrivateKey($privateKey);
        $issuer->setDN(['cn' => 'PHP OPC UA Test']);

        $certificate = $issuer->sign($issuer, $subject);
        $certificatePem = $issuer->saveX509($certificate);
        $certificateDer = base64_decode(
            preg_replace(
                '/\s+/',
                '',
                str_replace([
                    '-----BEGIN CERTIFICATE-----',
                    '-----END CERTIFICATE-----',
                ], '', $certificatePem)
            ),
            true
        );

        if ($certificateDer === false) {
            throw new RuntimeException('Failed to convert certificate to DER.');
        }

        return [
            'privateKeyPem' => $privateKey->toString('PKCS1'),
            'certificatePem' => $certificatePem,
            'certificateDer' => $certificateDer,
        ];
    }

    private static function encodeSequenceHeader(int $sequence, int $request): string
    {
        $encoder = new BinaryEncoder();
        (new SequenceHeader($sequence, $request))->encode($encoder);

        return $encoder->getBytes();
    }

    public function testSignAndEncryptRoundTrip(): void
    {
        $handler = new Basic256Sha256Handler();
        $crypto = new SecureChannelCrypto($handler, MessageSecurityMode::SignAndEncrypt);
        $crypto->setClientNonce(self::CLIENT_NONCE);
        $crypto->setServerNonceAndCertificate(self::SERVER_NONCE);

        $sequenceHeaderBytes = self::encodeSequenceHeader(1, 10);
        $body = 'OPC UA Test Payload';

        $keys = $handler->deriveKeys(self::CLIENT_NONCE, self::SERVER_NONCE);

        // Add OPC UA padding before encryption
        $paddedBody = OpcUaPadding::addSymmetric($body, 16);
        $encryptedBody = $handler->encryptSymmetric($paddedBody, $keys['serverEncryptionKey'], $keys['serverIV']);
        $signature = $handler->signSymmetric($sequenceHeaderBytes . $encryptedBody, $keys['serverSigningKey']);

        $decrypted = $crypto->decryptSymmetric(
            $sequenceHeaderBytes,
            $encryptedBody,
            $signature
        );

        $this->assertSame($body, $decrypted);
    }

    public function testSignOnlyModeProducesSignatureWithoutEncryption(): void
    {
        $handler = SecurityPolicyFactory::createHandler(SecurityPolicy::Basic256Sha256);
        $crypto = new SecureChannelCrypto($handler, MessageSecurityMode::Sign);
        $crypto->setClientNonce(self::CLIENT_NONCE);
        $crypto->setServerNonceAndCertificate(self::SERVER_NONCE);

        $sequenceHeaderBytes = self::encodeSequenceHeader(2, 20);
        $body = 'Sign only payload';

        $keys = $handler->deriveKeys(self::CLIENT_NONCE, self::SERVER_NONCE);
        $signature = $handler->signSymmetric($sequenceHeaderBytes . $body, $keys['serverSigningKey']);

        $decrypted = $crypto->decryptSymmetric(
            $sequenceHeaderBytes,
            $body,
            $signature
        );

        $this->assertSame($body, $decrypted);
    }

    public function testNoneModeBypassesSecurity(): void
    {
        $handler = SecurityPolicyFactory::createHandler(SecurityPolicy::None);
        $crypto = new SecureChannelCrypto($handler, MessageSecurityMode::None);
        $crypto->setClientNonce('');
        $crypto->setServerNonceAndCertificate('');

        $sequenceHeaderBytes = self::encodeSequenceHeader(3, 30);
        $body = 'Plain payload';

        $decrypted = $crypto->decryptSymmetric(
            $sequenceHeaderBytes,
            $body,
            ''
        );

        $this->assertSame($body, $decrypted);
    }

    public function testSignatureVerificationFailureThrows(): void
    {
        $handler = new Basic256Sha256Handler();
        $crypto = new SecureChannelCrypto($handler, MessageSecurityMode::Sign);
        $crypto->setClientNonce(self::CLIENT_NONCE);
        $crypto->setServerNonceAndCertificate(self::SERVER_NONCE);

        $sequenceHeaderBytes = self::encodeSequenceHeader(4, 40);
        $body = 'Signed payload';

        $keys = $handler->deriveKeys(self::CLIENT_NONCE, self::SERVER_NONCE);
        $signature = $handler->signSymmetric($sequenceHeaderBytes . $body, $keys['serverSigningKey']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Message signature verification failed');

        $crypto->decryptSymmetric(
            $sequenceHeaderBytes,
            $body,
            substr($signature, 0, -1) . chr(ord(substr($signature, -1)) ^ 0xFF)
        );
    }

    public function testAsymmetricEncryptSupportsMultiBlockMessages(): void
    {
        $creds = self::generateCertificate();

        $handler = new Basic256Sha256Handler();
        $crypto = new SecureChannelCrypto($handler, MessageSecurityMode::SignAndEncrypt);
        $crypto->setClientNonce(str_repeat("\xAA", 32));
        $crypto->setServerNonceAndCertificate(str_repeat("\xBB", 32), $creds['certificateDer']);

        $plaintext = random_bytes(600); // larger than single RSA block

        $result = $crypto->encryptAsymmetric($plaintext);
        $this->assertSame('', $result['signature']);
        $this->assertNotSame($plaintext, $result['encrypted']);


        $cipherBlockSize = RsaCrypto::getCiphertextBlockSize($creds['certificatePem']);
        $decrypted = '';
        for ($offset = 0; $offset < strlen($result['encrypted']); $offset += $cipherBlockSize) {
            $block = substr($result['encrypted'], $offset, $cipherBlockSize);
            if ($block === '') {
                break;
            }
            $decrypted .= RsaCrypto::decrypt($block, $creds['privateKeyPem'], RsaPadding::OAEP);
        }

        $this->assertSame($plaintext, $decrypted);
    }
}
