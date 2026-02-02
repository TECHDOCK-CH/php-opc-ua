<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Client\Cache\INodeCache;
use TechDock\OpcUa\Client\Cache\LruNodeCache;
use TechDock\OpcUa\Core\Messages\EndpointDescription;
use TechDock\OpcUa\Core\Messages\GetEndpointsRequest;
use TechDock\OpcUa\Core\Messages\GetEndpointsResponse;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecureChannel;
use TechDock\OpcUa\Core\Security\SecurityPolicy;
use TechDock\OpcUa\Core\Transport\TcpConnection;
use TechDock\OpcUa\Core\Types\ApplicationDescription;
use TechDock\OpcUa\Core\Types\ApplicationType;
use TechDock\OpcUa\Core\Types\LocalizedText;

/**
 * ClientBuilder - Fluent API for creating OPC UA clients
 *
 * Features:
 * - Sensible defaults for common scenarios
 * - Automatic server discovery and endpoint selection
 * - Optional caching and performance optimizations
 * - Clean, chainable configuration API
 *
 * Example:
 * ```php
 * $client = ClientBuilder::create()
 *     ->endpoint('opc.tcp://localhost:4840')
 *     ->withAnonymousAuth()
 *     ->withCache(maxSize: 1000)
 *     ->withAutoBatching()
 *     ->build();
 * ```
 */
final class ClientBuilder
{
    private ?string $endpointUrl = null;
    private ?EndpointDescription $endpoint = null;
    private ?ApplicationDescription $applicationDescription = null;
    private ?UserIdentity $userIdentity = null;
    private ?INodeCache $cache = null;
    private bool $autoBatching = false;
    private bool $autoDiscovery = false;
    private ?MessageSecurityMode $preferredSecurityMode = null;
    private ?SecurityPolicy $preferredSecurityPolicy = null;

    private function __construct()
    {
    }

    /**
     * Create a new builder instance
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the server endpoint URL
     *
     * @param string $url OPC UA endpoint URL (e.g., 'opc.tcp://localhost:4840')
     */
    public function endpoint(string $url): self
    {
        $this->endpointUrl = $url;
        return $this;
    }

    /**
     * Set a specific endpoint description
     *
     * Use this if you've already selected an endpoint via discovery.
     */
    public function withEndpoint(EndpointDescription $endpoint): self
    {
        $this->endpoint = $endpoint;
        $this->endpointUrl = $endpoint->endpointUrl;
        return $this;
    }

    /**
     * Enable automatic server discovery
     *
     * The builder will automatically fetch endpoints and select the best one.
     */
    public function withAutoDiscovery(): self
    {
        $this->autoDiscovery = true;
        return $this;
    }

    /**
     * Set preferred security mode for auto-discovery
     */
    public function preferSecurityMode(MessageSecurityMode $mode): self
    {
        $this->preferredSecurityMode = $mode;
        return $this;
    }

    /**
     * Set preferred security policy for auto-discovery
     */
    public function preferSecurityPolicy(SecurityPolicy $policy): self
    {
        $this->preferredSecurityPolicy = $policy;
        return $this;
    }

    /**
     * Prefer no security (for testing/development)
     *
     * Shortcut for preferring MessageSecurityMode::None
     */
    public function withNoSecurity(): self
    {
        $this->preferredSecurityMode = MessageSecurityMode::None;
        return $this;
    }

    /**
     * Set application description
     *
     * @param string $name Application name
     * @param string|null $uri Application URI (defaults to generated URI)
     * @param ApplicationType $type Application type (defaults to Client)
     */
    public function application(
        string $name,
        ?string $uri = null,
        ApplicationType $type = ApplicationType::Client,
    ): self {
        $this->applicationDescription = new ApplicationDescription(
            applicationUri: $uri ?? "urn:php-opc-ua:client:$name",
            productUri: 'https://github.com/php-opc-ua',
            applicationName: new LocalizedText(locale: null, text: $name),
            applicationType: $type,
            gatewayServerUri: null,
            discoveryProfileUri: null,
            discoveryUrls: [],
        );
        return $this;
    }

    /**
     * Use anonymous authentication
     */
    public function withAnonymousAuth(): self
    {
        $this->userIdentity = UserIdentity::anonymous();
        return $this;
    }

    /**
     * Use username/password authentication
     */
    public function withUsernameAuth(string $username, string $password): self
    {
        $this->userIdentity = UserIdentity::userName($username, $password);
        return $this;
    }

    /**
     * Set custom user identity
     */
    public function withUserIdentity(UserIdentity $identity): self
    {
        $this->userIdentity = $identity;
        return $this;
    }

    /**
     * Enable node caching for improved performance
     *
     * @param int $maxSize Maximum cache entries (default: 1000)
     */
    public function withCache(int $maxSize = 1000): self
    {
        $this->cache = new LruNodeCache($maxSize);
        return $this;
    }

