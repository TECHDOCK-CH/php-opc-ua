<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Messages\ActivateSessionRequest;
use TechDock\OpcUa\Core\Messages\ActivateSessionResponse;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Messages\BrowseNextRequest;
use TechDock\OpcUa\Core\Messages\BrowseNextResponse;
use TechDock\OpcUa\Core\Messages\BrowseRequest;
use TechDock\OpcUa\Core\Messages\BrowseResponse;
use TechDock\OpcUa\Core\Messages\BrowseResult;
use TechDock\OpcUa\Core\Messages\CallRequest;
use TechDock\OpcUa\Core\Messages\CallResponse;
use TechDock\OpcUa\Core\Messages\CloseSessionRequest;
use TechDock\OpcUa\Core\Messages\CloseSessionResponse;
use TechDock\OpcUa\Core\Messages\CreateSessionRequest;
use TechDock\OpcUa\Core\Messages\CreateSessionResponse;
use TechDock\OpcUa\Core\Messages\HistoryReadRequest;
use TechDock\OpcUa\Core\Messages\HistoryReadResponse;
use TechDock\OpcUa\Core\Messages\PublishRequest;
use TechDock\OpcUa\Core\Messages\PublishResponse;
use TechDock\OpcUa\Core\Messages\ReadRequest;
use TechDock\OpcUa\Core\Messages\ReadResponse;
use TechDock\OpcUa\Core\Messages\RegisterNodesRequest;
use TechDock\OpcUa\Core\Messages\RegisterNodesResponse;
use TechDock\OpcUa\Core\Messages\RequestHeader;
use TechDock\OpcUa\Core\Messages\TranslateBrowsePathsToNodeIdsRequest;
use TechDock\OpcUa\Core\Messages\TranslateBrowsePathsToNodeIdsResponse;
use TechDock\OpcUa\Core\Messages\UnregisterNodesRequest;
use TechDock\OpcUa\Core\Messages\UnregisterNodesResponse;
use TechDock\OpcUa\Core\Messages\WriteRequest;
use TechDock\OpcUa\Core\Messages\WriteResponse;
use TechDock\OpcUa\Core\Security\SecureChannel;
use TechDock\OpcUa\Core\Security\SecurityPolicy;
use TechDock\OpcUa\Core\Types\BrowsePath;
use TechDock\OpcUa\Core\Types\BrowsePathResult;
use TechDock\OpcUa\Core\Types\CallMethodRequest;
use TechDock\OpcUa\Core\Types\CallMethodResult;
use TechDock\OpcUa\Core\Types\DataValue;
use TechDock\OpcUa\Core\Types\HistoryReadResult;
use TechDock\OpcUa\Core\Types\HistoryReadValueId;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\ReadRawModifiedDetails;
use TechDock\OpcUa\Core\Types\ReadValueId;
use TechDock\OpcUa\Core\Types\StatusCode;
use TechDock\OpcUa\Core\Types\SubscriptionAcknowledgement;
use TechDock\OpcUa\Core\Types\TimestampsToReturn;
use TechDock\OpcUa\Core\Types\UserNameIdentityToken;
use TechDock\OpcUa\Core\Types\UserTokenType;
use TechDock\OpcUa\Core\Types\Variant;
use TechDock\OpcUa\Core\Types\WriteValue;
use Throwable;

/**
 * OPC UA Session
 *
 * Manages a session with the server and provides service operations
 */
final class Session
{
    private bool $isActive = false;
    private ?NodeId $sessionId = null;
    private ?NodeId $authenticationToken = null;
    private ?string $serverNonce = null;
    private int $revisedSessionTimeout = 0;

    /** @var array<int, Subscription> Subscriptions indexed by ID */
    private array $subscriptions = [];

    /** @var SubscriptionAcknowledgement[] Acknowledgements to send */
    private array $acknowledgements = [];

    private bool $publishingActive = false;
    private int $maxAcknowledgements = 100;

    // Keep-alive monitoring
    private bool $keepAliveEnabled = false;
    private float $keepAliveInterval = 5.0; // seconds
    private float $lastPublishTime = 0.0;
    private float $lastKeepAliveTime = 0.0;
    private int $missedKeepAliveCount = 0;
    private int $maxMissedKeepAlives = 3;

    /** @var callable|null Callback: function(Session): void */
    private $onKeepAliveMissed = null;

    /** @var callable|null Callback: function(Session): void */
    private $onConnectionLost = null;

    // Reconnection support
    private ?SessionReconnectHandler $reconnectHandler = null;
    private bool $autoReconnect = false;

    // Server capabilities cache
    private ?ServerCapabilities $serverCapabilities = null;
    private bool $autoBatchSplitting = false;

    public function __construct(
        private readonly SecureChannel $secureChannel,
        private readonly string $endpointUrl,
        private readonly string $sessionName = 'PHP OPC UA Client Session',
        private readonly ?OpcUaClient $client = null,
    ) {
    }

