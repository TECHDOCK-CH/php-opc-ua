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
use TechDock\OpcUa\Core\Transport\AcknowledgeMessage;
use TechDock\OpcUa\Core\Transport\ErrorMessage;
use TechDock\OpcUa\Core\Transport\HelloMessage;
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

    // Sequence number validation
    private int $lastReceivedSequenceNumber = 0;
    private bool $sequenceNumberRolledOver = false;

    // Client credentials for asymmetric operations
    private ?string $clientCertificateDer = null;
    private ?string $clientCertificatePem = null;
    private ?string $clientPrivateKeyPem = null;
    private ?string $clientPrivateKeyPassword = null;

    // Server certificate (from endpoint discovery)
    private ?string $serverCertificateDer = null;
    private ?string $serverCertificatePem = null;

    public function __construct(
        private readonly TcpConnectionInterface $connection,
        private readonly MessageSecurityMode $securityMode = MessageSecurityMode::None,
        private readonly SecurityPolicy $securityPolicy = SecurityPolicy::None,
        private readonly ?CertificateValidator $certificateValidator = null,
    ) {
    }

    /**
     * Set client certificate and private key for asymmetric signing and encryption
     *
     * @param string $certificateDer DER-encoded X.509 certificate
     * @param string $privateKeyPem PEM-encoded private key
     * @param string|null $privateKeyPassword Password for encrypted private key
     */
    public function setClientCertificate(
        string $certificateDer,
        string $privateKeyPem,
        ?string $privateKeyPassword = null
    ): void {
        $this->clientCertificateDer = $certificateDer;
        $this->clientCertificatePem = $this->derToPem($certificateDer);
        $this->clientPrivateKeyPem = $privateKeyPem;
        $this->clientPrivateKeyPassword = $privateKeyPassword;
    }

    /**
     * Set server certificate for asymmetric encryption (used when not discovered via GetEndpoints)
     *
     * @param string $certificateDer DER-encoded X.509 certificate
     */
    public function setServerCertificate(string $certificateDer): void
    {
        $this->serverCertificateDer = $certificateDer;
        $this->serverCertificatePem = $this->derToPem($certificateDer);
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
     * Calculate SHA-1 thumbprint of a DER-encoded certificate
     *
     * @param string $certificateDer DER-encoded certificate
     * @return string Raw binary SHA-1 hash (20 bytes)
     */
    private function calculateCertificateThumbprint(string $certificateDer): string
    {
        return hash('sha1', $certificateDer, true);
    }

    /**
     * Open the secure channel (perform handshake)
     */
    public function open(): void
    {
        if ($this->isOpen) {
            throw new RuntimeException('Secure channel is already open');
        }

        // Validate that client certificate is provided when security is enabled
        if ($this->securityMode !== MessageSecurityMode::None) {
            if ($this->clientCertificateDer === null || $this->clientPrivateKeyPem === null) {
                throw new RuntimeException(
                    'Client certificate and private key are required for security mode ' .
                    $this->securityMode->name
                );
            }

            // Create security handler for this policy
            $this->securityHandler = SecurityPolicyFactory::createHandler($this->securityPolicy);
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
            // Security handler should already be created in the validation step
            if ($this->securityHandler === null) {
                $this->securityHandler = SecurityPolicyFactory::createHandler($this->securityPolicy);
            }

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

        // Build sequence header
        $sequenceHeader = new SequenceHeader(
            sequenceNumber: $this->nextSequenceNumber(),
            requestId: $this->nextRequestId()
        );
        $sequenceHeaderEncoder = new BinaryEncoder();
        $sequenceHeader->encode($sequenceHeaderEncoder);
        $sequenceHeaderBytes = $sequenceHeaderEncoder->getBytes();

        // Determine certificates for asymmetric security header
        $senderCertificate = null;
        $receiverCertificateThumbprint = null;

        if ($this->securityMode !== MessageSecurityMode::None && $this->securityHandler !== null) {
            // Include sender certificate (client cert) for all non-None security modes
            $senderCertificate = $this->clientCertificateDer;

            // Include receiver certificate thumbprint if we have server certificate
            if ($this->serverCertificateDer !== null) {
                $receiverCertificateThumbprint = $this->calculateCertificateThumbprint(
                    $this->serverCertificateDer
                );
            }
        }

        // Build asymmetric security header
        $securityHeader = new AsymmetricSecurityHeader(
            secureChannelId: 0, // 0 for initial OpenSecureChannel request
            securityPolicy: $this->selectedEndpoint->securityPolicy ?? $this->securityPolicy,
            senderCertificate: $senderCertificate,
            receiverCertificateThumbprint: $receiverCertificateThumbprint
        );
        $securityHeaderEncoder = new BinaryEncoder();
        $securityHeader->encode($securityHeaderEncoder);
        $securityHeaderBytes = $securityHeaderEncoder->getBytes();

        // === ASYMMETRIC SIGNING AND ENCRYPTION ===
        $messagePayload = '';
        $signatureBytes = '';

        if ($this->securityMode !== MessageSecurityMode::None && $this->securityHandler !== null) {
            $plaintextPayload = $sequenceHeaderBytes . $messageBody;

            if ($this->securityMode === MessageSecurityMode::SignAndEncrypt) {
                // SignAndEncrypt mode: Encrypt then Sign
                if ($this->serverCertificatePem === null) {
                    throw new RuntimeException(
                        'Server certificate is required for SignAndEncrypt mode'
                    );
                }

                // Add OPC UA asymmetric padding before encryption
                $plaintextBlockSize = $this->securityHandler->getAsymmetricPlaintextBlockSize(
                    $this->serverCertificatePem
                );
                $paddedPlaintext = OpcUaPadding::addAsymmetric(
                    $plaintextPayload,
                    $plaintextBlockSize
                );

                // Encrypt in blocks using server's public key
                $ciphertextBlockSize = $this->securityHandler->getAsymmetricCiphertextBlockSize(
                    $this->serverCertificatePem
                );
                $encryptedPayload = '';
                for ($i = 0; $i < strlen($paddedPlaintext); $i += $plaintextBlockSize) {
                    $block = substr($paddedPlaintext, $i, $plaintextBlockSize);
                    $encryptedPayload .= $this->securityHandler->encryptAsymmetric(
                        $block,
                        $this->serverCertificatePem
                    );
                }

                $messagePayload = $encryptedPayload;
            } else {
                // Sign-only mode: No encryption, no padding needed per OPC UA spec
                $messagePayload = $plaintextPayload;
            }

            // Sign SecurityHeader + payload (ciphertext for SignAndEncrypt, plaintext for Sign mode)
            $dataToSign = $securityHeaderBytes . $messagePayload;

            // Client private key is guaranteed non-null by the validation in open()
            assert($this->clientPrivateKeyPem !== null, 'Client private key must be set');

            $signatureBytes = $this->securityHandler->signAsymmetric(
                $dataToSign,
                $this->clientPrivateKeyPem,
                $this->clientPrivateKeyPassword
            );
        } else {
            // No security: plaintext message (SecurityMode::None)
            $messagePayload = $sequenceHeaderBytes . $messageBody;
        }

        // Calculate total message size (Header 8 bytes + Security Header + Payload + Signature)
        $messageSize = MessageHeader::HEADER_SIZE
            + strlen($securityHeaderBytes)
            + strlen($messagePayload)
            + strlen($signatureBytes);

        // Build message header
        $messageHeader = MessageHeader::final(MessageType::OpenSecureChannel, $messageSize);
        $headerEncoder = new BinaryEncoder();
        $messageHeader->encode($headerEncoder);
        $headerBytes = $headerEncoder->getBytes();

        // Send complete message
        $completeMessage = $headerBytes . $securityHeaderBytes . $messagePayload . $signatureBytes;
        $this->connection->send($completeMessage);
    }

    /**
     * Receive and decode OpenSecureChannelResponse
     */
    private function receiveOpenSecureChannelResponse(): OpenSecureChannelResponse
    {
        // Receive message header
        $header = $this->connection->receiveHeader();

        if ($header->messageType === MessageType::Error) {
            $payload = $this->connection->receive($header->getPayloadSize());
            $headerEncoder = new BinaryEncoder();
            $header->encode($headerEncoder);
            $decoder = new BinaryDecoder($headerEncoder->getBytes() . $payload);
            $error = ErrorMessage::decode($decoder);
            throw new RuntimeException("Server returned error: {$error->reason}");
        }

        if ($header->messageType !== MessageType::OpenSecureChannel) {
            throw new RuntimeException("Expected OPN response, got {$header->messageType->value}");
        }

        // Receive payload
        $payload = $this->connection->receive($header->getPayloadSize());

        // Decode the response
        $decoder = new BinaryDecoder($payload);

        // Decode asymmetric security header
        $securityHeader = AsymmetricSecurityHeader::decode($decoder);

        // Extract server certificate from the security header (if present)
        if ($securityHeader->senderCertificate !== null && $securityHeader->senderCertificate !== '') {
            $this->serverCertificateDer = $securityHeader->senderCertificate;
            $this->serverCertificatePem = $this->derToPem($securityHeader->senderCertificate);
        }

        // === ASYMMETRIC SIGNATURE VERIFICATION AND DECRYPTION ===
        if ($this->securityMode !== MessageSecurityMode::None && $this->securityHandler !== null) {
            // Get the server certificate for signature verification
            if ($this->serverCertificatePem === null) {
                throw new RuntimeException(
                    'Server certificate is required for signature verification'
                );
            }

            // Re-encode security header for signature verification
            $securityHeaderEncoder = new BinaryEncoder();
            $securityHeader->encode($securityHeaderEncoder);
            $securityHeaderBytes = $securityHeaderEncoder->getBytes();

            // Calculate signature length (signature is at the end)
            $signatureLength = $this->securityHandler->getAsymmetricSignatureLength(
                $this->serverCertificatePem
            );

            $remainingPayloadLength = strlen($payload) - $decoder->getPosition();
            $payloadWithoutSignatureLength = $remainingPayloadLength - $signatureLength;

            if ($payloadWithoutSignatureLength < 0) {
                throw new RuntimeException(
                    "Invalid message: payload too short for signature " .
                    "(expected signature: {$signatureLength} bytes, remaining: {$remainingPayloadLength} bytes)"
                );
            }

            // Extract encrypted/plain payload and signature
            $encryptedOrPlainPayload = $decoder->readBytes($payloadWithoutSignatureLength);
            $receivedSignature = $decoder->readBytes($signatureLength);

            // Verify signature: SecurityHeader + EncryptedPayload (or PlainPayload for Sign mode)
            $dataToVerify = $securityHeaderBytes . $encryptedOrPlainPayload;
            $isValid = $this->securityHandler->verifyAsymmetric(
                $dataToVerify,
                $receivedSignature,
                $this->serverCertificatePem
            );

            if (!$isValid) {
                throw new RuntimeException(
                    'Asymmetric signature verification failed - possible tampering or wrong certificate'
                );
            }

            // Decrypt if SignAndEncrypt mode
            if ($this->securityMode === MessageSecurityMode::SignAndEncrypt) {
                if ($this->clientPrivateKeyPem === null) {
                    throw new RuntimeException(
                        'Client private key is required for decryption'
                    );
                }

                // Client certificate is guaranteed non-null by validation in open()
                assert($this->clientCertificatePem !== null, 'Client certificate must be set');

                // Decrypt in blocks using client's private key
                $ciphertextBlockSize = $this->securityHandler->getAsymmetricCiphertextBlockSize(
                    $this->clientCertificatePem
                );
                $plaintextBlockSize = $this->securityHandler->getAsymmetricPlaintextBlockSize(
                    $this->clientCertificatePem
                );

                $paddedPlaintext = '';
                for ($i = 0; $i < strlen($encryptedOrPlainPayload); $i += $ciphertextBlockSize) {
                    $block = substr($encryptedOrPlainPayload, $i, $ciphertextBlockSize);
                    $paddedPlaintext .= $this->securityHandler->decryptAsymmetric(
                        $block,
                        $this->clientPrivateKeyPem,
                        $this->clientPrivateKeyPassword
                    );
                }

                // Remove OPC UA asymmetric padding
                $plaintext = OpcUaPadding::removeAsymmetric($paddedPlaintext);

                // Create new decoder for decrypted data
                $decoder = new BinaryDecoder($plaintext);
            } else {
                // Sign-only mode: payload is plaintext
                $decoder = new BinaryDecoder($encryptedOrPlainPayload);
            }
        }

        // Decode sequence header
        $sequenceHeader = SequenceHeader::decode($decoder);

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
     * @param object $request The request message
     * @param class-string<T> $responseClass The expected response class
     * @return T The decoded response
     */
    public function sendServiceRequest(object $request, string $responseClass): object
    {
        // Encode request body with TypeId
        $bodyEncoder = new BinaryEncoder();

        // Prepend TypeId if the request has getTypeId() method
        if (method_exists($request, 'getTypeId')) {
            $request->getTypeId()->encode($bodyEncoder);
        }

        if (method_exists($request, 'encode')) {
            $request->encode($bodyEncoder);
        }
        $messageBody = $bodyEncoder->getBytes();

        // Build symmetric security header
        $securityHeader = new SymmetricSecurityHeader(
            secureChannelId: $this->securityToken !== null ? $this->securityToken->channelId : 0,
            tokenId: $this->securityToken !== null ? $this->securityToken->tokenId : 0,
        );
        $securityHeaderEncoder = new BinaryEncoder();
        $securityHeader->encode($securityHeaderEncoder);
        $securityHeaderBytes = $securityHeaderEncoder->getBytes();

        // Build sequence header
        $sequenceHeader = new SequenceHeader(
            sequenceNumber: $this->nextSequenceNumber(),
            requestId: $this->nextRequestId()
        );
        $sequenceHeaderEncoder = new BinaryEncoder();
        $sequenceHeader->encode($sequenceHeaderEncoder);
        $sequenceHeaderBytes = $sequenceHeaderEncoder->getBytes();

        // === ENCRYPTION LOGIC ===
        $messagePayload = '';

        if (
            $this->securityMode === MessageSecurityMode::SignAndEncrypt
            || $this->securityMode === MessageSecurityMode::Sign
        ) {
            // We have encryption enabled - encrypt and/or sign the message
            if ($this->securityHandler === null || $this->currentKeys === null) {
                throw new RuntimeException('Security handler and keys must be initialized for secure messaging');
            }

            // Plaintext to encrypt: SequenceHeader + Body
            $plaintextToEncrypt = $sequenceHeaderBytes . $messageBody;

            // Add OPC UA padding
            $blockSize = $this->securityHandler->getSymmetricBlockSize();
            $paddedPlaintext = OpcUaPadding::addSymmetric($plaintextToEncrypt, $blockSize);

            // Encrypt if required (SignAndEncrypt mode)
            if ($this->securityMode === MessageSecurityMode::SignAndEncrypt) {
                $encryptedPayload = $this->securityHandler->encryptSymmetric(
                    $paddedPlaintext,
                    $this->currentKeys->clientEncryptionKey,
                    $this->currentKeys->clientIV
                );
            } else {
                // Sign-only mode: no encryption, but still need padding
                $encryptedPayload = $paddedPlaintext;
            }

            // Sign: SecurityHeader + EncryptedPayload
            // Note: Signature is NOT encrypted (appended after ciphertext)
            $dataToSign = $securityHeaderBytes . $encryptedPayload;
            $signatureBytes = $this->securityHandler->signSymmetric(
                $dataToSign,
                $this->currentKeys->clientSigningKey
            );

            // Payload = EncryptedData + Signature
            $messagePayload = $encryptedPayload . $signatureBytes;
        } else {
            // No security: plaintext message (SecurityMode::None)
            $messagePayload = $sequenceHeaderBytes . $messageBody;
        }

        // Calculate total message size
        $messageSize = MessageHeader::HEADER_SIZE
            + strlen($securityHeaderBytes)
            + strlen($messagePayload);

        // Build message header
        $messageHeader = MessageHeader::final(MessageType::Message, $messageSize);
        $headerEncoder = new BinaryEncoder();
        $messageHeader->encode($headerEncoder);
        $headerBytes = $headerEncoder->getBytes();

        // Send complete message
        $completeMessage = $headerBytes . $securityHeaderBytes . $messagePayload;
        $this->connection->send($completeMessage);

        // Receive response
        return $this->receiveServiceResponse($responseClass);
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
        // Receive message header
        $header = $this->connection->receiveHeader();

        if ($header->messageType === MessageType::Error) {
            $payload = $this->connection->receive($header->getPayloadSize());
            $headerEncoder = new BinaryEncoder();
            $header->encode($headerEncoder);
            $decoder = new BinaryDecoder($headerEncoder->getBytes() . $payload);
            $error = ErrorMessage::decode($decoder);
            throw new RuntimeException("Server returned error: {$error->reason}");
        }

        if ($header->messageType !== MessageType::Message) {
            throw new RuntimeException("Expected MSG response, got {$header->messageType->value}");
        }

        // Receive payload
        $payload = $this->connection->receive($header->getPayloadSize());

        // Decode the response
        $decoder = new BinaryDecoder($payload);

        // Decode symmetric security header
        $securityHeader = SymmetricSecurityHeader::decode($decoder);

        // === DECRYPTION LOGIC ===
        if (
            $this->securityMode === MessageSecurityMode::SignAndEncrypt
            || $this->securityMode === MessageSecurityMode::Sign
        ) {
            // We have encryption enabled - verify signature and decrypt
            if ($this->securityHandler === null || $this->currentKeys === null) {
                throw new RuntimeException('Security handler and keys must be initialized for secure messaging');
            }

            // Calculate signature position (at the end of the payload)
            $signatureLength = $this->securityHandler->getSymmetricSignatureLength();
            $remainingPayloadLength = strlen($payload) - $decoder->getPosition();
            $encryptedPayloadLength = $remainingPayloadLength - $signatureLength;

            if ($encryptedPayloadLength < 0) {
                throw new RuntimeException(
                    "Invalid message: payload too short for signature " .
                    "(expected signature: {$signatureLength} bytes, remaining: {$remainingPayloadLength} bytes)"
                );
            }

            // Extract encrypted payload and signature
            $encryptedPayload = $decoder->readBytes($encryptedPayloadLength);
            $receivedSignature = $decoder->readBytes($signatureLength);

            // Verify signature BEFORE decryption (fail fast if tampered)
            $securityHeaderEncoder = new BinaryEncoder();
            $securityHeader->encode($securityHeaderEncoder);
            $securityHeaderBytes = $securityHeaderEncoder->getBytes();

            $dataToVerify = $securityHeaderBytes . $encryptedPayload;
            $isValid = $this->securityHandler->verifySymmetric(
                $dataToVerify,
                $receivedSignature,
                $this->currentKeys->serverSigningKey
            );

            if (!$isValid) {
                throw new RuntimeException(
                    'Message signature verification failed - possible tampering or wrong keys'
                );
            }

            // Decrypt if message was encrypted (SignAndEncrypt mode)
            if ($this->securityMode === MessageSecurityMode::SignAndEncrypt) {
                $paddedPlaintext = $this->securityHandler->decryptSymmetric(
                    $encryptedPayload,
                    $this->currentKeys->serverEncryptionKey,
                    $this->currentKeys->serverIV
                );
            } else {
                // Sign-only mode: payload is not encrypted, just signed
                $paddedPlaintext = $encryptedPayload;
            }

            // Remove OPC UA padding
            try {
                $plaintext = OpcUaPadding::removeSymmetric($paddedPlaintext);
            } catch (RuntimeException $e) {
                throw new RuntimeException(
                    "Failed to remove padding from decrypted message: {$e->getMessage()}"
                );
            }

            // Create new decoder for plaintext (SequenceHeader + Body)
            $decoder = new BinaryDecoder($plaintext);
        }

        // Decode sequence header
        $sequenceHeader = SequenceHeader::decode($decoder);

        // Validate sequence number (prevents replay attacks)
        $this->validateSequenceNumber($sequenceHeader->sequenceNumber);

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
