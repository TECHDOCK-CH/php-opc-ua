<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Messages\CloseSecureChannelRequest;
use TechDock\OpcUa\Core\Messages\EndpointDescription;
use TechDock\OpcUa\Core\Messages\GetEndpointsRequest;
use TechDock\OpcUa\Core\Messages\GetEndpointsResponse;
use TechDock\OpcUa\Core\Messages\OpenSecureChannelRequest;
use TechDock\OpcUa\Core\Messages\OpenSecureChannelResponse;
use TechDock\OpcUa\Core\Messages\RequestHeader;
use TechDock\OpcUa\Core\Messages\ServiceFault;
use TechDock\OpcUa\Core\Messages\ServiceRequest;
use TechDock\OpcUa\Core\Transport\AcknowledgeMessage;
use TechDock\OpcUa\Core\Transport\ErrorMessage;
use TechDock\OpcUa\Core\Transport\HelloMessage;
use TechDock\OpcUa\Core\Transport\MessageChunkReader;
use TechDock\OpcUa\Core\Transport\MessageHeader;
use TechDock\OpcUa\Core\Transport\MessageType;
use TechDock\OpcUa\Core\Transport\TcpConnectionInterface;
use TechDock\OpcUa\Core\Types\NodeId;
use Throwable;

/**
 * Manages a secure channel with an OPC UA server
 */
final class SecureChannel
{
    private bool $isOpen = false;
    private ?ChannelSecurityToken $securityToken = null;
    private int $sequenceNumber = 0;
    private int $requestId = 0;
    /** @var EndpointDescription[] */
    private array $availableEndpoints = [];
    private ?EndpointDescription $selectedEndpoint = null;

    // Encryption state
    private ?string $clientNonce = null;
    private ?string $serverNonce = null;
    private ?ChannelSecurityKeys $currentKeys = null;
    private ?SecurityPolicyHandlerInterface $securityHandler = null;

    // Authentication token (mirrors C# ClientBase.AuthenticationToken)
    private ?NodeId $authenticationToken = null;

    // Sequence number validation
    private int $lastReceivedSequenceNumber = 0;
    private bool $sequenceNumberRolledOver = false;

    private int $receiveBufferSize = 0;
    private int $sendBufferSize = 0;
    private int $maxMessageSize = 0;
    private int $maxChunkCount = 0;

    public function __construct(
        private readonly TcpConnectionInterface $connection,
        private readonly MessageSecurityMode $securityMode = MessageSecurityMode::None,
        private readonly SecurityPolicy $securityPolicy = SecurityPolicy::None,
        private readonly ?CertificateValidator $certificateValidator = null,
    ) {
    }

