<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\ApplicationDescription;
use TechDock\OpcUa\Core\Types\ApplicationType;
use TechDock\OpcUa\Core\Types\LocalizedText;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * CreateSessionRequest - Creates a new session with the server
 */
final readonly class CreateSessionRequest implements IEncodeable, ServiceRequest
{
    public const int DEFAULT_SESSION_TIMEOUT = 3600000; // 1 hour in milliseconds
    private const int TYPE_ID = 461;

    public function __construct(
        public RequestHeader $requestHeader,
        public ApplicationDescription $clientDescription,
        public ?string $serverUri,
        public string $endpointUrl,
        public string $sessionName,
        public string $clientNonce,
        public ?string $clientCertificate,
        public float $requestedSessionTimeout,
        public int $maxResponseMessageSize,
    ) {
    }

    /**
     * Create a CreateSessionRequest with default values
     */
    public static function create(
        string $endpointUrl,
        string $sessionName = 'PHP OPC UA Client Session',
        ?string $clientNonce = null,
        float $requestedSessionTimeout = self::DEFAULT_SESSION_TIMEOUT,
        ?RequestHeader $requestHeader = null,
        ?ApplicationDescription $clientDescription = null,
    ): self {
        $requestHeader ??= RequestHeader::create();

        $clientDescription ??= new ApplicationDescription(
            applicationUri: 'urn:php-opcua-client',
            productUri: 'urn:php-opcua-client',
            applicationName: new LocalizedText('en', 'PHP OPC UA Client'),
            applicationType: ApplicationType::Client,
            gatewayServerUri: null,
            discoveryProfileUri: null,
            discoveryUrls: [],
        );

        return new self(
            requestHeader: $requestHeader,
            clientDescription: $clientDescription,
            serverUri: null,
            endpointUrl: $endpointUrl,
            sessionName: $sessionName,
            clientNonce: $clientNonce ?? random_bytes(32),
            clientCertificate: null,
            requestedSessionTimeout: $requestedSessionTimeout,
            maxResponseMessageSize: 0, // 0 = no limit
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);

        $this->clientDescription->encode($encoder);
        $encoder->writeString($this->serverUri);
        $encoder->writeString($this->endpointUrl);
        $encoder->writeString($this->sessionName);
        $encoder->writeByteString($this->clientNonce);
        $encoder->writeByteString($this->clientCertificate);
        $encoder->writeDouble($this->requestedSessionTimeout);
        $encoder->writeUInt32($this->maxResponseMessageSize);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $clientDescription = ApplicationDescription::decode($decoder);
        $serverUri = $decoder->readString();
        $endpointUrl = $decoder->readString() ?? '';
        $sessionName = $decoder->readString() ?? '';
        $clientNonce = $decoder->readByteString() ?? '';
        $clientCertificate = $decoder->readByteString();
        $requestedSessionTimeout = $decoder->readDouble();
        $maxResponseMessageSize = $decoder->readUInt32();

        return new self(
            requestHeader: $requestHeader,
            clientDescription: $clientDescription,
            serverUri: $serverUri,
            endpointUrl: $endpointUrl,
            sessionName: $sessionName,
            clientNonce: $clientNonce,
            clientCertificate: $clientCertificate,
            requestedSessionTimeout: $requestedSessionTimeout,
            maxResponseMessageSize: $maxResponseMessageSize,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
