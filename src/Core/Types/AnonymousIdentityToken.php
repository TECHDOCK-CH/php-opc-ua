<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * AnonymousIdentityToken structure
 *
 * Only contains the PolicyId string.
 */
final readonly class AnonymousIdentityToken implements IEncodeable
{
    public function __construct(public string $policyId)
    {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeString($this->policyId);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $policyId = $decoder->readString() ?? '';
        return new self($policyId);
    }
}
