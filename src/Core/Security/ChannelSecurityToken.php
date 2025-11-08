<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\DateTime;

/**
 * Security token for a secure channel
 *
 * Structure:
 * - ChannelId (UInt32)
 * - TokenId (UInt32)
 * - CreatedAt (DateTime)
 * - RevisedLifetime (UInt32) - in milliseconds
 */
final readonly class ChannelSecurityToken implements IEncodeable
{
    public function __construct(
        public int $channelId,
        public int $tokenId,
        public DateTime $createdAt,
        public int $revisedLifetime,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->channelId);
        $encoder->writeUInt32($this->tokenId);
        $this->createdAt->encode($encoder);
        $encoder->writeUInt32($this->revisedLifetime);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $channelId = $decoder->readUInt32();
        $tokenId = $decoder->readUInt32();
        $createdAt = DateTime::decode($decoder);
        $revisedLifetime = $decoder->readUInt32();

        return new self(
            channelId: $channelId,
            tokenId: $tokenId,
            createdAt: $createdAt,
            revisedLifetime: $revisedLifetime,
        );
    }
}