    /**
     * Set a custom cache implementation
     */
    public function withCustomCache(INodeCache $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Enable automatic batch splitting for large operations
     *
     * The client will automatically detect server capabilities and split
     * large read/write operations into appropriately sized batches.
     */
    public function withAutoBatching(): self
    {
        $this->autoBatching = true;
        return $this;
    }

    /**
     * Build and connect the client
     *
     * This method:
     * 1. Discovers endpoints if auto-discovery is enabled
     * 2. Creates the OpcUaClient
     * 3. Connects to the server
     * 4. Creates and activates a session
     * 5. Configures performance features (cache, batching)
     *
     * @return ConnectedClient A connected and configured client
     * @throws RuntimeException if connection or configuration fails
     */
    public function build(): ConnectedClient
    {
        // Validate configuration
        if ($this->endpointUrl === null) {
            throw new RuntimeException('Endpoint URL is required');
        }

        // Perform auto-discovery if enabled
        if ($this->autoDiscovery && $this->endpoint === null) {
            $this->endpoint = $this->discoverEndpoint();
        }

        // Create default application description if not set
        if ($this->applicationDescription === null) {
            $this->applicationDescription = new ApplicationDescription(
                applicationUri: 'urn:php-opc-ua:client',
                productUri: 'https://github.com/php-opc-ua',
                applicationName: new LocalizedText(locale: null, text: 'PHP OPC UA Client'),
                applicationType: ApplicationType::Client,
                gatewayServerUri: null,
                discoveryProfileUri: null,
                discoveryUrls: [],
            );
        }

        // Use anonymous auth by default
        if ($this->userIdentity === null) {
            $this->userIdentity = UserIdentity::anonymous();
        }

        // Create client
        $client = new OpcUaClient(
            endpointUrl: $this->endpointUrl,
        );

        // Connect
        $client->connect();

        // Create session
        $session = $client->createSession();
        $session->create();

        // Ensure anonymous policyId matches the server's token policy.
        if ($this->userIdentity->isAnonymous()) {
            $this->userIdentity = UserIdentity::anonymousFromSession($session);
        }

        $session->activate($this->userIdentity);

        // Configure performance features
        if ($this->autoBatching) {
            $session->enableAutoBatchSplitting();
        }

        // Create browser with cache if configured
        $browser = $this->cache !== null
            ? new Browser($session, cache: $this->cache)
            : new Browser($session);

        return new ConnectedClient(
            client: $client,
            session: $session,
            browser: $browser,
            cache: $this->cache,
        );
    }

    /**
     * Build a client and immediately disconnect (for testing endpoints)
     *
     * @return array{client: OpcUaClient, session: Session, endpoints: EndpointDescription[]}
     */
    public function testConnection(): array
    {
        if ($this->endpointUrl === null) {
            throw new RuntimeException('Endpoint URL is required');
        }

        // Fetch endpoints
        $endpoints = $this->fetchEndpoints();

        // Create client
        $client = new OpcUaClient(
            endpointUrl: $this->endpointUrl,
        );

        // Connect briefly
        $client->connect();
        $session = $client->createSession();
        $session->create();

        return [
            'client' => $client,
            'session' => $session,
            'endpoints' => $endpoints,
        ];
    }

    /**
     * Discover and select the best endpoint
     */
    private function discoverEndpoint(): EndpointDescription
    {
        $endpoints = $this->fetchEndpoints();

        if ($endpoints === []) {
            throw new RuntimeException('No endpoints found at ' . $this->endpointUrl);
        }

        // Apply preferred security settings
        if ($this->preferredSecurityMode !== null) {
            $endpoints = EndpointSelector::filterBySecurityMode($endpoints, $this->preferredSecurityMode);
            if ($endpoints === []) {
                throw new RuntimeException(
                    'No endpoints found with security mode ' . $this->preferredSecurityMode->name
                );
            }
        }

        if ($this->preferredSecurityPolicy !== null) {
            $endpoints = EndpointSelector::filterBySecurityPolicy($endpoints, $this->preferredSecurityPolicy);
            if ($endpoints === []) {
                throw new RuntimeException(
                    'No endpoints found with security policy ' . $this->preferredSecurityPolicy->value
                );
            }
        }

        // Select best endpoint
        if ($this->preferredSecurityMode === MessageSecurityMode::None) {
            $result = EndpointSelector::selectNoSecurity($endpoints);
        } else {
            $result = EndpointSelector::selectBest(
                $endpoints,
                $this->preferredSecurityMode,
                $this->preferredSecurityPolicy,
            );
        }

        if ($result === null) {
            throw new RuntimeException('No suitable endpoint found');
        }

        return $result;
    }

    /**
     * Fetch endpoints from server
     *
     * @return EndpointDescription[]
     */
    private function fetchEndpoints(): array
    {
        // Create temporary connection for endpoint discovery
        $url = parse_url($this->endpointUrl ?? '');
        if (!isset($url['host']) || !isset($url['port'])) {
            throw new InvalidArgumentException('Invalid endpoint URL format');
        }

        $connection = new TcpConnection($url['host'], (int)$url['port'], $this->endpointUrl ?? '');
        $secureChannel = new SecureChannel(
            connection: $connection,
            securityMode: MessageSecurityMode::None,
        );

        try {
            $secureChannel->open();

            $request = GetEndpointsRequest::create($this->endpointUrl ?? '');

            /** @var GetEndpointsResponse $response */
            $response = $secureChannel->sendServiceRequest($request, GetEndpointsResponse::class);

            if (!$response->responseHeader->serviceResult->isGood()) {
                throw new RuntimeException(
                    "GetEndpoints failed: {$response->responseHeader->serviceResult}"
                );
            }

            return $response->endpoints;
        } finally {
            $secureChannel->close();
        }
    }
}
