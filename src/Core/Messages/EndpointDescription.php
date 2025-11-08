<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;
use TechDock\OpcUa\Core\Types\ApplicationDescription;
use TechDock\OpcUa\Core\Types\UserTokenPolicy;

/**
 * Describes an OPC UA server endpoint
 */
final class EndpointDescription
{
    /**
     * @param string $endpointUrl The URL for the endpoint
     * @param ApplicationDescription $server The server application description
     * @param string|null $serverCertificate The server certificate (DER encoded)
     * @param SecurityPolicy $securityPolicy The security policy used by the endpoint
     * @param MessageSecurityMode $securityMode The security mode used by the endpoint
     * @param UserTokenPolicy[] $userIdentityTokens The user identity tokens supported
     * @param string $transportProfileUri The transport profile URI
     * @param int $securityLevel The security level of the endpoint
     */
    public function __construct(
        public readonly string $endpointUrl,
        public readonly ApplicationDescription $server,
        public readonly ?string $serverCertificate,
        public readonly SecurityPolicy $securityPolicy,
        public readonly MessageSecurityMode $securityMode,
        public readonly array $userIdentityTokens,
        public readonly string $transportProfileUri,
        public readonly int $securityLevel,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeString($this->endpointUrl);
        $this->server->encode($encoder);
        $encoder->writeByteString($this->serverCertificate);
        $encoder->writeInt32($this->securityMode->value);
        $encoder->writeString($this->securityPolicy->uri());

        $encoder->writeInt32(count($this->userIdentityTokens));
        foreach ($this->userIdentityTokens as $token) {
            $token->encode($encoder);
        }

        $encoder->writeString($this->transportProfileUri);
        $encoder->writeByte($this->securityLevel);
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        $endpointUrl = $decoder->readString();
        $server = ApplicationDescription::decode($decoder);
        $serverCertificate = $decoder->readByteString();
        $securityModeValue = $decoder->readInt32();
        $securityMode = MessageSecurityMode::from($securityModeValue);
        $securityPolicyUri = $decoder->readString();

        if ($securityPolicyUri === null) {
            throw new RuntimeException('Security policy URI cannot be null');
        }

        $securityPolicy = SecurityPolicy::fromUri($securityPolicyUri);

        $tokenCount = $decoder->readInt32();
        $userIdentityTokens = [];
        for ($i = 0; $i < $tokenCount; $i++) {
            $userIdentityTokens[] = UserTokenPolicy::decode($decoder);
        }

        $transportProfileUri = $decoder->readString();
        $securityLevel = $decoder->readByte();

        if ($endpointUrl === null) {
            throw new RuntimeException('Endpoint URL cannot be null');
        }

        if ($transportProfileUri === null) {
            throw new RuntimeException('Transport profile URI cannot be null');
        }

        return new self(
            endpointUrl: $endpointUrl,
            server: $server,
            serverCertificate: $serverCertificate,
            securityPolicy: $securityPolicy,
            securityMode: $securityMode,
            userIdentityTokens: $userIdentityTokens,
            transportProfileUri: $transportProfileUri,
            securityLevel: $securityLevel,
        );
    }
}
