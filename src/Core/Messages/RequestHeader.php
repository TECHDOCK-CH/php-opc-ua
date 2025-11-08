<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Common header for all OPC UA service requests
 *
 * Structure:
 * - AuthenticationToken (NodeId)
 * - Timestamp (DateTime)
 * - RequestHandle (UInt32)
 * - ReturnDiagnostics (UInt32)
 * - AuditEntryId (String)
 * - TimeoutHint (UInt32) - in milliseconds
 * - AdditionalHeader (ExtensionObject) - not implemented yet, always null
 */
final readonly class RequestHeader implements IEncodeable
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
     * Create a RequestHeader with default values
     */
    public static function create(int $requestHandle = 1, int $timeoutHint = 15000): self
    {
        return new self(
            authenticationToken: NodeId::numeric(0, 0), // Null NodeId
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
