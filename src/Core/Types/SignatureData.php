<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * SignatureData structure
 *
 * Contains the signature algorithm URI and the signature bytes.
 */
final readonly class SignatureData implements IEncodeable
{
    public function __construct(
        public ?string $algorithm,
        public ?string $signature,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeString($this->algorithm);
        $encoder->writeByteString($this->signature);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $algorithm = $decoder->readString();
        $signature = $decoder->readByteString();

        return new self($algorithm, $signature);
    }
}
