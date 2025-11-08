<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Messages\FindServersOnNetworkRequest;
use TechDock\OpcUa\Core\Messages\FindServersOnNetworkResponse;
use TechDock\OpcUa\Core\Messages\FindServersRequest;
use TechDock\OpcUa\Core\Messages\FindServersResponse;
use TechDock\OpcUa\Core\Security\CertificateValidator;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecureChannel;
use TechDock\OpcUa\Core\Transport\TcpConnection;
use TechDock\OpcUa\Core\Types\ApplicationDescription;
use TechDock\OpcUa\Core\Types\ServerOnNetwork;

/**
 * OPC UA Client
 *
 * Main entry point for connecting to OPC UA servers
 */
final class OpcUaClient
{
    private ?SecureChannel $secureChannel = null;

    public function __construct(
        private readonly string $endpointUrl,
        private readonly MessageSecurityMode $securityMode = MessageSecurityMode::None,
        private readonly ?CertificateValidator $certificateValidator = null,
    ) {
        if (!str_starts_with($endpointUrl, 'opc.tcp://')) {
            throw new InvalidArgumentException('Endpoint URL must start with opc.tcp://');
        }
    }

    /**
     * Connect to the server
     */
    public function connect(): void
    {
        if ($this->secureChannel !== null && $this->secureChannel->isOpen()) {
            throw new RuntimeException('Already connected');
        }

        // Parse endpoint URL
        $url = parse_url($this->endpointUrl);

        if (!isset($url['host']) || !isset($url['port'])) {
            throw new InvalidArgumentException('Invalid endpoint URL format');
        }

        $host = $url['host'];
        $port = (int)$url['port'];

        // Create TCP connection with full endpoint URL
        $connection = new TcpConnection($host, $port, $this->endpointUrl);

        // Create secure channel
        $this->secureChannel = new SecureChannel(
            connection: $connection,
            securityMode: $this->securityMode,
            certificateValidator: $this->certificateValidator,
        );

        // Open the secure channel
        $this->secureChannel->open();
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->secureChannel !== null && $this->secureChannel->isOpen();
    }

    /**
     * Disconnect from the server
     */
    public function disconnect(): void
    {
        if ($this->secureChannel !== null) {
            $this->secureChannel->close();
            $this->secureChannel = null;
        }
    }

    /**
     * Get the endpoint URL
     */
    public function getEndpointUrl(): string
    {
        return $this->endpointUrl;
    }

    /**
     * Create a new session
     */
    public function createSession(string $sessionName = 'PHP OPC UA Client Session'): Session
    {
        if (!$this->isConnected()) {
            throw new RuntimeException('Must be connected before creating a session');
        }

        if ($this->secureChannel === null) {
            throw new RuntimeException('Secure channel not established');
        }

        return new Session(
            secureChannel: $this->secureChannel,
            endpointUrl: $this->endpointUrl,
            sessionName: $sessionName,
            client: $this,
        );
    }

    /**
     * Get the secure channel
     */
    public function getSecureChannel(): ?SecureChannel
    {
        return $this->secureChannel;
    }

    /**
     * Find OPC UA servers at a discovery endpoint
     *
     * This is a static method that can be called without an established connection.
     * Use it to discover available servers before creating a client instance.
     *
     * @param string $discoveryEndpointUrl Discovery endpoint URL (e.g., 'opc.tcp://localhost:4840')
     * @param string[] $localeIds Preferred locales (e.g., ['en-US', 'de-DE'])
     * @param string[] $serverUris Filter by server URIs (empty = all servers)
     * @return ApplicationDescription[] List of discovered servers
     */
    public static function findServers(
        string $discoveryEndpointUrl,
        array $localeIds = [],
        array $serverUris = [],
    ): array {
        // Create temporary connection for discovery
        $url = parse_url($discoveryEndpointUrl);
        if (!isset($url['host']) || !isset($url['port'])) {
            throw new InvalidArgumentException('Invalid discovery endpoint URL format');
        }

        $connection = new TcpConnection($url['host'], (int)$url['port'], $discoveryEndpointUrl);
        $secureChannel = new SecureChannel(
            connection: $connection,
            securityMode: MessageSecurityMode::None, // Discovery typically uses no security
        );

        try {
            $secureChannel->open();

            $request = FindServersRequest::create(
                endpointUrl: $discoveryEndpointUrl,
                localeIds: $localeIds,
                serverUris: $serverUris,
            );

            /** @var FindServersResponse $response */
            $response = $secureChannel->sendServiceRequest($request, FindServersResponse::class);

            if (!$response->responseHeader->serviceResult->isGood()) {
                throw new RuntimeException(
                    "FindServers failed: {$response->responseHeader->serviceResult}"
                );
            }

            return $response->servers;
        } finally {
            $secureChannel->close();
        }
    }

    /**
     * Find OPC UA servers on the local network
     *
     * This is a static method that discovers servers via multicast/broadcast.
     * Use it to find servers without knowing their URLs in advance.
     *
     * @param string $discoveryEndpointUrl Discovery endpoint URL (e.g., 'opc.tcp://localhost:4840')
     * @param int $startingRecordId Starting record ID for paging (0 = from beginning)
     * @param int $maxRecordsToReturn Maximum records to return (0 = no limit)
     * @param string[] $serverCapabilityFilter Filter by capabilities (e.g., ['DA', 'HD'])
     * @return ServerOnNetwork[] List of servers discovered on the network
     */
    public static function findServersOnNetwork(
        string $discoveryEndpointUrl,
        int $startingRecordId = 0,
        int $maxRecordsToReturn = 0,
        array $serverCapabilityFilter = [],
    ): array {
        // Create temporary connection for discovery
        $url = parse_url($discoveryEndpointUrl);
        if (!isset($url['host']) || !isset($url['port'])) {
            throw new InvalidArgumentException('Invalid discovery endpoint URL format');
        }

        $connection = new TcpConnection($url['host'], (int)$url['port'], $discoveryEndpointUrl);
        $secureChannel = new SecureChannel(
            connection: $connection,
            securityMode: MessageSecurityMode::None, // Discovery typically uses no security
        );

        try {
            $secureChannel->open();

            $request = FindServersOnNetworkRequest::create(
                startingRecordId: $startingRecordId,
                maxRecordsToReturn: $maxRecordsToReturn,
                serverCapabilityFilter: $serverCapabilityFilter,
            );

            /** @var FindServersOnNetworkResponse $response */
            $response = $secureChannel->sendServiceRequest($request, FindServersOnNetworkResponse::class);

            if (!$response->responseHeader->serviceResult->isGood()) {
                throw new RuntimeException(
                    "FindServersOnNetwork failed: {$response->responseHeader->serviceResult}"
                );
            }

            return $response->servers;
        } finally {
            $secureChannel->close();
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