    /**
     * Open the secure channel (perform handshake)
     */
    public function open(): void
    {
        if ($this->isOpen) {
            throw new RuntimeException('Secure channel is already open');
        }

        // Step 1: Connect TCP
        if (!$this->connection->isConnected()) {
            $this->connection->connect();
        }

        // Step 2: Send Hello message
        $hello = HelloMessage::create($this->connection->getEndpointUrl());
        $this->connection->send($hello->encode());

        // Step 3: Receive Acknowledge or Error
        $header = $this->connection->receiveHeader();

        if ($header->messageType === MessageType::Error) {
            $payload = $this->connection->receive($header->getPayloadSize());
            $headerEncoder = new BinaryEncoder();
            $header->encode($headerEncoder);
            $decoder = new BinaryDecoder($headerEncoder->getBytes() . $payload);
            $error = ErrorMessage::decode($decoder);
            throw new RuntimeException("Server returned error: {$error->reason}");
        }

        if ($header->messageType !== MessageType::Acknowledge) {
            throw new RuntimeException("Expected ACK, got {$header->messageType->value}");
        }

        $payload = $this->connection->receive($header->getPayloadSize());
        $headerEncoder = new BinaryEncoder();
        $header->encode($headerEncoder);
        $decoder = new BinaryDecoder($headerEncoder->getBytes() . $payload);
        $ack = AcknowledgeMessage::decode($decoder);
        $this->receiveBufferSize = $ack->receiveBufferSize;
        $this->sendBufferSize = $ack->sendBufferSize;
        $this->maxMessageSize = $ack->maxMessageSize;
        $this->maxChunkCount = $ack->maxChunkCount;

        // Step 4: Send OpenSecureChannelRequest wrapped in OPN message (BEFORE GetEndpoints!)
        // Generate client nonce only when security mode is not None
        $this->clientNonce = $this->securityMode !== MessageSecurityMode::None
            ? random_bytes(32)
            : null;

        $openRequest = OpenSecureChannelRequest::issue(
            securityMode: $this->securityMode,
            clientNonce: $this->clientNonce,
            requestedLifetime: 600000, // 10 minutes
        );

        $this->sendOpenSecureChannelRequest($openRequest);

        // Step 5: Receive OpenSecureChannelResponse
        $response = $this->receiveOpenSecureChannelResponse();

        // Step 6: Store security token and server nonce
        $this->securityToken = $response->securityToken;
        $this->serverNonce = $response->serverNonce;

        // Step 6a: Derive encryption keys if security is enabled
        if (
            $this->securityMode !== MessageSecurityMode::None
            && $this->clientNonce !== null
            && $this->serverNonce !== null
        ) {
            // Create security handler for this policy
            $this->securityHandler = SecurityPolicyFactory::createHandler($this->securityPolicy);

            // Derive session keys from nonces
            $this->currentKeys = ChannelSecurityKeys::derive(
                clientNonce: $this->clientNonce,
                serverNonce: $this->serverNonce,
                tokenId: $this->securityToken->tokenId,
                handler: $this->securityHandler
            );
        }

        // Step 7: Call GetEndpoints to discover server policies (AFTER OpenSecureChannel!)
        $getEndpointsRequest = new GetEndpointsRequest(
            requestHeader: RequestHeader::create(requestHandle: $this->nextRequestId()),
            endpointUrl: $this->connection->getEndpointUrl(),
        );
        $endpointsResponse = $this->sendServiceRequest($getEndpointsRequest, GetEndpointsResponse::class);
        $this->availableEndpoints = array_map(
            fn(EndpointDescription $endpoint): EndpointDescription => $this->normalizeEndpointUrl($endpoint),
            $endpointsResponse->endpoints,
        );
        $this->selectedEndpoint = $this->selectEndpoint($this->availableEndpoints);

        // Step 8: Validate server certificate if validator is configured
        if (
            $this->certificateValidator !== null
            && $this->selectedEndpoint->serverCertificate !== null
            && $this->selectedEndpoint->serverCertificate !== ''
        ) {
            try {
                $this->certificateValidator->validate($this->selectedEndpoint->serverCertificate);
            } catch (CertificateValidationException $e) {
                throw new RuntimeException(
                    "Server certificate validation failed: {$e->getMessage()}"
                );
            }
        }

        $this->isOpen = true;
    }

    /**
     * Check if the secure channel is open
     */
    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    /**
     * Close the secure channel
     */
    public function close(): void
    {
        if (!$this->isOpen) {
            return;
        }

        // Send CloseSecureChannelRequest wrapped in CLO message
        try {
            $closeRequest = CloseSecureChannelRequest::create();
            $this->sendCloseSecureChannelRequest($closeRequest);
        } catch (Throwable $e) {
            // Ignore errors during close
        }

        $this->connection->close();
        $this->isOpen = false;
    }

    /**
     * Get the underlying connection
     */
    public function getConnection(): TcpConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get the current security token
     */
    public function getSecurityToken(): ?ChannelSecurityToken
    {
        return $this->securityToken;
    }

    /**
     * @return EndpointDescription[]
     */
    public function getAvailableEndpoints(): array
    {
        return $this->availableEndpoints;
    }

    public function getSelectedEndpoint(): ?EndpointDescription
    {
        return $this->selectedEndpoint;
    }

    /**
     * Set the session's AuthenticationToken for automatic injection into service requests.
     *
     * Mirrors C# ClientBase.AuthenticationToken — every service request sent through
     * this channel will automatically have the token injected if the request's
     * RequestHeader contains a null AuthenticationToken.
     */
    public function setAuthenticationToken(?NodeId $authenticationToken): void
    {
        $this->authenticationToken = $authenticationToken;
    }

