<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\DataValue;
use TechDock\OpcUa\Core\Types\DiagnosticInfo;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * ReadResponse - contains the results for a read service call.
 *
 * @phpstan-type DiagnosticInfoArray array<int, DiagnosticInfo>
 */
final readonly class ReadResponse implements IEncodeable, ServiceResponse
{
    private const int TYPE_ID = 634;

    /**
     * @param DataValue[] $results
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
            $results[] = DataValue::decode($decoder);
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
