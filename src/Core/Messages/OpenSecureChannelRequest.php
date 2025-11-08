<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityTokenRequestType;

/**
 * OpenSecureChannelRequest - Opens or renews a secure channel
 *
 * Structure:
 * - RequestHeader
 * - ClientProtocolVersion (UInt32)
 * - RequestType (SecurityTokenRequestType)
 * - SecurityMode (MessageSecurityMode)
 * - ClientNonce (ByteString)
 * - RequestedLifetime (UInt32) - in milliseconds
 */
final readonly class OpenSecureChannelRequest implements IEncodeable
{
    public const int DEFAULT_PROTOCOL_VERSION = 0;
    public const int DEFAULT_LIFETIME = 600000; // 10 minutes

    public function __construct(
        public RequestHeader $requestHeader,
        public int $clientProtocolVersion,
        public SecurityTokenRequestType $requestType,
        public MessageSecurityMode $securityMode,
        public ?string $clientNonce,
        public int $requestedLifetime,
    ) {
    }

    /**
     * Create a new secure channel request
     */
    public static function issue(
        MessageSecurityMode $securityMode = MessageSecurityMode::None,
        ?string $clientNonce = null,
        int $requestedLifetime = self::DEFAULT_LIFETIME,
    ): self {
        return new self(
            requestHeader: RequestHeader::create(),
            clientProtocolVersion: self::DEFAULT_PROTOCOL_VERSION,
            requestType: SecurityTokenRequestType::Issue,
            securityMode: $securityMode,
            clientNonce: $clientNonce,
            requestedLifetime: $requestedLifetime,
        );
    }

    /**
     * Create a renew secure channel request
     */
    public static function renew(
        MessageSecurityMode $securityMode = MessageSecurityMode::None,
        ?string $clientNonce = null,
        int $requestedLifetime = self::DEFAULT_LIFETIME,
    ): self {
        return new self(
            requestHeader: RequestHeader::create(),
            clientProtocolVersion: self::DEFAULT_PROTOCOL_VERSION,
            requestType: SecurityTokenRequestType::Renew,
            securityMode: $securityMode,
            clientNonce: $clientNonce,
            requestedLifetime: $requestedLifetime,
        );
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeUInt32($this->clientProtocolVersion);
        $encoder->writeUInt32($this->requestType->value);
        $encoder->writeUInt32($this->securityMode->value);
        $encoder->writeByteString($this->clientNonce);
        $encoder->writeUInt32($this->requestedLifetime);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $requestHeader = RequestHeader::decode($decoder);
        $clientProtocolVersion = $decoder->readUInt32();
        $requestType = SecurityTokenRequestType::from($decoder->readUInt32());
        $securityMode = MessageSecurityMode::from($decoder->readUInt32());
        $clientNonce = $decoder->readByteString();
        $requestedLifetime = $decoder->readUInt32();

        return new self(
            requestHeader: $requestHeader,
            clientProtocolVersion: $clientProtocolVersion,
            requestType: $requestType,
            securityMode: $securityMode,
            clientNonce: $clientNonce,
            requestedLifetime: $requestedLifetime,
        );
    }
}
