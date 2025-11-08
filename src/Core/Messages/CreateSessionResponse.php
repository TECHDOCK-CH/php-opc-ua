<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\SignatureData;
use TechDock\OpcUa\Core\Types\SignedSoftwareCertificate;

/**
 * CreateSessionResponse - Response to CreateSessionRequest
 */
final readonly class CreateSessionResponse implements IEncodeable, ServiceResponse
{
    private const int TYPE_ID = 464;

    /**
     * @param EndpointDescription[] $serverEndpoints
     * @param SignedSoftwareCertificate[] $serverSoftwareCertificates
     */
    public function __construct(
        public ResponseHeader $responseHeader,
        public NodeId $sessionId,
        public NodeId $authenticationToken,
        public float $revisedSessionTimeout,
        public string $serverNonce,
        public ?string $serverCertificate,
        public array $serverEndpoints,
        public array $serverSoftwareCertificates,
        public SignatureData $serverSignature,
        public int $maxRequestMessageSize,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);
        $this->sessionId->encode($encoder);
        $this->authenticationToken->encode($encoder);
        $encoder->writeDouble($this->revisedSessionTimeout);
        $encoder->writeByteString($this->serverNonce);
        $encoder->writeByteString($this->serverCertificate);

        // Server endpoints array
        $encoder->writeUInt32(count($this->serverEndpoints));
        foreach ($this->serverEndpoints as $endpoint) {
            if ($endpoint instanceof EndpointDescription) {
                $endpoint->encode($encoder);
            }
        }

        // Server software certificates
        $encoder->writeUInt32(count($this->serverSoftwareCertificates));
        foreach ($this->serverSoftwareCertificates as $certificate) {
            if ($certificate instanceof SignedSoftwareCertificate) {
                $certificate->encode($encoder);
            }
        }

        $this->serverSignature->encode($encoder);
        $encoder->writeUInt32($this->maxRequestMessageSize);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);

        // Check if response is an error
        if (!$responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "Server returned error: {$responseHeader->serviceResult}"
            );
        }

        $sessionId = NodeId::decode($decoder);
        $authenticationToken = NodeId::decode($decoder);
        $revisedSessionTimeout = $decoder->readDouble();
        $serverNonce = $decoder->readByteString() ?? '';
        $serverCertificate = $decoder->readByteString();

        // Server endpoints
        $endpointCount = $decoder->readUInt32();
        $serverEndpoints = [];
        for ($i = 0; $i < $endpointCount; $i++) {
            $serverEndpoints[] = EndpointDescription::decode($decoder);
        }

        // Server software certificates
        $certCount = $decoder->readUInt32();
        $serverSoftwareCertificates = [];
        for ($i = 0; $i < $certCount; $i++) {
            $serverSoftwareCertificates[] = SignedSoftwareCertificate::decode($decoder);
        }

        $serverSignature = SignatureData::decode($decoder);
        $maxRequestMessageSize = $decoder->readUInt32();

        return new self(
            responseHeader: $responseHeader,
            sessionId: $sessionId,
            authenticationToken: $authenticationToken,
            revisedSessionTimeout: $revisedSessionTimeout,
            serverNonce: $serverNonce,
            serverCertificate: $serverCertificate,
            serverEndpoints: $serverEndpoints,
            serverSoftwareCertificates: $serverSoftwareCertificates,
            serverSignature: $serverSignature,
            maxRequestMessageSize: $maxRequestMessageSize,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