    /**
     * Inject the stored AuthenticationToken into the encoded message body.
     *
     * Replaces the null-token RequestHeader bytes with a new RequestHeader
     * containing the session's AuthenticationToken. Uses deterministic offset
     * calculation based on TypeId length to avoid binary pattern matching.
     *
     * @return string The modified message body with auth token injected
     */
    private function injectAuthenticationToken(
        string $messageBody,
        ServiceRequest $request,
        RequestHeader $originalHeader,
    ): string {
        // TypeId byte length (known position)
        $typeIdEncoder = new BinaryEncoder();
        $request->getTypeId()->encode($typeIdEncoder);
        $typeIdLength = strlen($typeIdEncoder->getBytes());

        // Original header byte length
        $originalEncoder = new BinaryEncoder();
        $originalHeader->encode($originalEncoder);
        $originalHeaderLength = strlen($originalEncoder->getBytes());

        // New header with auth token
        $newHeader = new RequestHeader(
            authenticationToken: $this->authenticationToken ?? NodeId::numeric(0, 0),
            timestamp: $originalHeader->timestamp,
            requestHandle: $originalHeader->requestHandle,
            returnDiagnostics: $originalHeader->returnDiagnostics,
            auditEntryId: $originalHeader->auditEntryId,
            timeoutHint: $originalHeader->timeoutHint,
            additionalHeader: $originalHeader->additionalHeader,
        );
        $newEncoder = new BinaryEncoder();
        $newHeader->encode($newEncoder);
        $newHeaderBytes = $newEncoder->getBytes();

        // Deterministic splice: TypeId bytes + new header + rest of body
        return substr($messageBody, 0, $typeIdLength)
            . $newHeaderBytes
            . substr($messageBody, $typeIdLength + $originalHeaderLength);
    }

    /**
     * Get the next sequence number
     */
    private function nextSequenceNumber(): int
    {
        $this->sequenceNumber++;
        return $this->sequenceNumber;
    }

    /**
     * Get the next request ID
     */
    private function nextRequestId(): int
    {
        $this->requestId++;
        return $this->requestId;
    }

    /**
     * Choose an endpoint that best matches the requested security settings.
     *
     * @param EndpointDescription[] $endpoints
     */
    private function selectEndpoint(array $endpoints): EndpointDescription
    {
        if ($endpoints === []) {
            throw new RuntimeException('Server returned no endpoints for discovery');
        }

        $endpointUrl = $this->connection->getEndpointUrl();

        $candidates = array_values(array_filter(
            $endpoints,
            static fn(EndpointDescription $endpoint): bool => strcasecmp($endpoint->endpointUrl, $endpointUrl) === 0
        ));

        if ($candidates === []) {
            $candidates = $endpoints;
        }

        foreach ($candidates as $endpoint) {
            if (
                $endpoint->securityMode === $this->securityMode
                && $endpoint->securityPolicy === $this->securityPolicy
            ) {
                return $endpoint;
            }
        }

        foreach ($candidates as $endpoint) {
            if ($endpoint->securityMode === $this->securityMode) {
                return $endpoint;
            }
        }

        foreach ($candidates as $endpoint) {
            if ($endpoint->securityPolicy === $this->securityPolicy) {
                return $endpoint;
            }
        }

        return $candidates[0];
    }

    /**
     * Align the endpoint URL with the client-specified host/port while retaining the server's path/query.
     */
    private function normalizeEndpointUrl(EndpointDescription $endpoint): EndpointDescription
    {
        $clientUrl = $this->connection->getEndpointUrl();
        $clientParts = parse_url($clientUrl);
        $endpointParts = parse_url($endpoint->endpointUrl);

        if ($clientParts === false || !isset($clientParts['host'])) {
            return $endpoint;
        }

        $clientPort = $clientParts['port'] ?? null;
        $endpointHost = $endpointParts['host'] ?? null;
        $endpointPort = $endpointParts['port'] ?? null;

        $hostMatches = $endpointHost !== null
            && strcasecmp($endpointHost, $clientParts['host']) === 0;
        $portMatches = ($clientPort === null && $endpointPort === null)
            || ($clientPort !== null && $clientPort === $endpointPort);

        if ($hostMatches && $portMatches) {
            return $endpoint;
        }

        $newUrl = $this->rebuildEndpointUrl($clientParts, $endpointParts);

        return new EndpointDescription(
            endpointUrl: $newUrl,
            server: $endpoint->server,
            serverCertificate: $endpoint->serverCertificate,
            securityPolicy: $endpoint->securityPolicy,
            securityMode: $endpoint->securityMode,
            userIdentityTokens: $endpoint->userIdentityTokens,
            transportProfileUri: $endpoint->transportProfileUri,
            securityLevel: $endpoint->securityLevel,
        );
    }

