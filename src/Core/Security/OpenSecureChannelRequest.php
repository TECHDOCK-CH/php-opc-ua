<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Services\RequestHeader;

/**
 * OpenSecureChannel Request
 *
 * Structure:
 * - RequestHeader
 * - ClientProtocolVersion (UInt32)
 * - RequestType (SecurityTokenRequestType)
 * - SecurityMode (MessageSecurityMode)
 * - ClientNonce (ByteString) - can be null for SecurityMode::None
 * - RequestedLifetime (UInt32) - in milliseconds
 */
final readonly class OpenSecureChannelRequest
{
    public const int DEFAULT_LIFETIME = 600000; // 10 minutes

    public function __construct(
        public RequestHeader $requestHeader,
        public int $clientProtocolVersion,
        public SecurityTokenRequestType $requestType,
        public MessageSecurityMode $securityMode,
        public ?string $clientNonce,
        public int $requestedLifetime,
    ) {
        if ($clientProtocolVersion < 0) {
            throw new InvalidArgumentException(
                "Client protocol version cannot be negative, got {$clientProtocolVersion}"
            );
        }

        if ($requestedLifetime < 0) {
            throw new InvalidArgumentException(
                "Requested lifetime cannot be negative, got {$requestedLifetime}"
            );
        }

        if ($securityMode !== MessageSecurityMode::None && $clientNonce === null) {
            throw new InvalidArgumentException('Client nonce is required for non-None security mode');
        }
    }

    /**
     * Create request for None security (no encryption)
     */
    public static function none(int $requestHandle = 1): self
    {
        return new self(
            requestHeader: RequestHeader::create($requestHandle),
            clientProtocolVersion: 0,
            requestType: SecurityTokenRequestType::Issue,
            securityMode: MessageSecurityMode::None,
            clientNonce: null,
            requestedLifetime: self::DEFAULT_LIFETIME,
        );
    }

    /**
     * Encode the request body (without message header)
     */
    public function encodeBody(BinaryEncoder $encoder): void
    {
        $this->requestHeader->encode($encoder);
        $encoder->writeUInt32($this->clientProtocolVersion);
        $encoder->writeUInt32($this->requestType->value);
        $encoder->writeUInt32($this->securityMode->value);
        $encoder->writeByteString($this->clientNonce);
        $encoder->writeUInt32($this->requestedLifetime);
    }
}
