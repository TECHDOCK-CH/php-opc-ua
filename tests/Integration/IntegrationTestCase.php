<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Tests\Integration;

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Shared test harness for integration tests that interact with a live OPC UA server.
 */
abstract class IntegrationTestCase extends TestCase
{
    private const DEFAULT_ENDPOINT = 'opc.tcp://127.0.0.1:4840';
    private const READINESS_TIMEOUT_SECONDS = 15;

    private const DEFAULT_USERNAME = 'integration-user';
    private const DEFAULT_PASSWORD = 'integration-pass';
    private const DEFAULT_CERTIFICATE_PATH = '/../fixtures/certs/test-user-cert.pem';

    /** @var array<string, bool> Cache of endpoint readiness checks */
    private static array $readyEndpoints = [];

    protected static ?string $endpointUrl = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::shouldRun()) {
            self::markTestSkipped('Set OPCUA_RUN_INTEGRATION_TESTS=1 to run integration tests.');
        }

        if (static::$endpointUrl === null) {
            static::$endpointUrl = getenv('OPCUA_INTEGRATION_ENDPOINT') ?: self::DEFAULT_ENDPOINT;
        }

        $endpoint = static::$endpointUrl;
        if (!isset(self::$readyEndpoints[$endpoint])) {
            if (!self::waitForEndpoint($endpoint, self::READINESS_TIMEOUT_SECONDS)) {
                self::markTestSkipped(sprintf('OPC UA endpoint %s not reachable. Ensure podman-compose is running.', $endpoint));
            }

            self::$readyEndpoints[$endpoint] = true;
        }
    }

    protected function endpointUrl(): string
    {
        return static::$endpointUrl ?? self::DEFAULT_ENDPOINT;
    }

    protected function createClient(MessageSecurityMode $securityMode = MessageSecurityMode::None): OpcUaClient
    {
        return new OpcUaClient(
            endpointUrl: $this->endpointUrl(),
            securityMode: $securityMode,
        );
    }

    /**
     * Resolve integration test credentials, preferring environment overrides.
     *
     * @return array{username: string, password: string}
     */
    protected function getUserCredentials(): array
    {
        $username = getenv('OPCUA_INTEGRATION_USERNAME') ?: self::DEFAULT_USERNAME;
        $password = getenv('OPCUA_INTEGRATION_PASSWORD') ?: self::DEFAULT_PASSWORD;

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Load the PEM-encoded certificate used for certificate-based authentication.
     */
    protected function getIntegrationCertificatePem(): string
    {
        $path = getenv('OPCUA_INTEGRATION_USER_CERT_PEM')
            ?: __DIR__ . self::DEFAULT_CERTIFICATE_PATH;

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Integration certificate PEM not found at %s', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read integration certificate PEM at %s', $path));
        }

        return $contents;
    }

    private static function shouldRun(): bool
    {
        return getenv('OPCUA_RUN_INTEGRATION_TESTS') === '1';
    }

    private static function waitForEndpoint(string $endpointUrl, int $timeoutSeconds): bool
    {
        $parts = parse_url($endpointUrl);
        $host = $parts['host'] ?? '127.0.0.1';
        $port = (int)($parts['port'] ?? 4840);

        $deadline = time() + $timeoutSeconds;
        while (time() <= $deadline) {
            $socket = @stream_socket_client(sprintf('tcp://%s:%d', $host, $port), $errno, $errstr, 1.0);
            if ($socket !== false) {
                fclose($socket);
                return true;
            }
            usleep(250_000);
        }

        return false;
    }
}