    /**
     * Build a URL that keeps the server-provided path/query fragments but swaps host/port.
     *
     * @param array<string, mixed> $clientParts
     * @param array<string, mixed>|false $endpointParts
     */
    private function rebuildEndpointUrl(array $clientParts, array|false $endpointParts): string
    {
        $scheme = $clientParts['scheme'] ?? ($endpointParts['scheme'] ?? 'opc.tcp');
        $host = $clientParts['host'] ?? (
        $endpointParts !== false ? ($endpointParts['host'] ?? 'localhost') : 'localhost'
        );
        $port = $clientParts['port'] ?? (
        $endpointParts !== false ? ($endpointParts['port'] ?? null) : null
        );

        $path = $endpointParts !== false ? ($endpointParts['path'] ?? '') : '';
        if ($path === '') {
            $path = '/';
        }

        $query = $endpointParts !== false && isset($endpointParts['query'])
            ? '?' . $endpointParts['query']
            : '';
        $fragment = $endpointParts !== false && isset($endpointParts['fragment'])
            ? '#' . $endpointParts['fragment']
            : '';

        $url = sprintf('%s://%s', $scheme, $host);
        if ($port !== null) {
            $url .= ':' . $port;
        }

        return $url . $path . $query . $fragment;
    }

    /**
     * Send OpenSecureChannelRequest wrapped in OPN message
     */
    private function sendOpenSecureChannelRequest(OpenSecureChannelRequest $request): void
    {
        // Build the message body with TypeId
        $bodyEncoder = new BinaryEncoder();
        // Prepend TypeId for OpenSecureChannelRequest (ns=0;i=446)
        NodeId::numeric(0, 446)->encode($bodyEncoder);
        $request->encode($bodyEncoder);
        $messageBody = $bodyEncoder->getBytes();

        // Build asymmetric security header
        $securityHeader = new AsymmetricSecurityHeader(
            secureChannelId: 0, // 0 for initial OpenSecureChannel request
            securityPolicy: $this->selectedEndpoint->securityPolicy ?? $this->securityPolicy,
            senderCertificate: null,
            receiverCertificateThumbprint: null
        );
        $securityHeaderEncoder = new BinaryEncoder();
        $securityHeader->encode($securityHeaderEncoder);
        $securityHeaderBytes = $securityHeaderEncoder->getBytes();

        $requestId = $this->nextRequestId();

        $this->sendAsymmetricRequestChunks(
            $securityHeaderBytes,
            $messageBody,
            MessageType::OpenSecureChannel,
            $requestId
        );
    }

    /**
     * Receive and decode OpenSecureChannelResponse
     */
    private function receiveOpenSecureChannelResponse(): OpenSecureChannelResponse
    {
        $chunkReader = new MessageChunkReader(
            $this->connection,
            $this->receiveBufferSize,
            $this->maxMessageSize,
            $this->maxChunkCount
        );
        $chunks = $chunkReader->read(MessageType::OpenSecureChannel);

        $assembledBody = '';
        $expectedRequestId = null;

        foreach ($chunks as $chunk) {
            if ($chunk->header->isAbort()) {
                throw new RuntimeException('Server aborted the open secure channel response');
            }

            [$requestId, $sequenceNumber, $bodyChunk] = $this->decodeAsymmetricChunk($chunk->payload);

            if ($expectedRequestId === null) {
                $expectedRequestId = $requestId;
            } elseif ($requestId !== $expectedRequestId) {
                throw new RuntimeException('Mismatched requestId in open secure channel response chunks');
            }

            $this->validateSequenceNumber($sequenceNumber);
            $assembledBody .= $bodyChunk;
        }

        $decoder = new BinaryDecoder($assembledBody);

        // Decode TypeId
        $typeId = NodeId::decode($decoder);

        // Check if this is a ServiceFault (TypeId 397)
        if ($typeId->namespaceIndex === 0 && $typeId->identifier === 397) {
            $fault = ServiceFault::decode($decoder);
            throw new RuntimeException(
                "Server returned ServiceFault: {$fault->responseHeader->serviceResult}"
            );
        }

        // Decode OpenSecureChannelResponse (TypeId should be 449)
        $response = OpenSecureChannelResponse::decode($decoder);

        return $response;
    }