    /**
     * Create the session with the server
     */
    public function create(): void
    {
        if ($this->sessionId !== null) {
            throw new RuntimeException('Session already created');
        }

        $request = CreateSessionRequest::create(
            endpointUrl: $this->endpointUrl,
            sessionName: $this->sessionName,
        );

        /** @var CreateSessionResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, CreateSessionResponse::class);

        $this->sessionId = $response->sessionId;
        $this->authenticationToken = $response->authenticationToken;
        $this->serverNonce = $response->serverNonce;
        $this->revisedSessionTimeout = (int)$response->revisedSessionTimeout;

        // Propagate auth token to SecureChannel for automatic injection
        $this->secureChannel->setAuthenticationToken($this->authenticationToken);
    }

    /**
     * Get the revised session timeout in milliseconds
     */
    public function getRevisedSessionTimeout(): int
    {
        return $this->revisedSessionTimeout;
    }

    /**
     * Get the last keep-alive time (Unix timestamp)
     */
    public function getLastKeepAliveTime(): float
    {
        return $this->lastKeepAliveTime;
    }

    /**
     * Activate the session with user authentication
     *
     * @param UserIdentity|null $userIdentity User identity for authentication (null = anonymous)
     */
    public function activate(?UserIdentity $userIdentity = null): void
    {
        if ($this->sessionId === null) {
            throw new RuntimeException('Session must be created before activation');
        }

        if ($this->isActive) {
            throw new RuntimeException('Session is already active');
        }

        // Default to anonymous if not specified, with auto-detected policyId
        if ($userIdentity === null) {
            $policyId = $this->findPolicyIdForTokenType(UserTokenType::Anonymous);
            $userIdentity = UserIdentity::anonymous($policyId);
        }

        // Create request header with authentication token from CreateSession
        $requestHeader = $this->createRequestHeader();

        // Encrypt password if using username/password authentication
        $token = $userIdentity->getToken();
        if ($token instanceof UserNameIdentityToken) {
            // Get server certificate from endpoint
            $endpoint = $this->secureChannel->getSelectedEndpoint();
            $serverCertificate = $endpoint?->serverCertificate;

            if ($serverCertificate === null || $serverCertificate === '') {
                throw new RuntimeException('Server certificate required for password encryption');
            }

            // Convert DER to PEM format for phpseclib
            $serverCertificatePem = $this->derToPem($serverCertificate);

            // Determine security policy to use for password encryption.
            $securityPolicy = $endpoint->securityPolicy ?? SecurityPolicy::None;
            if ($endpoint !== null) {
                foreach ($endpoint->userIdentityTokens as $tokenPolicy) {
                    if (
                        $tokenPolicy->policyId === $token->policyId
                        && $tokenPolicy->securityPolicyUri !== null
                        && $tokenPolicy->securityPolicyUri !== ''
                    ) {
                        $securityPolicy = SecurityPolicy::fromUri(
                            $tokenPolicy->securityPolicyUri
                        );
                        break;
                    }
                }
            }

            // Encrypt the password
            $token->encrypt(
                $serverCertificatePem,
                $this->serverNonce,
                $securityPolicy
            );
        }

        // Create ActivateSessionRequest with the identity token
        $request = ActivateSessionRequest::withIdentity($requestHeader, $token);

        /** @var ActivateSessionResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, ActivateSessionResponse::class);

        $this->serverNonce = $response->serverNonce;
        $this->isActive = true;
    }

    /**
     * Convert DER-encoded certificate to PEM format
     */
    private function derToPem(string $der): string
    {
        $pem = "-----BEGIN CERTIFICATE-----\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");
        $pem .= "-----END CERTIFICATE-----\n";
        return $pem;
    }

    /**
     * Browse a node
     */
    public function browse(BrowseDescription $browseDescription): BrowseResult
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before browsing');
        }

        // Create request header with authentication token
        $requestHeader = $this->createRequestHeader();

        $request = BrowseRequest::forNode($browseDescription, requestHeader: $requestHeader);

