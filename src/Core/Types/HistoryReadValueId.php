<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * HistoryReadValueId - Identifies a node and its history to read
 *
 * Specifies the node and data encoding for history read operations.
 *
 * OPC UA Specification Part 4, Section 7.4.3.133
 */
final readonly class HistoryReadValueId implements IEncodeable
{
    /**
     * @param NodeId $nodeId The node to read history from
     * @param string|null $indexRange Range of array indices (e.g., "1:5")
     * @param QualifiedName|null $dataEncoding Data encoding to use
     * @param ByteString|null $continuationPoint Continuation point from previous read
     */
    public function __construct(
        public NodeId $nodeId,
        public ?string $indexRange = null,
        public ?QualifiedName $dataEncoding = null,
        public ?ByteString $continuationPoint = null,
    ) {
    }

    /**
     * Create a simple history read value ID
     *
     * @param NodeId $nodeId Node to read history from
     */
    public static function create(NodeId $nodeId): self
    {
        return new self(
            nodeId: $nodeId,
            indexRange: null,
            dataEncoding: null,
            continuationPoint: null,
        );
    }

    /**
     * Create with continuation point for paging
     *
     * @param NodeId $nodeId Node to read history from
     * @param ByteString $continuationPoint Continuation point from previous read
     */
    public static function withContinuationPoint(
        NodeId $nodeId,
        ByteString $continuationPoint,
    ): self {
        return new self(
            nodeId: $nodeId,
            indexRange: null,
            dataEncoding: null,
            continuationPoint: $continuationPoint,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode node ID
        $this->nodeId->encode($encoder);

        // Encode index range
        $encoder->writeString($this->indexRange);

        // Encode data encoding
        if ($this->dataEncoding === null) {
            // Encode null qualified name (namespace index 0, empty name)
            $encoder->writeUInt16(0);
            $encoder->writeString(null);
        } else {
            $this->dataEncoding->encode($encoder);
        }

        // Encode continuation point
        if ($this->continuationPoint === null) {
            $encoder->writeByteString(null);
        } else {
            $this->continuationPoint->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $nodeId = NodeId::decode($decoder);
        $indexRange = $decoder->readString();
        $dataEncoding = QualifiedName::decode($decoder);
        $continuationPoint = ByteString::decode($decoder);

        // Check if data encoding is null (namespace index 0 and empty name)
        $isDataEncodingNull = ($dataEncoding->namespaceIndex === 0 &&
            $dataEncoding->name === '');

        return new self(
            nodeId: $nodeId,
            indexRange: $indexRange,
            dataEncoding: $isDataEncodingNull ? null : $dataEncoding,
            continuationPoint: $continuationPoint->isEmpty() ? null : $continuationPoint,
        );
    }
}