    /**
     * Send CloseSecureChannelRequest wrapped in CLO message
     */
    private function sendCloseSecureChannelRequest(CloseSecureChannelRequest $request): void
    {
        // Build the message body
        $bodyEncoder = new BinaryEncoder();
        $request->encode($bodyEncoder);
        $messageBody = $bodyEncoder->getBytes();

        // Build sequence header
        $sequenceHeader = new SequenceHeader(
            sequenceNumber: $this->nextSequenceNumber(),
            requestId: $this->nextRequestId()
        );
        $sequenceHeaderEncoder = new BinaryEncoder();
        $sequenceHeader->encode($sequenceHeaderEncoder);
        $sequenceHeaderBytes = $sequenceHeaderEncoder->getBytes();

        // For CLO messages, we don't need asymmetric security header
        // Just: Header + SequenceHeader + Body

        // Calculate total message size
        $messageSize = MessageHeader::HEADER_SIZE
            + strlen($sequenceHeaderBytes)
            + strlen($messageBody);

        // Build message header
        $messageHeader = MessageHeader::final(MessageType::CloseSecureChannel, $messageSize);
        $headerEncoder = new BinaryEncoder();
        $messageHeader->encode($headerEncoder);
        $headerBytes = $headerEncoder->getBytes();

        // Send complete message
        $completeMessage = $headerBytes . $sequenceHeaderBytes . $messageBody;
        $this->connection->send($completeMessage);
    }

    /**
     * Send a service request and receive response (MSG message type)
     *
     * @template T of object
     * @param ServiceRequest $request The request message
     * @param class-string<T> $responseClass The expected response class
     * @return T The decoded response
     */
    public function sendServiceRequest(ServiceRequest $request, string $responseClass): object
    {
        // Encode request body with TypeId
        $bodyEncoder = new BinaryEncoder();
        $request->getTypeId()->encode($bodyEncoder);
        $request->encode($bodyEncoder);
        $messageBody = $bodyEncoder->getBytes();

        // Auto-inject AuthenticationToken (mirrors C# ClientBase.UpdateRequestHeader)
        $header = $request->getRequestHeader();
        if ($this->authenticationToken !== null && $header->authenticationToken->isNull()) {
            $messageBody = $this->injectAuthenticationToken($messageBody, $request, $header);
        }

        // Build symmetric security header
        $securityHeader = new SymmetricSecurityHeader(
            secureChannelId: $this->securityToken !== null ? $this->securityToken->channelId : 0,
            tokenId: $this->securityToken !== null ? $this->securityToken->tokenId : 0,
        );
        $securityHeaderEncoder = new BinaryEncoder();
        $securityHeader->encode($securityHeaderEncoder);
        $securityHeaderBytes = $securityHeaderEncoder->getBytes();

        $requestId = $this->nextRequestId();
        $this->sendSymmetricRequestChunks($securityHeaderBytes, $messageBody, $requestId);

        // Receive response
        return $this->receiveServiceResponse($responseClass);
    }

    /**
     * Send a service request as one or more symmetric chunks.
     */
    private function sendSymmetricRequestChunks(string $securityHeaderBytes, string $messageBody, int $requestId): void
    {
        $bodyLength = strlen($messageBody);
        $effectiveSendBufferSize = $this->sendBufferSize;
        if ($this->maxMessageSize > 0) {
            $effectiveSendBufferSize = $effectiveSendBufferSize > 0
                ? min($effectiveSendBufferSize, $this->maxMessageSize)
                : $this->maxMessageSize;
        }

        $maxChunkBodySize = $this->calculateMaxChunkBodySize(
            strlen($securityHeaderBytes),
            $bodyLength,
            $effectiveSendBufferSize
        );
        if ($bodyLength > 0 && $maxChunkBodySize <= 0) {
            throw new RuntimeException('Send buffer too small for request payload');
        }

        $chunkBodies = $bodyLength === 0
            ? ['']
            : $this->splitMessageBody($messageBody, $maxChunkBodySize);

        $chunkCount = count($chunkBodies);
        if ($this->maxChunkCount > 0 && $chunkCount > $this->maxChunkCount) {
            throw new RuntimeException(
                "Request exceeds max chunk count ({$chunkCount} > {$this->maxChunkCount})"
            );
        }

        foreach ($chunkBodies as $index => $chunkBody) {
            $sequenceHeader = new SequenceHeader(
                sequenceNumber: $this->nextSequenceNumber(),
                requestId: $requestId
            );
            $sequenceHeaderEncoder = new BinaryEncoder();
            $sequenceHeader->encode($sequenceHeaderEncoder);
            $sequenceHeaderBytes = $sequenceHeaderEncoder->getBytes();

            $payload = $this->buildSymmetricChunkPayload(
                $securityHeaderBytes,
                $sequenceHeaderBytes,
                $chunkBody
            );

            $messageSize = MessageHeader::HEADER_SIZE + strlen($payload);
            if ($this->maxMessageSize > 0 && $messageSize > $this->maxMessageSize) {
                throw new RuntimeException(
                    "Request chunk exceeds max message size ({$messageSize} > {$this->maxMessageSize})"
                );
            }
            $messageHeader = ($index === $chunkCount - 1)
                ? MessageHeader::final(MessageType::Message, $messageSize)
                : MessageHeader::intermediate(MessageType::Message, $messageSize);
            $headerEncoder = new BinaryEncoder();
            $messageHeader->encode($headerEncoder);

            $this->connection->send($headerEncoder->getBytes() . $payload);
        }
    }

