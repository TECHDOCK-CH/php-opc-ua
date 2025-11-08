<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\DateTime;
use TechDock\OpcUa\Core\Types\DiagnosticInfo;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\StatusCode;

/**
 * Common header for all OPC UA service responses
 *
 * Structure:
 * - AuthenticationToken (NodeId) - from OpenSecureChannel
 * - Timestamp (DateTime)
 * - RequestHandle (UInt32)
 * - ServiceResult (StatusCode)
 * - ServiceDiagnostics (DiagnosticInfo) - not implemented, always null
 * - StringTable (Array of String) - always empty
 * - AdditionalHeader (ExtensionObject) - always null
 */
final readonly class ResponseHeader implements IEncodeable
{
    public function __construct(
        public NodeId $authenticationToken,
        public DateTime $timestamp,
        public int $requestHandle,
        public StatusCode $serviceResult,
    ) {
    }

    /**
     * Create a ResponseHeader with good status
     */
    public static function good(int $requestHandle): self
    {
        return new self(
            authenticationToken: NodeId::numeric(0, 0),
            timestamp: DateTime::now(),
            requestHandle: $requestHandle,
            serviceResult: StatusCode::good(),
        );
    }

    /**
     * Create a ResponseHeader with an error status
     */
    public static function error(int $requestHandle, StatusCode $error): self
    {
        return new self(
            authenticationToken: NodeId::numeric(0, 0),
            timestamp: DateTime::now(),
            requestHandle: $requestHandle,
            serviceResult: $error,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->timestamp->encode($encoder);
        $encoder->writeUInt32($this->requestHandle);
        $this->serviceResult->encode($encoder);

        // ServiceDiagnostics - always null
        DiagnosticInfo::empty()->encode($encoder);

        // StringTable - always empty array
        $encoder->writeInt32(0); // array length

        // AdditionalHeader - always null
        ExtensionObject::empty(NodeId::numeric(0, 0))->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // ResponseHeader does NOT have authenticationToken (that's only in RequestHeader)
        $timestamp = DateTime::decode($decoder);
        $requestHandle = $decoder->readUInt32();
        $serviceResult = StatusCode::decode($decoder);

        // Decode ServiceDiagnostics (DiagnosticInfo)
        $diagnosticMask = $decoder->readByte();
        if ($diagnosticMask !== 0) {
            // There's diagnostic info, decode it properly
            $decoder->setPosition($decoder->getPosition() - 1); // Rewind one byte
            DiagnosticInfo::decode($decoder); // Decode and discard
        }

        // Decode StringTable
        $stringTableLength = $decoder->readInt32();
        for ($i = 0; $i < $stringTableLength; $i++) {
            $decoder->readString();
        }

        // Decode AdditionalHeader (ExtensionObject)
        $additionalHeader = ExtensionObject::decode($decoder);

        return new self(
            authenticationToken: NodeId::numeric(0, 0), // Not transmitted in response
            timestamp: $timestamp,
            requestHandle: $requestHandle,
            serviceResult: $serviceResult,
        );
    }
}
