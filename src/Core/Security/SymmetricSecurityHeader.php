<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * Security header for symmetric encrypted messages (MSG, CLO)
 * Used after secure channel is established
 *
 * Structure (8 bytes):
 * - SecureChannelId (UInt32)
 * - TokenId (UInt32)
 */
final class SymmetricSecurityHeader
{
    public function __construct(
        public readonly int $secureChannelId,
        public readonly int $tokenId,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->secureChannelId);
        $encoder->writeUInt32($this->tokenId);
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        $secureChannelId = $decoder->readUInt32();
        $tokenId = $decoder->readUInt32();

        return new self(
            secureChannelId: $secureChannelId,
            tokenId: $tokenId
        );
    }
}