    /**
     * Send an asymmetric message (OPN) as one or more chunks.
     */
    private function sendAsymmetricRequestChunks(
        string $securityHeaderBytes,
        string $messageBody,
        MessageType $messageType,
        int $requestId
    ): void {
        $bodyLength = strlen($messageBody);
        $effectiveSendBufferSize = $this->sendBufferSize;
        if ($this->maxMessageSize > 0) {
            $effectiveSendBufferSize = $effectiveSendBufferSize > 0
                ? min($effectiveSendBufferSize, $this->maxMessageSize)
                : $this->maxMessageSize;
        }

        $maxPayloadSize = $effectiveSendBufferSize > 0
            ? $effectiveSendBufferSize - MessageHeader::HEADER_SIZE - strlen($securityHeaderBytes)
            : $bodyLength + 8;

        $sequenceHeaderSize = 8;
        $maxChunkBodySize = $maxPayloadSize - $sequenceHeaderSize;

        if ($bodyLength > 0 && $maxChunkBodySize <= 0) {
            throw new RuntimeException('Send buffer too small for open secure channel payload');
        }

        $chunkBodies = $bodyLength === 0
            ? ['']
            : $this->splitMessageBody($messageBody, $maxChunkBodySize);

        $chunkCount = count($chunkBodies);
        if ($this->maxChunkCount > 0 && $chunkCount > $this->maxChunkCount) {
            throw new RuntimeException(
                "Request exceeds max chunk count ({$chunkCount} > {$this->maxChunkCount})"
            );
        }

        foreach ($chunkBodies as $index => $chunkBody) {
            $sequenceHeader = new SequenceHeader(
                sequenceNumber: $this->nextSequenceNumber(),
                requestId: $requestId
            );
            $sequenceHeaderEncoder = new BinaryEncoder();
            $sequenceHeader->encode($sequenceHeaderEncoder);
            $sequenceHeaderBytes = $sequenceHeaderEncoder->getBytes();

            $payload = $securityHeaderBytes . $sequenceHeaderBytes . $chunkBody;
            $messageSize = MessageHeader::HEADER_SIZE + strlen($payload);
            if ($this->maxMessageSize > 0 && $messageSize > $this->maxMessageSize) {
                throw new RuntimeException(
                    "Request chunk exceeds max message size ({$messageSize} > {$this->maxMessageSize})"
                );
            }

            $messageHeader = ($index === $chunkCount - 1)
                ? MessageHeader::final($messageType, $messageSize)
                : MessageHeader::intermediate($messageType, $messageSize);
            $headerEncoder = new BinaryEncoder();
            $messageHeader->encode($headerEncoder);

            $this->connection->send($headerEncoder->getBytes() . $payload);
        }
    }

    /**
     * Build a single symmetric chunk payload (security header + data/signature).
     */
    private function buildSymmetricChunkPayload(
        string $securityHeaderBytes,
        string $sequenceHeaderBytes,
        string $chunkBody
    ): string {
        $plaintext = $sequenceHeaderBytes . $chunkBody;

        if ($this->securityMode === MessageSecurityMode::None) {
            return $securityHeaderBytes . $plaintext;
        }

        if ($this->securityHandler === null || $this->currentKeys === null) {
            throw new RuntimeException('Security handler and keys must be initialized for secure messaging');
        }

        if ($this->securityMode === MessageSecurityMode::SignAndEncrypt) {
            $blockSize = $this->securityHandler->getSymmetricBlockSize();
            $paddedPlaintext = OpcUaPadding::addSymmetric($plaintext, $blockSize);
            $encryptedPayload = $this->securityHandler->encryptSymmetric(
                $paddedPlaintext,
                $this->currentKeys->clientEncryptionKey,
                $this->currentKeys->clientIV
            );
            $dataToSign = $securityHeaderBytes . $encryptedPayload;
            $signatureBytes = $this->securityHandler->signSymmetric(
                $dataToSign,
                $this->currentKeys->clientSigningKey
            );
            return $securityHeaderBytes . $encryptedPayload . $signatureBytes;
        }

        $dataToSign = $securityHeaderBytes . $plaintext;
        $signatureBytes = $this->securityHandler->signSymmetric(
            $dataToSign,
            $this->currentKeys->clientSigningKey
        );

        return $securityHeaderBytes . $plaintext . $signatureBytes;
    }

