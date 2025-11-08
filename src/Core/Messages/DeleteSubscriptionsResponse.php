<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Types\DiagnosticInfo;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\StatusCode;

/**
 * DeleteSubscriptionsResponse - result of deleting subscriptions.
 */
final readonly class DeleteSubscriptionsResponse implements ServiceResponse
{
    private const int TYPE_ID = 802;

    /**
     * @param StatusCode[] $results
     * @param DiagnosticInfo[] $diagnosticInfos
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
        foreach ($this->diagnosticInfos as $diagnostic) {
            $diagnostic->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);

        $resultCount = $decoder->readInt32();
        $results = [];
        for ($i = 0; $i < $resultCount; $i++) {
            $results[] = StatusCode::decode($decoder);
        }

        $diagnosticCount = $decoder->readInt32();
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

    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, self::TYPE_ID);
    }
}
