<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\ByteString;
use TechDock\OpcUa\Core\Types\DiagnosticInfo;
use TechDock\OpcUa\Core\Types\HistoryReadResult;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * HistoryReadResponse - Response to a history read request
 *
 * OPC UA Specification Part 4, Section 5.10.3
 */
final class HistoryReadResponse implements ServiceResponse
{
    private const int TYPE_ID = 665;

    /**
     * @param ResponseHeader $responseHeader Response header
     * @param HistoryReadResult[] $results Results for each node
     * @param DiagnosticInfo[] $diagnosticInfos Diagnostic information
     */
    public function __construct(
        public readonly ResponseHeader $responseHeader,
        public readonly array $results,
        public readonly array $diagnosticInfos,
    ) {
    }

    /**
     * Check if any results have continuation points
     */
    public function hasMoreData(): bool
    {
        foreach ($this->results as $result) {
            if ($result->hasMoreData()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get continuation points from results
     *
     * @return array<int, ByteString> Map of result index to continuation point
     */
    public function getContinuationPoints(): array
    {
        $continuationPoints = [];
        foreach ($this->results as $i => $result) {
            if ($result->hasMoreData() && $result->continuationPoint !== null) {
                $continuationPoints[$i] = $result->continuationPoint;
            }
        }
        return $continuationPoints;
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode response header
        $this->responseHeader->encode($encoder);

        // Encode results array
        $encoder->writeInt32(count($this->results));
        foreach ($this->results as $result) {
            $result->encode($encoder);
        }

        // Encode diagnostic infos array
        $encoder->writeInt32(count($this->diagnosticInfos));
        foreach ($this->diagnosticInfos as $diagnosticInfo) {
            $diagnosticInfo->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);

        $resultCount = $decoder->readInt32();
        $results = [];
        for ($i = 0; $i < $resultCount; $i++) {
            $results[] = HistoryReadResult::decode($decoder);
        }

        $diagnosticInfoCount = $decoder->readInt32();
        $diagnosticInfos = [];
        for ($i = 0; $i < $diagnosticInfoCount; $i++) {
            $diagnosticInfos[] = DiagnosticInfo::decode($decoder);
        }

        return new self(
            responseHeader: $responseHeader,
            results: $results,
            diagnosticInfos: $diagnosticInfos,
        );
    }

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
