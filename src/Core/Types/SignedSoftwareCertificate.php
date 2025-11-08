<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * SignedSoftwareCertificate structure
 *
 * Contains a software certificate and its corresponding signature.
 */
final readonly class SignedSoftwareCertificate implements IEncodeable
{
    public function __construct(
        public ?string $certificateData,
        public ?string $signature,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeByteString($this->certificateData);
        $encoder->writeByteString($this->signature);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $certificateData = $decoder->readByteString();
        $signature = $decoder->readByteString();

        return new self($certificateData, $signature);
    }
}
