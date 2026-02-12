<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\HistoryReadValueId;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\ReadRawModifiedDetails;
use TechDock\OpcUa\Core\Types\TimestampsToReturn;

/**
 * HistoryReadRequest - Request to read historical data
 *
 * OPC UA Specification Part 4, Section 5.10.3
 */
final class HistoryReadRequest implements ServiceRequest
{
    private const int TYPE_ID = 662;

    /**
     * @param RequestHeader $requestHeader Request header
     * @param ExtensionObject $historyReadDetails Details of what to read (ReadRawModifiedDetails, etc.)
     * @param TimestampsToReturn $timestampsToReturn Which timestamps to return
     * @param bool $releaseContinuationPoints Release continuation points without reading more data
     * @param HistoryReadValueId[] $nodesToRead Nodes to read history from
     */
    public function __construct(
        public readonly RequestHeader $requestHeader,
        public readonly ExtensionObject $historyReadDetails,
        public readonly TimestampsToReturn $timestampsToReturn,
        public readonly bool $releaseContinuationPoints,
        public readonly array $nodesToRead,
    ) {
    }

    /**
     * Create a request for reading raw historical data
     *
     * @param ReadRawModifiedDetails $details Read details (time range, limits)
     * @param HistoryReadValueId[] $nodesToRead Nodes to read
     * @param TimestampsToReturn $timestampsToReturn Which timestamps to return
     */
    public static function forRawData(
        ReadRawModifiedDetails $details,
        array $nodesToRead,
        TimestampsToReturn $timestampsToReturn = TimestampsToReturn::Both,
    ): self {
        $detailsExtension = ExtensionObject::fromEncodeable(
            NodeId::numeric(0, 647), // ReadRawModifiedDetails TypeId
            $details
        );

        return new self(
            requestHeader: RequestHeader::create(),
            historyReadDetails: $detailsExtension,
            timestampsToReturn: $timestampsToReturn,
            releaseContinuationPoints: false,
            nodesToRead: $nodesToRead,
        );
    }

    /**
     * Create a request to release continuation points
     *
     * @param HistoryReadValueId[] $nodesToRead Nodes with continuation points to release
     */
    public static function releaseContinuationPoints(array $nodesToRead): self
    {
        // Empty details when releasing continuation points
        $emptyDetails = ExtensionObject::empty(NodeId::numeric(0, 0));

        return new self(
            requestHeader: RequestHeader::create(),
            historyReadDetails: $emptyDetails,
            timestampsToReturn: TimestampsToReturn::Neither,
            releaseContinuationPoints: true,
            nodesToRead: $nodesToRead,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode request header
        $this->requestHeader->encode($encoder);

        // Encode history read details
        $this->historyReadDetails->encode($encoder);

        // Encode timestamps to return
        $encoder->writeInt32($this->timestampsToReturn->value);

        // Encode release continuation points flag
        $encoder->writeBoolean($this->releaseContinuationPoints);

        // Encode nodes to read array
        $encoder->writeInt32(count($this->nodesToRead));
        foreach ($this->nodesToRead as $node) {
            $node->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $historyReadDetails = ExtensionObject::decode($decoder);
        $timestampsToReturn = TimestampsToReturn::from($decoder->readInt32());
        $releaseContinuationPoints = $decoder->readBoolean();

        $nodeCount = $decoder->readInt32();
        $nodesToRead = [];
        for ($i = 0; $i < $nodeCount; $i++) {
            $nodesToRead[] = HistoryReadValueId::decode($decoder);
        }

        return new self(
            requestHeader: $requestHeader,
            historyReadDetails: $historyReadDetails,
            timestampsToReturn: $timestampsToReturn,
            releaseContinuationPoints: $releaseContinuationPoints,
            nodesToRead: $nodesToRead,
        );
    }

    public function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }

    public function getRequestHeader(): RequestHeader
    {
        return $this->requestHeader;
    }
}
