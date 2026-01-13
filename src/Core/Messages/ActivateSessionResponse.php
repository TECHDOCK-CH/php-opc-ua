<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\DiagnosticInfo;
use TechDock\OpcUa\Core\Types\StatusCode;

/**
 * ActivateSessionResponse - Response to ActivateSessionRequest
 */
final readonly class ActivateSessionResponse implements IEncodeable
{
    /**
     * @param array<int, mixed> $results
     * @param array<int, mixed> $diagnosticInfos
     */
    public function __construct(
        public ResponseHeader $responseHeader,
        public string $serverNonce,
        public array $results,
        public array $diagnosticInfos,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);
        $encoder->writeByteString($this->serverNonce);

        // Results
        $encoder->writeInt32(count($this->results));
        foreach ($this->results as $result) {
            if ($result instanceof StatusCode) {
                $result->encode($encoder);
            } else {
                $encoder->writeUInt32((int)$result);
            }
        }

        // Diagnostic infos
        $encoder->writeInt32(count($this->diagnosticInfos));
        foreach ($this->diagnosticInfos as $info) {
            if ($info instanceof DiagnosticInfo) {
                $info->encode($encoder);
            } else {
                $encoder->writeByte(0); // Empty diagnostic info fallback
            }
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);
        $serverNonce = $decoder->readByteString() ?? '';

        // Results
        $resultCount = $decoder->readArrayLength();
        $results = [];
        for ($i = 0; $i < $resultCount; $i++) {
            $results[] = StatusCode::decode($decoder);
        }

        // Diagnostic infos
        $diagnosticCount = $decoder->readArrayLength();
        $diagnosticInfos = [];
        for ($i = 0; $i < $diagnosticCount; $i++) {
            $diagnosticInfos[] = DiagnosticInfo::decode($decoder);
        }

        return new self(
            responseHeader: $responseHeader,
            serverNonce: $serverNonce,
            results: $results,
            diagnosticInfos: $diagnosticInfos,
        );
    }
}
