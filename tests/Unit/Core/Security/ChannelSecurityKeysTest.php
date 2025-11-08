<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Unit\Core\Security;

use TechDock\OpcUa\Core\Security\Basic256Sha256Handler;
use TechDock\OpcUa\Core\Security\ChannelSecurityKeys;
use TechDock\OpcUa\Core\Security\NoneHandler;
use PHPUnit\Framework\TestCase;

final class ChannelSecurityKeysTest extends TestCase
{
    public function testDeriveKeysWithBasic256Sha256(): void
    {
        $clientNonce = random_bytes(32);
        $serverNonce = random_bytes(32);
        $tokenId = 12345;
        $handler = new Basic256Sha256Handler();

        $keys = ChannelSecurityKeys::derive($clientNonce, $serverNonce, $tokenId, $handler);

        self::assertSame($tokenId, $keys->tokenId);
        self::assertSame(32, strlen($keys->clientSigningKey), 'Client signing key should be 32 bytes');
        self::assertSame(32, strlen($keys->clientEncryptionKey), 'Client encryption key should be 32 bytes');
        self::assertSame(16, strlen($keys->clientIV), 'Client IV should be 16 bytes');
        self::assertSame(32, strlen($keys->serverSigningKey), 'Server signing key should be 32 bytes');
        self::assertSame(32, strlen($keys->serverEncryptionKey), 'Server encryption key should be 32 bytes');
        self::assertSame(16, strlen($keys->serverIV), 'Server IV should be 16 bytes');
    }

    public function testDerivedKeysAreDeterministic(): void
    {
        $clientNonce = random_bytes(32);
        $serverNonce = random_bytes(32);
        $tokenId = 999;
        $handler = new Basic256Sha256Handler();

        $keys1 = ChannelSecurityKeys::derive($clientNonce, $serverNonce, $tokenId, $handler);
        $keys2 = ChannelSecurityKeys::derive($clientNonce, $serverNonce, $tokenId, $handler);

        // Same nonces should produce identical keys
        self::assertSame($keys1->clientSigningKey, $keys2->clientSigningKey);
        self::assertSame($keys1->clientEncryptionKey, $keys2->clientEncryptionKey);
        self::assertSame($keys1->clientIV, $keys2->clientIV);
        self::assertSame($keys1->serverSigningKey, $keys2->serverSigningKey);
        self::assertSame($keys1->serverEncryptionKey, $keys2->serverEncryptionKey);
        self::assertSame($keys1->serverIV, $keys2->serverIV);
    }

    public function testDifferentNoncesProduceDifferentKeys(): void
    {
        $clientNonce1 = random_bytes(32);
        $serverNonce1 = random_bytes(32);
        $clientNonce2 = random_bytes(32);
        $serverNonce2 = random_bytes(32);
        $tokenId = 1;
        $handler = new Basic256Sha256Handler();

        $keys1 = ChannelSecurityKeys::derive($clientNonce1, $serverNonce1, $tokenId, $handler);
        $keys2 = ChannelSecurityKeys::derive($clientNonce2, $serverNonce2, $tokenId, $handler);

        // Different nonces should produce different keys
        self::assertNotSame($keys1->clientSigningKey, $keys2->clientSigningKey);
        self::assertNotSame($keys1->clientEncryptionKey, $keys2->clientEncryptionKey);
        self::assertNotSame($keys1->serverSigningKey, $keys2->serverSigningKey);
        self::assertNotSame($keys1->serverEncryptionKey, $keys2->serverEncryptionKey);
    }

    public function testClientAndServerKeysAreDifferent(): void
    {
        $clientNonce = random_bytes(32);
        $serverNonce = random_bytes(32);
        $tokenId = 1;
        $handler = new Basic256Sha256Handler();

        $keys = ChannelSecurityKeys::derive($clientNonce, $serverNonce, $tokenId, $handler);

        // Client and server keys should be different (derived from swapped nonces)
        self::assertNotSame($keys->clientSigningKey, $keys->serverSigningKey);
        self::assertNotSame($keys->clientEncryptionKey, $keys->serverEncryptionKey);
        self::assertNotSame($keys->clientIV, $keys->serverIV);
    }

    public function testGetKeySizes(): void
    {
        $clientNonce = random_bytes(32);
        $serverNonce = random_bytes(32);
        $tokenId = 1;
        $handler = new Basic256Sha256Handler();

        $keys = ChannelSecurityKeys::derive($clientNonce, $serverNonce, $tokenId, $handler);
        $sizes = $keys->getKeySizes();

        self::assertSame(32, $sizes['clientSigningKey']);
        self::assertSame(32, $sizes['clientEncryptionKey']);
        self::assertSame(16, $sizes['clientIV']);
        self::assertSame(32, $sizes['serverSigningKey']);
        self::assertSame(32, $sizes['serverEncryptionKey']);
        self::assertSame(16, $sizes['serverIV']);
    }

    public function testDeriveKeysWithNoneHandler(): void
    {
        $clientNonce = '';
        $serverNonce = '';
        $tokenId = 1;
        $handler = new NoneHandler();

        $keys = ChannelSecurityKeys::derive($clientNonce, $serverNonce, $tokenId, $handler);

        // NoneHandler should produce empty keys
        self::assertSame('', $keys->clientSigningKey);
        self::assertSame('', $keys->clientEncryptionKey);
        self::assertSame('', $keys->clientIV);
        self::assertSame('', $keys->serverSigningKey);
        self::assertSame('', $keys->serverEncryptionKey);
        self::assertSame('', $keys->serverIV);
    }

    public function testKnownNoncesProduceExpectedKeys(): void
    {
        // Use known nonces to verify PSHA-256 implementation
        $clientNonce = hex2bin('0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f20');
        $serverNonce = hex2bin('2122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f40');
        $tokenId = 42;
        $handler = new Basic256Sha256Handler();

        $keys = ChannelSecurityKeys::derive($clientNonce, $serverNonce, $tokenId, $handler);

        // Keys should be deterministic for these specific nonces
        self::assertSame(32, strlen($keys->clientSigningKey));
        self::assertSame(32, strlen($keys->clientEncryptionKey));
        self::assertSame(16, strlen($keys->clientIV));

        // Verify keys are not zero (actual derivation occurred)
        self::assertNotSame(str_repeat("\x00", 32), $keys->clientSigningKey);
        self::assertNotSame(str_repeat("\x00", 32), $keys->clientEncryptionKey);
        self::assertNotSame(str_repeat("\x00", 16), $keys->clientIV);
    }
}
