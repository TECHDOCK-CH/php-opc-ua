<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Security\ChannelSecurityToken;

/**
 * OpenSecureChannelResponse - Server response with security token
 *
 * Structure:
 * - ResponseHeader
 * - ServerProtocolVersion (UInt32)
 * - SecurityToken (ChannelSecurityToken)
 * - ServerNonce (ByteString)
 */
final readonly class OpenSecureChannelResponse implements IEncodeable
{
    public function __construct(
        public ResponseHeader $responseHeader,
        public int $serverProtocolVersion,
        public ChannelSecurityToken $securityToken,
        public ?string $serverNonce,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->responseHeader->encode($encoder);
        $encoder->writeUInt32($this->serverProtocolVersion);
        $this->securityToken->encode($encoder);
        $encoder->writeByteString($this->serverNonce);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $responseHeader = ResponseHeader::decode($decoder);

        // Check if the response is actually a ServiceFault (error response)
        if (!$responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "Server returned error: {$responseHeader->serviceResult}"
            );
        }

        $serverProtocolVersion = $decoder->readUInt32();
        $securityToken = ChannelSecurityToken::decode($decoder);
        $serverNonce = $decoder->readByteString();

        return new self(
            responseHeader: $responseHeader,
            serverProtocolVersion: $serverProtocolVersion,
            securityToken: $securityToken,
            serverNonce: $serverNonce,
        );
    }
}
