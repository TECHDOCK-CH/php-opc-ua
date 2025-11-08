<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Integration;

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Client\UserIdentity;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\NodeId;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

/**
 * Integration tests for encrypted OPC UA connections
 *
 * These tests verify that message encryption/decryption works correctly
 * with a real OPC UA server.
 *
 * Note: opc-plc server may not support encryption by default.
 * If these tests are skipped, the server needs to be configured with
 * security policies enabled.
 */
#[Group('integration')]
#[Group('encryption')]
final class EncryptedConnectionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Check if server supports encryption
        if (!$this->serverSupportsEncryption()) {
            self::markTestSkipped(
                'OPC UA server does not support encryption. ' .
                'Configure server with Basic256Sha256 security policy to run these tests.'
            );
        }
    }

    public function testConnectWithSignAndEncryptMode(): void
    {
        $client = $this->createClient(MessageSecurityMode::SignAndEncrypt);

        try {
            // This should derive encryption keys automatically
            $client->connect();

            $session = $client->createSession();
            $session->create();
            $session->activate();

            // If we got here, encryption is working!
            self::assertTrue(true, 'Successfully connected with SignAndEncrypt mode');

            $session->close();
        } finally {
            $client->disconnect();
        }
    }

    public function testConnectWithSignMode(): void
    {
        $client = $this->createClient(MessageSecurityMode::Sign);

        try {
            $client->connect();

            $session = $client->createSession();
            $session->create();
            $session->activate();

            self::assertTrue(true, 'Successfully connected with Sign mode');

            $session->close();
        } finally {
            $client->disconnect();
        }
    }

    public function testReadServerTimeWithEncryption(): void
    {
        $client = $this->createClient(MessageSecurityMode::SignAndEncrypt);

        try {
            $client->connect();

            $session = $client->createSession();
            $session->create();
            $session->activate();

            // Read server current time (encrypted)
            $results = $session->read([NodeId::numeric(0, 2258)]);

            self::assertCount(1, $results);
            self::assertInstanceOf(DateTime::class, $results[0]->value->value);

            $session->close();
        } finally {
            $client->disconnect();
        }
    }

    public function testMultipleEncryptedOperations(): void
    {
        $client = $this->createClient(MessageSecurityMode::SignAndEncrypt);

        try {
            $client->connect();

            $session = $client->createSession();
            $session->create();
            $session->activate();

            // Perform multiple operations to test sequence numbers
            for ($i = 0; $i < 5; $i++) {
                $results = $session->read([NodeId::numeric(0, 2258)]);
                self::assertCount(1, $results, "Read #{$i} failed");
            }

            self::assertTrue(true, 'Multiple encrypted operations succeeded');

            $session->close();
        } finally {
            $client->disconnect();
        }
    }

    public function testBrowseWithEncryption(): void
    {
        $client = $this->createClient(MessageSecurityMode::SignAndEncrypt);

        try {
            $client->connect();

            $session = $client->createSession();
            $session->create();
            $session->activate();

            // Browse Objects folder (encrypted)
            $browseResult = $session->browse(
                BrowseDescription::create(NodeId::numeric(0, 85))
            );

            self::assertNotEmpty($browseResult->references, 'Browse should return references');

            $session->close();
        } finally {
            $client->disconnect();
        }
    }

    public function testUsernamePasswordAuthWithEncryption(): void
    {
        $client = $this->createClient(MessageSecurityMode::SignAndEncrypt);
        $credentials = $this->getUserCredentials();

        try {
            $client->connect();

            $session = $client->createSession();
            $session->create();

            // Activate with username/password (encrypted)
            $identity = UserIdentity::userName(
                $credentials['username'],
                $credentials['password']
            );
            $session->activate($identity);

            // Verify authenticated
            $results = $session->read([NodeId::numeric(0, 2258)]);
            self::assertCount(1, $results);

            $session->close();
        } finally {
            $client->disconnect();
        }
    }

    public function testEncryptedConnectionUsesCorrectSecurityPolicy(): void
    {
        $client = new OpcUaClient(
            endpointUrl: $this->endpointUrl(),
            securityMode: MessageSecurityMode::SignAndEncrypt,
            securityPolicy: SecurityPolicy::Basic256Sha256
        );

        try {
            $client->connect();

            $session = $client->createSession();
            $channel = $session->getSecureChannel();

            // Verify the selected endpoint matches our request
            $endpoint = $channel->getSelectedEndpoint();
            self::assertNotNull($endpoint);
            self::assertSame(MessageSecurityMode::SignAndEncrypt, $endpoint->securityMode);
            self::assertSame(SecurityPolicy::Basic256Sha256, $endpoint->securityPolicy);

            $session->create();
            $session->activate();
            $session->close();
        } finally {
            $client->disconnect();
        }
    }

    /**
     * Check if the server supports encrypted connections
     *
     * This connects to the server and checks available endpoints
     */
    private function serverSupportsEncryption(): bool
    {
        try {
            $client = $this->createClient(MessageSecurityMode::None);
            $client->connect();

            $session = $client->createSession();
            $channel = $session->getSecureChannel();
            $endpoints = $channel->getAvailableEndpoints();

            $client->disconnect();

            // Check if any endpoint supports SignAndEncrypt with Basic256Sha256
            foreach ($endpoints as $endpoint) {
                if ($endpoint->securityMode === MessageSecurityMode::SignAndEncrypt
                    && $endpoint->securityPolicy === SecurityPolicy::Basic256Sha256) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