        /** @var BrowseResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, BrowseResponse::class);

        if ($response->results === []) {
            throw new RuntimeException('Server returned empty browse results');
        }

        return $response->results[0];
    }

    /**
     * Continue a browse operation using continuation points
     *
     * @param string[] $continuationPoints Continuation points from previous browse
     * @param bool $releaseContinuationPoints If true, releases the points without retrieving results
     * @return BrowseResult[]
     */
    public function browseNext(array $continuationPoints, bool $releaseContinuationPoints = false): array
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before browsing');
        }

        if ($continuationPoints === []) {
            return [];
        }

        $requestHeader = $this->createRequestHeader();

        $request = BrowseNextRequest::create(
            continuationPoints: $continuationPoints,
            releaseContinuationPoints: $releaseContinuationPoints,
            requestHeader: $requestHeader
        );

        /** @var BrowseNextResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, BrowseNextResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "BrowseNext failed: {$response->responseHeader->serviceResult}"
            );
        }

        return $response->results;
    }

    /**
     * Browse with automatic continuation point handling
     *
     * This method automatically calls BrowseNext when continuation points are returned,
     * gathering all results until complete. Useful for browsing large address spaces.
     *
     * @param BrowseDescription $browseDescription Browse parameters
     * @param int $maxReferencesPerNode Maximum references per BrowseNext call (0 = unlimited)
     * @return BrowseResult Aggregated result with all references
     */
    public function managedBrowse(
        BrowseDescription $browseDescription,
        int $maxReferencesPerNode = 1000
    ): BrowseResult {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before browsing');
        }

        // Initial browse
        $requestHeader = $this->createRequestHeader();
        $request = BrowseRequest::forNode(
            $browseDescription,
            requestedMaxReferencesPerNode: $maxReferencesPerNode,
            requestHeader: $requestHeader
        );

        /** @var BrowseResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, BrowseResponse::class);

        if ($response->results === []) {
            throw new RuntimeException('Server returned empty browse results');
        }

        $result = $response->results[0];
        $allReferences = $result->references;
        $statusCode = $result->statusCode;

        // Check if there are continuation points
        $continuationPoint = $result->continuationPoint;

        if ($continuationPoint === null || $continuationPoint === '') {
            // No continuation needed
            return $result;
        }

        // Keep fetching with BrowseNext until no more continuation points
        $maxIterations = 1000; // Safety limit to prevent infinite loops
        $iterations = 0;

        while ($continuationPoint !== null && $continuationPoint !== '') {
            if ($iterations >= $maxIterations) {
                // Release the continuation point before throwing
                try {
                    $this->browseNext([$continuationPoint], releaseContinuationPoints: true);
                } catch (Throwable) {
                    // Ignore errors during cleanup
                }
                throw new RuntimeException(
                    "Browse iteration limit exceeded ($maxIterations). " .
                    "Possible infinite loop or extremely large result set."
                );
            }

            $nextResults = $this->browseNext([$continuationPoint]);

            if ($nextResults === []) {
                break;
            }

            $nextResult = $nextResults[0];

            // Check for errors
            if (!$nextResult->statusCode->isGood()) {
                // Try to release continuation point on error
                try {
                    if ($nextResult->continuationPoint !== null && $nextResult->continuationPoint !== '') {
                        $this->browseNext([$nextResult->continuationPoint], releaseContinuationPoints: true);
                    }
                } catch (Throwable) {
                    // Ignore errors during cleanup
                }
                throw new RuntimeException(
                    "BrowseNext failed: {$nextResult->statusCode}"
                );
            }

            // Accumulate references
            $allReferences = array_merge($allReferences, $nextResult->references);

            // Get next continuation point
            $continuationPoint = $nextResult->continuationPoint;
            $iterations++;
        }

        // Return aggregated result
        return new BrowseResult(
            statusCode: $statusCode,
            continuationPoint: null, // All results retrieved
            references: $allReferences
        );
    }

    /**
     * Read one or more node attributes.
     *
     * @param array<int, NodeId|ReadValueId> $nodes
     * @return DataValue[]
     */
    public function read(
        array $nodes,
        float $maxAge = 0.0,
        TimestampsToReturn $timestampsToReturn = TimestampsToReturn::Both,
    ): array {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before reading');
        }

        if ($nodes === []) {
            throw new InvalidArgumentException('Read requires at least one node.');
        }

        $readValues = [];
        foreach ($nodes as $node) {
            if ($node instanceof ReadValueId) {
                $readValues[] = $node;
                continue;
            }

            if ($node instanceof NodeId) {
                $readValues[] = ReadValueId::attribute($node);
                continue;
            }

            throw new InvalidArgumentException(
                'Read nodes must be instances of NodeId or ReadValueId.'
            );
        }

        $request = ReadRequest::create(
            nodesToRead: $readValues,
            requestHeader: $this->createRequestHeader(),
            maxAge: $maxAge,
            timestampsToReturn: $timestampsToReturn,
        );

        /** @var ReadResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, ReadResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "ReadService failed: {$response->responseHeader->serviceResult}"
            );
        }

        return $response->results;
    }

    /**
     * Write one or more values.
     *
     * @param WriteValue[] $values
     * @return StatusCode[]
     */
    public function write(array $values): array
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before writing');
        }

        if ($values === []) {
            throw new InvalidArgumentException('Write requires at least one WriteValue.');
        }

        foreach ($values as $value) {
            if (!$value instanceof WriteValue) {
                throw new InvalidArgumentException('Write array must contain only WriteValue instances.');
            }
        }

        $request = WriteRequest::create(
            nodesToWrite: $values,
            requestHeader: $this->createRequestHeader(),
        );

        /** @var WriteResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, WriteResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "WriteService failed: {$response->responseHeader->serviceResult}"
            );
        }

        return $response->results;
    }

    /**
     * Call one or more server methods.
     *
     * @param CallMethodRequest[] $methodsToCall
     * @return CallMethodResult[]
     */
    public function call(array $methodsToCall): array
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before calling methods');
        }

        if ($methodsToCall === []) {
            throw new InvalidArgumentException('Call requires at least one CallMethodRequest.');
        }

        foreach ($methodsToCall as $method) {
            if (!$method instanceof CallMethodRequest) {
                throw new InvalidArgumentException('methodsToCall must only contain CallMethodRequest instances.');
            }
        }

        $request = CallRequest::create(
            methodsToCall: $methodsToCall,
            requestHeader: $this->createRequestHeader(),
        );

        /** @var CallResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, CallResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "CallService failed: {$response->responseHeader->serviceResult}"
            );
        }

        return $response->results;
    }

    /**
     * Call a single server method.
     *
     * @param Variant[] $inputArguments
     * @return Variant[] Output arguments
     */
    public function callMethod(
        NodeId $objectId,
        NodeId $methodId,
        array $inputArguments = [],
    ): array {
        $methodRequest = CallMethodRequest::create(
            objectId: $objectId,
            methodId: $methodId,
            inputArguments: $inputArguments,
        );

        $results = $this->call([$methodRequest]);

        if ($results === []) {
            throw new RuntimeException('Server returned empty call results');
        }

        $result = $results[0];

        if (!$result->statusCode->isGood()) {
            throw new RuntimeException(
                "Method call failed: {$result->statusCode}"
            );
        }

        return $result->outputArguments;
    }

    /**
     * Read historical data for a node.
     *
     * @param NodeId $nodeId The node to read history for
     * @param ReadRawModifiedDetails $details The read details (time range, etc.)
     * @param TimestampsToReturn $timestampsToReturn Which timestamps to return
     * @return HistoryReadResult The history read result
     */
    public function readHistory(
        NodeId $nodeId,
        ReadRawModifiedDetails $details,
        TimestampsToReturn $timestampsToReturn = TimestampsToReturn::Both,
    ): HistoryReadResult {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before reading history');
        }

        $nodeToRead = new HistoryReadValueId(
            nodeId: $nodeId,
            indexRange: null,
            dataEncoding: null,
            continuationPoint: null
        );

        $request = HistoryReadRequest::forRawData(
            details: $details,
            nodesToRead: [$nodeToRead],
            timestampsToReturn: $timestampsToReturn
        );

        /** @var HistoryReadResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, HistoryReadResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "HistoryRead failed: {$response->responseHeader->serviceResult}"
            );
        }

        if ($response->results === []) {
            throw new RuntimeException('Server returned empty history results');
        }

        $result = $response->results[0];

        if (!$result->statusCode->isGood()) {
            throw new RuntimeException("HistoryRead result bad: {$result->statusCode}");
        }

        return $result;
    }

    /**
     * Translate browse paths to NodeIds.
     *
     * Resolves path-based references (like "Objects/Server/ServerStatus") to NodeIds.
     *
     * @param BrowsePath[] $browsePaths Paths to translate
     * @return BrowsePathResult[] Results for each path
     */
    public function translateBrowsePaths(array $browsePaths): array
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before translating browse paths');
        }

        if ($browsePaths === []) {
            return [];
        }

        foreach ($browsePaths as $path) {
            if (!$path instanceof BrowsePath) {
                throw new InvalidArgumentException('Paths must be BrowsePath instances');
            }
        }

        $request = TranslateBrowsePathsToNodeIdsRequest::create(
            browsePaths: $browsePaths,
            requestHeader: $this->createRequestHeader(),
        );

        /** @var TranslateBrowsePathsToNodeIdsResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, TranslateBrowsePathsToNodeIdsResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "TranslateBrowsePaths failed: {$response->responseHeader->serviceResult}"
            );
        }

        return $response->results;
    }

    /**
     * Register nodes for repeated access optimization.
     *
     * Tells the server that these nodes will be accessed frequently,
     * allowing it to create optimized aliases for faster access.
     *
     * @param NodeId[] $nodesToRegister Nodes to register
     * @return NodeId[] Alias NodeIds to use for subsequent operations
     */
    public function registerNodes(array $nodesToRegister): array
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before registering nodes');
        }

        if ($nodesToRegister === []) {
            return [];
        }

        foreach ($nodesToRegister as $nodeId) {
            if (!$nodeId instanceof NodeId) {
                throw new InvalidArgumentException('Nodes must be NodeId instances');
            }
        }

        $request = RegisterNodesRequest::create(
            nodesToRegister: $nodesToRegister,
            requestHeader: $this->createRequestHeader(),
        );

        /** @var RegisterNodesResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, RegisterNodesResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "RegisterNodes failed: {$response->responseHeader->serviceResult}"
            );
        }

        return $response->registeredNodeIds;
    }

    /**
     * Unregister previously registered nodes.
     *
     * Frees server resources for registered nodes that are no longer needed.
     *
     * @param NodeId[] $nodesToUnregister Registered nodes to unregister
     */
    public function unregisterNodes(array $nodesToUnregister): void
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before unregistering nodes');
        }

        if ($nodesToUnregister === []) {
            return;
        }

        foreach ($nodesToUnregister as $nodeId) {
            if (!$nodeId instanceof NodeId) {
                throw new InvalidArgumentException('Nodes must be NodeId instances');
            }
        }

        $request = UnregisterNodesRequest::create(
            nodesToUnregister: $nodesToUnregister,
            requestHeader: $this->createRequestHeader(),
        );

        /** @var UnregisterNodesResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, UnregisterNodesResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "UnregisterNodes failed: {$response->responseHeader->serviceResult}"
            );
        }
    }

    /**
     * Create a subscription on the server.
     */
    public function createSubscription(
        float $publishingInterval = 1000.0,
        int $lifetimeCount = 10000,
        int $maxKeepAliveCount = 10,
        int $maxNotificationsPerPublish = 0,
        bool $publishingEnabled = true,
        int $priority = 0,
    ): Subscription {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before creating subscriptions');
        }

        $subscription = new Subscription(
            secureChannel: $this->secureChannel,
            requestedPublishingInterval: $publishingInterval,
            requestedLifetimeCount: $lifetimeCount,
            requestedMaxKeepAliveCount: $maxKeepAliveCount,
            maxNotificationsPerPublish: $maxNotificationsPerPublish,
            publishingEnabled: $publishingEnabled,
            priority: $priority,
        );

        $subscription->create();

        // Track subscription
        $subscriptionId = $subscription->getSubscriptionId();
        if ($subscriptionId === null) {
            throw new RuntimeException('Subscription creation failed - no ID assigned');
        }
        $this->subscriptions[$subscriptionId] = $subscription;

        return $subscription;
    }

    /**
     * Add an already-created subscription to this session.
     */
    public function addSubscription(Subscription $subscription): void
    {
        if (!$subscription->isCreated()) {
            throw new RuntimeException('Subscription must be created before adding to session');
        }

        $subscriptionId = $subscription->getSubscriptionId();
        if ($subscriptionId === null) {
            throw new RuntimeException('Subscription has no ID - cannot add to session');
        }
        $this->subscriptions[$subscriptionId] = $subscription;
    }

    /**
     * Remove a subscription from this session.
     */
    public function removeSubscription(int $subscriptionId): void
    {
        unset($this->subscriptions[$subscriptionId]);
    }

    /**
     * Get all subscriptions managed by this session.
     *
     * @return Subscription[]
     */
    public function getSubscriptions(): array
    {
        return array_values($this->subscriptions);
    }

    /**
     * Send a publish request and process notifications.
     *
     * This method should be called periodically to receive notifications
     * from active subscriptions.
     *
     * @return bool True if notifications were received, false otherwise
     */
    public function publish(): bool
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before publishing');
        }

        if ($this->subscriptions === []) {
            return false; // No subscriptions to publish
        }

        // Check keep-alive before publishing
        if ($this->keepAliveEnabled) {
            $this->checkKeepAlive();
        }

        // Trim acknowledgements if needed
        if (count($this->acknowledgements) > $this->maxAcknowledgements) {
            $this->acknowledgements = array_slice(
                $this->acknowledgements,
                -$this->maxAcknowledgements
            );
        }

        try {
            $request = PublishRequest::create(
                subscriptionAcknowledgements: $this->acknowledgements,
                requestHeader: $this->createRequestHeader(),
            );

            /** @var PublishResponse $response */
            $response = $this->secureChannel->sendServiceRequest($request, PublishResponse::class);

            if (!$response->responseHeader->serviceResult->isGood()) {
                throw new RuntimeException(
                    "Publish failed: {$response->responseHeader->serviceResult}"
                );
            }

            // Update publish time for keep-alive tracking
            $this->lastPublishTime = microtime(true);

            // Check if this is a keep-alive notification (empty notification data)
            $isKeepAlive = $response->notificationMessage->notificationData === [];
            if ($isKeepAlive) {
                $this->handleKeepAlive();
            }

            // Route notification to appropriate subscription
            $subscriptionId = $response->subscriptionId;
            if (isset($this->subscriptions[$subscriptionId])) {
                $this->subscriptions[$subscriptionId]->processNotificationMessage(
                    $response->notificationMessage
                );
            }

            // Add acknowledgement for this notification
            $this->acknowledgements[] = new SubscriptionAcknowledgement(
                subscriptionId: $subscriptionId,
                sequenceNumber: $response->notificationMessage->sequenceNumber,
            );

            // Reset missed count on successful publish
            if ($this->keepAliveEnabled) {
                $this->missedKeepAliveCount = 0;
            }

            return true;
        } catch (Throwable $e) {
            // Track missed keep-alive on error
            if ($this->keepAliveEnabled) {
                $this->missedKeepAliveCount++;
                $this->handlePublishError($e);
            }
            return false;
        }
    }

    /**
     * Start continuous publishing loop.
     *
     * This will block and continuously poll for notifications at the specified interval.
     * Call from a separate process/thread or use publishOnce() for manual control.
     *
     * @param float $intervalSeconds Seconds between publish calls
     * @param int $maxIterations Maximum iterations (0 = infinite)
     */
    public function startPublishing(float $intervalSeconds = 0.1, int $maxIterations = 0): void
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before starting publish loop');
        }

        $this->publishingActive = true;
        $iteration = 0;

        while ($this->publishingActive) {
            if ($maxIterations > 0 && $iteration >= $maxIterations) {
                break;
            }

            $this->publish();

            // Sleep for interval (convert to microseconds)
            usleep((int)($intervalSeconds * 1_000_000));

            $iteration++;
        }
    }

    /**
     * Stop the publishing loop.
     */
    public function stopPublishing(): void
    {
        $this->publishingActive = false;
    }

    /**
     * Check if publishing is active.
     */
    public function isPublishing(): bool
    {
        return $this->publishingActive;
    }

    /**
     * Detect server capabilities by reading ServerCapabilities node
     *
     * Reads operational limits from the server's ServerCapabilities node
     * to allow the client to auto-configure batch sizes and features.
     *
     * @return ServerCapabilities Server operational limits and features
     */
    public function detectServerCapabilities(): ServerCapabilities
    {
        if (!$this->isActive) {
            throw new RuntimeException('Session must be activated before detecting capabilities');
        }

        // ServerCapabilities node: ns=0;i=2268
        $serverCapabilitiesNode = NodeId::numeric(0, 2268);

        // Read known capability properties
        $nodesToRead = [
            NodeId::numeric(0, 2733), // MaxBrowseContinuationPoints
            NodeId::numeric(0, 2735), // MaxQueryContinuationPoints
            NodeId::numeric(0, 2736), // MaxHistoryContinuationPoints
            NodeId::numeric(0, 2737), // MaxArrayLength
            NodeId::numeric(0, 2738), // MaxStringLength
            NodeId::numeric(0, 2739), // MaxByteStringLength
            NodeId::numeric(0, 11705), // MaxNodesPerRead (optional - may not exist on all servers)
            NodeId::numeric(0, 11707), // MaxNodesPerWrite
            NodeId::numeric(0, 11709), // MaxNodesPerMethodCall
            NodeId::numeric(0, 11710), // MaxNodesPerBrowse
            NodeId::numeric(0, 11711), // MaxNodesPerRegisterNodes
            NodeId::numeric(0, 11712), // MaxNodesPerTranslateBrowsePathsToNodeIds
            NodeId::numeric(0, 11713), // MaxNodesPerNodeManagement
            NodeId::numeric(0, 11714), // MaxMonitoredItemsPerCall
        ];

        try {
            $values = $this->read($nodesToRead);

            $capabilities = [];
            $capabilities['maxBrowseContinuationPoints'] = ($values[0]->statusCode?->isGood() ?? false)
                ? $values[0]->value?->value : null;
            $capabilities['maxQueryContinuationPoints'] = ($values[1]->statusCode?->isGood() ?? false)
                ? $values[1]->value?->value : null;
            $capabilities['maxHistoryContinuationPoints'] = ($values[2]->statusCode?->isGood() ?? false)
                ? $values[2]->value?->value : null;
            $capabilities['maxArrayLength'] = ($values[3]->statusCode?->isGood() ?? false)
                ? $values[3]->value?->value : null;
            $capabilities['maxStringLength'] = ($values[4]->statusCode?->isGood() ?? false)
                ? $values[4]->value?->value : null;
            $capabilities['maxByteStringLength'] = ($values[5]->statusCode?->isGood() ?? false)
                ? $values[5]->value?->value : null;
            $capabilities['maxNodesPerRead'] = ($values[6]->statusCode?->isGood() ?? false)
                ? $values[6]->value?->value : null;
            $capabilities['maxNodesPerWrite'] = ($values[7]->statusCode?->isGood() ?? false)
                ? $values[7]->value?->value : null;
            $capabilities['maxNodesPerMethodCall'] = ($values[8]->statusCode?->isGood() ?? false)
                ? $values[8]->value?->value : null;
            $capabilities['maxNodesPerBrowse'] = ($values[9]->statusCode?->isGood() ?? false)
                ? $values[9]->value?->value : null;
            $capabilities['maxNodesPerRegisterNodes'] = ($values[10]->statusCode?->isGood() ?? false)
                ? $values[10]->value?->value : null;
            $capabilities['maxNodesPerTranslateBrowsePathsToNodeIds'] =
                ($values[11]->statusCode?->isGood() ?? false) ? $values[11]->value?->value : null;
            $capabilities['maxNodesPerNodeManagement'] = ($values[12]->statusCode?->isGood() ?? false)
                ? $values[12]->value?->value : null;
            $capabilities['maxMonitoredItemsPerCall'] = ($values[13]->statusCode?->isGood() ?? false)
                ? $values[13]->value?->value : null;

            return ServerCapabilities::fromArray($capabilities);
        } catch (Throwable $e) {
            // If detection fails, return conservative defaults
            return ServerCapabilities::defaults();
        }
    }

    /**
     * Enable automatic batch splitting for read/write operations
     *
     * When enabled, large read/write operations will be automatically split
     * into multiple batches that respect server operational limits.
     *
     * @param bool $detectCapabilities If true, detect server capabilities now
     */
    public function enableAutoBatchSplitting(bool $detectCapabilities = true): void
    {
        $this->autoBatchSplitting = true;

        if ($detectCapabilities && $this->serverCapabilities === null) {
            $this->serverCapabilities = $this->detectServerCapabilities();
        }
    }

    /**
     * Disable automatic batch splitting
     */
    public function disableAutoBatchSplitting(): void
    {
        $this->autoBatchSplitting = false;
    }

    /**
     * Check if automatic batch splitting is enabled
     */
    public function isAutoBatchSplittingEnabled(): bool
    {
        return $this->autoBatchSplitting;
    }

    /**
     * Get server capabilities (detects if not cached)
     */
    public function getServerCapabilities(): ServerCapabilities
    {
        if ($this->serverCapabilities === null) {
            $this->serverCapabilities = $this->detectServerCapabilities();
        }
        return $this->serverCapabilities;
    }

    /**
     * Read with automatic batch splitting
     *
     * If auto-batching is enabled and the number of nodes exceeds server limits,
     * the operation will be automatically split into multiple batches.
     *
     * @param array<int, NodeId|ReadValueId> $nodes
     * @param float $maxAge
     * @param TimestampsToReturn $timestampsToReturn
     * @param callable|null $progressCallback Optional callback: function(int $completed, int $total): void
     * @return DataValue[]
     */
    public function readBatched(
        array $nodes,
        float $maxAge = 0.0,
        TimestampsToReturn $timestampsToReturn = TimestampsToReturn::Both,
        ?callable $progressCallback = null,
    ): array {
        if (!$this->autoBatchSplitting) {
            return $this->read($nodes, $maxAge, $timestampsToReturn);
        }

        $capabilities = $this->getServerCapabilities();
        $batchSize = $capabilities->getSafeReadBatchSize();

        if (count($nodes) <= $batchSize || $batchSize < 1) {
            return $this->read($nodes, $maxAge, $timestampsToReturn);
        }

        // Split into batches
        $batches = array_chunk($nodes, $batchSize);
        $allResults = [];
        $completed = 0;
        $total = count($nodes);

        foreach ($batches as $batch) {
            $results = $this->read($batch, $maxAge, $timestampsToReturn);
            $allResults = array_merge($allResults, $results);

            $completed += count($batch);
            if ($progressCallback !== null) {
                $progressCallback($completed, $total);
            }
        }

        return $allResults;
    }

    /**
     * Write with automatic batch splitting
     *
     * If auto-batching is enabled and the number of values exceeds server limits,
     * the operation will be automatically split into multiple batches.
     *
     * @param WriteValue[] $values
     * @param callable|null $progressCallback Optional callback: function(int $completed, int $total): void
     * @return StatusCode[]
     */
    public function writeBatched(
        array $values,
        ?callable $progressCallback = null,
    ): array {
        if (!$this->autoBatchSplitting) {
            return $this->write($values);
        }

        $capabilities = $this->getServerCapabilities();
        $batchSize = $capabilities->getSafeWriteBatchSize();

        if (count($values) <= $batchSize || $batchSize < 1) {
            return $this->write($values);
        }

        // Split into batches
        $batches = array_chunk($values, $batchSize);
        $allResults = [];
        $completed = 0;
        $total = count($values);

        foreach ($batches as $batch) {
            $results = $this->write($batch);
            $allResults = array_merge($allResults, $results);

            $completed += count($batch);
            if ($progressCallback !== null) {
                $progressCallback($completed, $total);
            }
        }

        return $allResults;
    }

    /**
     * Register nodes with automatic batch splitting
     *
     * @param NodeId[] $nodesToRegister
     * @return NodeId[]
     */
    public function registerNodesBatched(array $nodesToRegister): array
    {
        if (!$this->autoBatchSplitting) {
            return $this->registerNodes($nodesToRegister);
        }

        $capabilities = $this->getServerCapabilities();
        $batchSize = $capabilities->getSafeRegisterNodesBatchSize();

        if (count($nodesToRegister) <= $batchSize || $batchSize < 1) {
            return $this->registerNodes($nodesToRegister);
        }

        // Split into batches
        $batches = array_chunk($nodesToRegister, $batchSize);
        $allResults = [];

        foreach ($batches as $batch) {
            $results = $this->registerNodes($batch);
            $allResults = array_merge($allResults, $results);
        }

        return $allResults;
    }

    /**
     * Close the session
     */
    public function close(): void
    {
        if ($this->sessionId === null) {
            return; // Nothing to close
        }

        // Stop publishing
        $this->stopPublishing();

        // Delete all subscriptions
        foreach ($this->subscriptions as $subscription) {
            try {
                $subscription->delete();
            } catch (Throwable $e) {
                // Ignore errors during cleanup
            }
        }
        $this->subscriptions = [];

        try {
            $requestHeader = $this->createRequestHeader();
            $request = CloseSessionRequest::create($requestHeader);

            /** @var CloseSessionResponse $response */
            $response = $this->secureChannel->sendServiceRequest($request, CloseSessionResponse::class);
        } catch (Throwable $e) {
            // Ignore errors during close
        }

        $this->isActive = false;
        $this->sessionId = null;
        $this->authenticationToken = null;
        $this->secureChannel->setAuthenticationToken(null);
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Get session ID
     */
    public function getSessionId(): ?NodeId
    {
        return $this->sessionId;
    }

    /**
     * Get authentication token
     */
    public function getAuthenticationToken(): ?NodeId
    {
        return $this->authenticationToken;
    }

    /**
     * Get the secure channel used by this session.
     */
    public function getSecureChannel(): SecureChannel
    {
        return $this->secureChannel;
    }

    /**
     * Enable keep-alive monitoring.
     *
     * @param float $intervalSeconds Keep-alive check interval
     * @param int $maxMissed Maximum missed keep-alives before connection lost
     */
    public function enableKeepAlive(float $intervalSeconds = 5.0, int $maxMissed = 3): void
    {
        $this->keepAliveEnabled = true;
        $this->keepAliveInterval = $intervalSeconds;
        $this->maxMissedKeepAlives = $maxMissed;
        $this->lastPublishTime = microtime(true);
        $this->lastKeepAliveTime = microtime(true);
        $this->missedKeepAliveCount = 0;
    }

    /**
     * Disable keep-alive monitoring.
     */
    public function disableKeepAlive(): void
    {
        $this->keepAliveEnabled = false;
    }

    /**
     * Check if keep-alive is enabled.
     */
    public function isKeepAliveEnabled(): bool
    {
        return $this->keepAliveEnabled;
    }

    /**
     * Set callback for missed keep-alive.
     *
     * @param callable $callback function(Session): void
     */
    public function setKeepAliveMissedCallback(callable $callback): void
    {
        $this->onKeepAliveMissed = $callback;
    }

    /**
     * Set callback for connection lost.
     *
     * @param callable $callback function(Session): void
     */
    public function setConnectionLostCallback(callable $callback): void
    {
        $this->onConnectionLost = $callback;
    }

    /**
     * Get the number of consecutive missed keep-alives.
     */
    public function getMissedKeepAliveCount(): int
    {
        return $this->missedKeepAliveCount;
    }

    /**
     * Get time since last successful publish (in seconds).
     */
    public function getTimeSinceLastPublish(): float
    {
        if ($this->lastPublishTime === 0.0) {
            return 0.0;
        }
        return microtime(true) - $this->lastPublishTime;
    }

    /**
     * Check keep-alive status and invoke callbacks if needed.
     */
    private function checkKeepAlive(): void
    {
        $now = microtime(true);
        $timeSinceLastPublish = $now - $this->lastPublishTime;

        // Check if we've exceeded the keep-alive interval
        if ($timeSinceLastPublish > $this->keepAliveInterval) {
            $this->missedKeepAliveCount++;

            // Invoke missed callback
            if ($this->onKeepAliveMissed !== null) {
                ($this->onKeepAliveMissed)($this);
            }

            // Check if connection is lost
            if ($this->missedKeepAliveCount >= $this->maxMissedKeepAlives) {
                $this->handleConnectionLost();
            }

            // Reset timer
            $this->lastPublishTime = $now;
        }
    }

    /**
     * Handle keep-alive notification from server.
     */
    private function handleKeepAlive(): void
    {
        $this->lastKeepAliveTime = microtime(true);
        $this->missedKeepAliveCount = 0;
    }

    /**
     * Handle publish error during keep-alive monitoring.
     */
    private function handlePublishError(Throwable $e): void
    {
        // Check if connection appears lost
        if ($this->missedKeepAliveCount >= $this->maxMissedKeepAlives) {
            $this->handleConnectionLost();
        }
    }

    /**
     * Handle connection lost condition.
     */
    private function handleConnectionLost(): void
    {
        if ($this->onConnectionLost !== null) {
            ($this->onConnectionLost)($this);
        }

        // Attempt automatic reconnection if enabled
        if ($this->autoReconnect && $this->reconnectHandler !== null) {
            $this->reconnectHandler->reconnect();
        }
    }

    /**
     * Enable automatic reconnection.
     *
     * @param float $minDelay Minimum delay between reconnect attempts (seconds)
     * @param float $maxDelay Maximum delay between reconnect attempts (seconds)
     * @param int $maxAttempts Maximum number of reconnection attempts
     * @param float $backoffMultiplier Exponential backoff multiplier
     */
    public function enableAutoReconnect(
        float $minDelay = 1.0,
        float $maxDelay = 60.0,
        int $maxAttempts = 10,
        float $backoffMultiplier = 2.0,
    ): void {
        if ($this->client === null) {
            throw new RuntimeException('Cannot enable auto-reconnect: client not provided to Session');
        }

        $this->autoReconnect = true;

        if ($this->reconnectHandler === null) {
            $this->reconnectHandler = new SessionReconnectHandler($this->client, $this);
        }

        $this->reconnectHandler->configure(
            minDelay: $minDelay,
            maxDelay: $maxDelay,
            maxAttempts: $maxAttempts,
            backoffMultiplier: $backoffMultiplier,
        );
    }

    /**
     * Disable automatic reconnection.
     */
    public function disableAutoReconnect(): void
    {
        $this->autoReconnect = false;
    }

    /**
     * Check if automatic reconnection is enabled.
     */
    public function isAutoReconnectEnabled(): bool
    {
        return $this->autoReconnect;
    }

    /**
     * Get the reconnect handler instance.
     */
    public function getReconnectHandler(): ?SessionReconnectHandler
    {
        return $this->reconnectHandler;
    }

    /**
     * Manually trigger reconnection.
     *
     * @return bool True if reconnection succeeded
     */
    public function reconnect(): bool
    {
        if ($this->reconnectHandler === null) {
            if ($this->client === null) {
                throw new RuntimeException('Cannot reconnect: client not provided to Session');
            }
            $this->reconnectHandler = new SessionReconnectHandler($this->client, $this);
        }

        return $this->reconnectHandler->reconnect();
    }

    /**
     * Find the appropriate policyId for a given token type from the server's endpoint
     *
     * @param UserTokenType $tokenType The type of user token to find a policy for
     * @return string The policyId to use
     * @throws RuntimeException If no policy is found for the given token type
     */
    private function findPolicyIdForTokenType(UserTokenType $tokenType): string
    {
        $endpoint = $this->secureChannel->getSelectedEndpoint();

        if ($endpoint === null) {
            throw new RuntimeException('No endpoint selected - cannot determine policy ID');
        }

        // Find the first policy that matches the requested token type
        foreach ($endpoint->userIdentityTokens as $tokenPolicy) {
            if ($tokenPolicy->tokenType === $tokenType) {
                return $tokenPolicy->policyId;
            }
        }

        throw new RuntimeException(
            "No user token policy found for token type: {$tokenType->name}"
        );
    }

    public function createRequestHeader(): RequestHeader
    {
        $requestHeader = RequestHeader::create();

        if ($this->authenticationToken === null) {
            return $requestHeader;
        }

        return new RequestHeader(
            authenticationToken: $this->authenticationToken,
            timestamp: $requestHeader->timestamp,
            requestHandle: $requestHeader->requestHandle,
            returnDiagnostics: $requestHeader->returnDiagnostics,
            auditEntryId: $requestHeader->auditEntryId,
            timeoutHint: $requestHeader->timeoutHint,
            additionalHeader: $requestHeader->additionalHeader,
        );
    }

    public function __destruct()
    {
        $this->close();
    }
}
