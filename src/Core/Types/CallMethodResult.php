<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * CallMethodResult - result of a single method call.
 */
final readonly class CallMethodResult implements IEncodeable
{
    /**
     * @param StatusCode[] $inputArgumentResults
     * @param DiagnosticInfo[] $inputArgumentDiagnosticInfos
     * @param Variant[] $outputArguments
     */
    public function __construct(
        public StatusCode $statusCode,
        public array $inputArgumentResults,
        public array $inputArgumentDiagnosticInfos,
        public array $outputArguments,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->statusCode->encode($encoder);

        $encoder->writeInt32(count($this->inputArgumentResults));
        foreach ($this->inputArgumentResults as $result) {
            $result->encode($encoder);
        }

        $encoder->writeInt32(count($this->inputArgumentDiagnosticInfos));
        foreach ($this->inputArgumentDiagnosticInfos as $diagnostic) {
            $diagnostic->encode($encoder);
        }

        $encoder->writeInt32(count($this->outputArguments));
        foreach ($this->outputArguments as $arg) {
            $arg->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $statusCode = StatusCode::decode($decoder);

        $inputResultCount = $decoder->readInt32();
        $inputArgumentResults = [];
        for ($i = 0; $i < $inputResultCount; $i++) {
            $inputArgumentResults[] = StatusCode::decode($decoder);
        }

        $diagnosticCount = $decoder->readInt32();
        $inputArgumentDiagnosticInfos = [];
        for ($i = 0; $i < $diagnosticCount; $i++) {
            $inputArgumentDiagnosticInfos[] = DiagnosticInfo::decode($decoder);
        }

        $outputCount = $decoder->readInt32();
        $outputArguments = [];
        for ($i = 0; $i < $outputCount; $i++) {
            $outputArguments[] = Variant::decode($decoder);
        }

        return new self(
            statusCode: $statusCode,
            inputArgumentResults: $inputArgumentResults,
            inputArgumentDiagnosticInfos: $inputArgumentDiagnosticInfos,
            outputArguments: $outputArguments,
        );
    }
}