    /**
     * Calculate the maximum request body size per chunk.
     */
    private function calculateMaxChunkBodySize(
        int $securityHeaderLength,
        int $bodyLength,
        ?int $sendBufferSize = null
    ): int {
        $sendBufferSize = $sendBufferSize ?? $this->sendBufferSize;
        if ($sendBufferSize <= 0) {
            return $bodyLength;
        }

        $maxPayloadSize = $sendBufferSize - MessageHeader::HEADER_SIZE - $securityHeaderLength;
        if ($maxPayloadSize <= 0) {
            return 0;
        }

        $sequenceHeaderSize = 8;

        if ($this->securityMode === MessageSecurityMode::None) {
            return $maxPayloadSize - $sequenceHeaderSize;
        }

        if ($this->securityHandler === null) {
            return 0;
        }

        $signatureLength = $this->securityHandler->getSymmetricSignatureLength();

        if ($this->securityMode === MessageSecurityMode::Sign) {
            return $maxPayloadSize - $sequenceHeaderSize - $signatureLength;
        }

        $maxPlaintextSize = $maxPayloadSize - $signatureLength;
        $blockSize = $this->securityHandler->getSymmetricBlockSize();
        $maxDataLength = $maxPlaintextSize;

        while ($maxDataLength > 0) {
            $paddingLength = OpcUaPadding::calculatePaddingLength($maxDataLength, $blockSize);
            if ($maxDataLength + $paddingLength <= $maxPlaintextSize) {
                return $maxDataLength - $sequenceHeaderSize;
            }
            $maxDataLength--;
        }

        return 0;
    }

    /**
     * Split a binary message body into chunk-sized pieces.
     *
     * @return string[]
     */
    private function splitMessageBody(string $messageBody, int $chunkSize): array
    {
        $chunks = [];
        $offset = 0;
        $length = strlen($messageBody);

        while ($offset < $length) {
            $chunks[] = substr($messageBody, $offset, $chunkSize);
            $offset += $chunkSize;
        }

        return $chunks;
    }

    /**
     * Receive a service response (MSG message type)
     *
     * @template T of object
     * @param class-string<T> $responseClass
     * @return T
     */
    private function receiveServiceResponse(string $responseClass): object
    {
        $chunkReader = new MessageChunkReader(
            $this->connection,
            $this->receiveBufferSize,
            $this->maxMessageSize,
            $this->maxChunkCount
        );
        $chunks = $chunkReader->read(MessageType::Message);

        $assembledBody = '';
        $expectedRequestId = null;

        foreach ($chunks as $chunk) {
            if ($chunk->header->isAbort()) {
                throw new RuntimeException('Server aborted the response message');
            }

            [$requestId, $sequenceNumber, $bodyChunk] = $this->decodeSymmetricChunk($chunk->payload);

            if ($expectedRequestId === null) {
                $expectedRequestId = $requestId;
            } elseif ($requestId !== $expectedRequestId) {
                throw new RuntimeException('Mismatched requestId in response chunks');
            }

            $this->validateSequenceNumber($sequenceNumber);
            $assembledBody .= $bodyChunk;
        }

        $decoder = new BinaryDecoder($assembledBody);

        // Decode TypeId
        $typeId = NodeId::decode($decoder);

        // Check if this is a ServiceFault (TypeId 397)
        if ($typeId->namespaceIndex === 0 && $typeId->identifier === 397) {
            $fault = ServiceFault::decode($decoder);
            throw new RuntimeException(
                "Server returned ServiceFault: {$fault->responseHeader->serviceResult}"
            );
        }

        // Decode service response
        if (!method_exists($responseClass, 'decode')) {
            throw new RuntimeException("Response class {$responseClass} does not have decode method");
        }

        return $responseClass::decode($decoder);
    }

