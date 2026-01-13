<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * BrowseResponse - Response to a Browse request
 */
final readonly class BrowseResponse implements IEncodeable
{
    /**
     * @param BrowseResult[] $results
     * @param array<int, mixed> $diagnosticInfos
     */
    public function __construct(
        public ResponseHeader $responseHeader,
        public array $results,
        public array $diagnosticInfos,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);

        $encoder->writeInt32(count($this->results));
        foreach ($this->results as $result) {
            $result->encode($encoder);
        }

        $encoder->writeInt32(count($this->diagnosticInfos));
        foreach ($this->diagnosticInfos as $diagnosticInfo) {
            $diagnosticInfo->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);

        $resultCount = $decoder->readArrayLength();
        $results = [];
        for ($i = 0; $i < $resultCount; $i++) {
            $results[] = BrowseResult::decode($decoder);
        }

        $diagnosticCount = $decoder->readArrayLength();
        $diagnosticInfos = [];
        for ($i = 0; $i < $diagnosticCount; $i++) {
            $diagnosticInfos[] = DiagnosticInfo::decode($decoder);
        }

        return new self(
            responseHeader: $responseHeader,
            results: $results,
            diagnosticInfos: $diagnosticInfos,
        );
    }
}
