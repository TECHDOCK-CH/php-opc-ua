<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Integration;

use TechDock\OpcUa\Client\UserIdentity;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Types\UserTokenType;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

/**
 * Integration tests against an existing OPC UA PLC reference server.
 *
 * Requires that `podman-compose up` (or equivalent Docker setup) is already running
 * and exposing an endpoint (default opc.tcp://127.0.0.14840).
 *
 * Enable via OPCUA_RUN_INTEGRATION_TESTS=1 and optionally override endpoint with
 * OPCUA_INTEGRATION_ENDPOINT.
 *
 * Optional environment variables for extended coverage:
 * - OPCUA_INTEGRATION_USERNAME / OPCUA_INTEGRATION_PASSWORD: Username authentication.
 */
#[Group('integration')]
final class SecureChannelIntegrationTest extends IntegrationTestCase
{
    public function testAnonymousSecureChannelOpens(): void
    {
        $client = $this->createClient(MessageSecurityMode::None);

        $client->connect();

        try {
            self::assertTrue($client->isConnected(), 'Client should report connected state.');
        } finally {
            $client->disconnect();
        }
    }

    public function testServerReportsSecureEndpointsAndIdentityTokens(): void
    {
        $client = $this->createClient();
        $client->connect();

        try {
            $secureChannel = $client->getSecureChannel();
            self::assertNotNull($secureChannel, 'Secure channel should be available after connect.');

            $endpoints = $secureChannel->getAvailableEndpoints();
            self::assertNotEmpty($endpoints, 'Server should advertise at least one endpoint.');

            $hasSecureEndpoint = false;
            foreach ($endpoints as $endpoint) {
                if ($endpoint->securityMode !== MessageSecurityMode::None) {
                    $hasSecureEndpoint = true;
                    break;
                }
            }
            self::assertTrue($hasSecureEndpoint, 'Server should advertise at least one secure endpoint.');

            $selectedEndpoint = $secureChannel->getSelectedEndpoint();
            self::assertNotNull($selectedEndpoint, 'Secure channel should select an endpoint.');

            $serverCertificate = $selectedEndpoint->serverCertificate;
            self::assertNotNull($serverCertificate, 'Selected endpoint should provide a server certificate.');
            self::assertGreaterThan(0, strlen($serverCertificate), 'Server certificate must not be empty.');

            $hasUserNameIdentity = false;
            foreach ($selectedEndpoint->userIdentityTokens as $token) {
                if ($token->tokenType === UserTokenType::UserName) {
                    $hasUserNameIdentity = true;
                    self::assertNotSame('', $token->policyId, 'UserName policyId must not be empty.');
                }
            }
            self::assertTrue($hasUserNameIdentity, 'Selected endpoint should expose a username identity token.');
        } finally {
            $client->disconnect();
        }
    }

    public function testAnonymousSessionActivates(): void
    {
        $client = $this->createClient();
        $client->connect();

        $session = $client->createSession();

        try {
            $session->create();
            $session->activate();

            self::assertTrue($session->isActive(), 'Session should be active after anonymous authentication.');
            self::assertNotNull($session->getSessionId(), 'Session ID should be assigned.');
            self::assertNotNull($session->getAuthenticationToken(), 'Authentication token should be assigned.');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }

    public function testUsernamePasswordSessionActivatesWhenCredentialsProvided(): void
    {
        $client = $this->createClient(MessageSecurityMode::SignAndEncrypt);
        try {
            $client->connect();
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '0x80540000')) {
                self::markTestSkipped('Secure channel SignAndEncrypt handshake rejected (0x80540000). Ensure secure channel encryption support is available.');
            }

            throw $e;
        }

        $secureChannel = $client->getSecureChannel();
        self::assertNotNull($secureChannel, 'Secure channel should be available after connect.');

        $selectedEndpoint = $secureChannel->getSelectedEndpoint();
        self::assertNotNull($selectedEndpoint, 'Secure channel should select an endpoint.');

        $policyId = null;
        foreach ($selectedEndpoint->userIdentityTokens as $token) {
            if ($token->tokenType === UserTokenType::UserName) {
                $policyId = $token->policyId;
                break;
            }
        }

        self::assertNotNull($policyId, 'Server must advertise a username identity token.');
        self::assertNotSame('', $policyId);

        $credentials = $this->getUserCredentials();
        $identity = UserIdentity::userName(
            userName: $credentials['username'],
            password: $credentials['password'],
            policyId: $policyId,
        );

        $session = $client->createSession();

        try {
            $session->create();
            $session->activate($identity);

            self::assertTrue($session->isActive(), 'Session should be active after username authentication.');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }

    public function testCertificateSessionActivatesWithTrustedUserCertificate(): void
    {
        $client = $this->createClient(MessageSecurityMode::SignAndEncrypt);
        try {
            $client->connect();
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '0x80540000')) {
                self::markTestSkipped('Secure channel SignAndEncrypt handshake rejected (0x80540000). Ensure secure channel encryption support is available.');
            }

            throw $e;
        }

        $secureChannel = $client->getSecureChannel();
        self::assertNotNull($secureChannel, 'Secure channel should be available after connect.');

        $selectedEndpoint = $secureChannel->getSelectedEndpoint();
        self::assertNotNull($selectedEndpoint, 'Secure channel should select an endpoint.');

        $policyId = null;
        foreach ($selectedEndpoint->userIdentityTokens as $token) {
            if ($token->tokenType === UserTokenType::Certificate) {
                $policyId = $token->policyId;
                break;
            }
        }

        self::assertNotNull($policyId, 'Server must advertise a certificate identity token.');
        self::assertNotSame('', $policyId);

        $identity = UserIdentity::certificate(
            certificatePem: $this->getIntegrationCertificatePem(),
            policyId: $policyId,
        );

        $session = $client->createSession();

        try {
            $session->create();
            $session->activate($identity);

            self::assertTrue($session->isActive(), 'Session should be active after certificate authentication.');
        } finally {
            $session->close();
            $client->disconnect();
        }
    }
}
