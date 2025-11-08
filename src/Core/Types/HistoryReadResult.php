<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * HistoryReadResult - Result of reading history for a single node
 *
 * Contains the historical data and any continuation point for paging.
 *
 * OPC UA Specification Part 4, Section 5.10.3
 */
final readonly class HistoryReadResult implements IEncodeable
{
    /**
     * @param StatusCode $statusCode Status of the operation
     * @param ByteString|null $continuationPoint Continuation point for reading more data
     * @param ExtensionObject $historyData Historical data (HistoryData, HistoryModifiedData, etc.)
     */
    public function __construct(
        public StatusCode $statusCode,
        public ?ByteString $continuationPoint,
        public ExtensionObject $historyData,
    ) {
    }

    /**
     * Check if there is more data available (continuation point exists)
     */
    public function hasMoreData(): bool
    {
        return $this->continuationPoint !== null && !$this->continuationPoint->isEmpty();
    }

    /**
     * Check if the operation was successful
     */
    public function isGood(): bool
    {
        return $this->statusCode->isGood();
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode status code
        $this->statusCode->encode($encoder);

        // Encode continuation point
        if ($this->continuationPoint === null) {
            $encoder->writeByteString(null);
        } else {
            $this->continuationPoint->encode($encoder);
        }

        // Encode history data
        $this->historyData->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $statusCode = StatusCode::decode($decoder);
        $continuationPoint = ByteString::decode($decoder);
        $historyData = ExtensionObject::decode($decoder);

        return new self(
            statusCode: $statusCode,
            continuationPoint: $continuationPoint->isEmpty() ? null : $continuationPoint,
            historyData: $historyData,
        );
    }
}
