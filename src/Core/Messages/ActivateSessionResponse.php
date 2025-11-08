<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

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
        $encoder->writeUInt32(count($this->results));
        foreach ($this->results as $result) {
            // TODO: Implement StatusCode array encoding
            $encoder->writeUInt32(0);
        }

        // Diagnostic infos
        $encoder->writeUInt32(count($this->diagnosticInfos));
        foreach ($this->diagnosticInfos as $info) {
            $encoder->writeByte(0); // Empty diagnostic info
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);
        $serverNonce = $decoder->readByteString() ?? '';

        // Results
        $resultCount = $decoder->readUInt32();
        $results = [];
        for ($i = 0; $i < $resultCount; $i++) {
            $results[] = $decoder->readUInt32();
        }

        // Diagnostic infos
        $diagnosticCount = $decoder->readUInt32();
        $diagnosticInfos = [];
        for ($i = 0; $i < $diagnosticCount; $i++) {
            $diagnosticInfos[] = $decoder->readByte();
        }

        return new self(
            responseHeader: $responseHeader,
            serverNonce: $serverNonce,
            results: $results,
            diagnosticInfos: $diagnosticInfos,
        );
    }
}