    /**
     * Decode a symmetric response chunk and return its body.
     *
     * @return array{0:int,1:int,2:string} requestId, sequenceNumber, bodyChunk
     */
    private function decodeSymmetricChunk(string $payload): array
    {
        $decoder = new BinaryDecoder($payload);
        $securityHeader = SymmetricSecurityHeader::decode($decoder);

        $chunkPayload = substr($payload, $decoder->getPosition());

        if ($this->securityMode === MessageSecurityMode::None) {
            $plaintext = $chunkPayload;
        } else {
            if ($this->securityHandler === null || $this->currentKeys === null) {
                throw new RuntimeException('Security handler and keys must be initialized for secure messaging');
            }

            $signatureLength = $this->securityHandler->getSymmetricSignatureLength();
            if (strlen($chunkPayload) < $signatureLength) {
                throw new RuntimeException('Invalid message: payload too short for signature');
            }

            $signedPayload = substr($chunkPayload, 0, -$signatureLength);
            $receivedSignature = substr($chunkPayload, -$signatureLength);

            $securityHeaderEncoder = new BinaryEncoder();
            $securityHeader->encode($securityHeaderEncoder);
            $securityHeaderBytes = $securityHeaderEncoder->getBytes();

            $dataToVerify = $securityHeaderBytes . $signedPayload;
            $isValid = $this->securityHandler->verifySymmetric(
                $dataToVerify,
                $receivedSignature,
                $this->currentKeys->serverSigningKey
            );

            if (!$isValid) {
                throw new RuntimeException('Message signature verification failed');
            }

            if ($this->securityMode === MessageSecurityMode::SignAndEncrypt) {
                $paddedPlaintext = $this->securityHandler->decryptSymmetric(
                    $signedPayload,
                    $this->currentKeys->serverEncryptionKey,
                    $this->currentKeys->serverIV
                );

                try {
                    $plaintext = OpcUaPadding::removeSymmetric($paddedPlaintext);
                } catch (RuntimeException $e) {
                    throw new RuntimeException(
                        "Failed to remove padding from decrypted message: {$e->getMessage()}"
                    );
                }
            } else {
                $plaintext = $signedPayload;
            }
        }

        $chunkDecoder = new BinaryDecoder($plaintext);
        $sequenceHeader = SequenceHeader::decode($chunkDecoder);
        $body = substr($plaintext, $chunkDecoder->getPosition());

        return [$sequenceHeader->requestId, $sequenceHeader->sequenceNumber, $body];
    }

    /**
     * Decode an asymmetric response chunk and return its body.
     *
     * @return array{0:int,1:int,2:string} requestId, sequenceNumber, bodyChunk
     */
    private function decodeAsymmetricChunk(string $payload): array
    {
        $decoder = new BinaryDecoder($payload);
        AsymmetricSecurityHeader::decode($decoder);

        $sequenceHeader = SequenceHeader::decode($decoder);
        $body = substr($payload, $decoder->getPosition());

        return [$sequenceHeader->requestId, $sequenceHeader->sequenceNumber, $body];
    }

    /**
     * Validate sequence number to prevent replay attacks
     *
     * @param int $receivedSeqNum Sequence number from received message
     * @throws RuntimeException If sequence number is invalid
     */
    private function validateSequenceNumber(int $receivedSeqNum): void
    {
        if ($this->lastReceivedSequenceNumber === 0) {
            // First message from server - accept any sequence number
            $this->lastReceivedSequenceNumber = $receivedSeqNum;
            return;
        }

        // Check for rollover (2^31 - 1 for RSA-based policies, 2^32 - 1 for ECC)
        // Using 2^31 - 1 (2147483647) for now (conservative, works for all policies)
        $maxSeqNum = 2147483647;

        if ($receivedSeqNum < $this->lastReceivedSequenceNumber) {
            // Potential rollover
            if ($this->sequenceNumberRolledOver) {
                throw new RuntimeException(
                    'Sequence number rolled over twice - security violation (possible replay attack)'
                );
            }

            // Allow one rollover per token
            $this->sequenceNumberRolledOver = true;
        } elseif ($receivedSeqNum === $this->lastReceivedSequenceNumber) {
            // Duplicate sequence number - replay attack!
            throw new RuntimeException(
                "Duplicate sequence number detected: {$receivedSeqNum} - possible replay attack"
            );
        }
        // else: receivedSeqNum > lastReceivedSequenceNumber - normal case

        $this->lastReceivedSequenceNumber = $receivedSeqNum;
    }
}
