<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Services;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * RequestHeader - common header for all service requests
 */
final readonly class RequestHeader
{
    public function __construct(
        public NodeId $authenticationToken,
        public DateTime $timestamp,
        public int $requestHandle,
        public int $returnDiagnostics,
        public ?string $auditEntryId,
        public int $timeoutHint,
        public ExtensionObject $additionalHeader,
    ) {
    }

    /**
     * Create a minimal request header
     */
    public static function create(int $requestHandle = 1, int $timeoutHint = 10000): self
    {
        return new self(
            authenticationToken: NodeId::numeric(0, 0), // Null NodeId for no session
            timestamp: DateTime::now(),
            requestHandle: $requestHandle,
            returnDiagnostics: 0,
            auditEntryId: null,
            timeoutHint: $timeoutHint,
            additionalHeader: ExtensionObject::empty(NodeId::numeric(0, 0)),
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->authenticationToken->encode($encoder);
        $this->timestamp->encode($encoder);
        $encoder->writeUInt32($this->requestHandle);
        $encoder->writeUInt32($this->returnDiagnostics);
        $encoder->writeString($this->auditEntryId);
        $encoder->writeUInt32($this->timeoutHint);
        $this->additionalHeader->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $authenticationToken = NodeId::decode($decoder);
        $timestamp = DateTime::decode($decoder);
        $requestHandle = $decoder->readUInt32();
        $returnDiagnostics = $decoder->readUInt32();
        $auditEntryId = $decoder->readString();
        $timeoutHint = $decoder->readUInt32();
        $additionalHeader = ExtensionObject::decode($decoder);

        return new self(
            authenticationToken: $authenticationToken,
            timestamp: $timestamp,
            requestHandle: $requestHandle,
            returnDiagnostics: $returnDiagnostics,
            auditEntryId: $auditEntryId,
            timeoutHint: $timeoutHint,
            additionalHeader: $additionalHeader,
        );
    }
}
