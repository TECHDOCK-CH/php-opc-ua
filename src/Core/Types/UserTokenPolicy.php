<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * User token types
 */
enum UserTokenType: int
{
    case Anonymous = 0;
    case UserName = 1;
    case Certificate = 2;
    case IssuedToken = 3;
}

/**
 * Describes a user identity token policy
 */
final class UserTokenPolicy
{
    public function __construct(
        public readonly string $policyId,
        public readonly UserTokenType $tokenType,
        public readonly ?string $issuedTokenType = null,
        public readonly ?string $issuerEndpointUrl = null,
        public readonly ?string $securityPolicyUri = null,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeString($this->policyId);
        $encoder->writeInt32($this->tokenType->value);
        $encoder->writeString($this->issuedTokenType);
        $encoder->writeString($this->issuerEndpointUrl);
        $encoder->writeString($this->securityPolicyUri);
    }

    public static function decode(BinaryDecoder $decoder): self
    {
        $policyId = $decoder->readString();
        $tokenType = UserTokenType::from($decoder->readInt32());
        $issuedTokenType = $decoder->readString();
        $issuerEndpointUrl = $decoder->readString();
        $securityPolicyUri = $decoder->readString();

        if ($policyId === null) {
            throw new RuntimeException('Policy ID cannot be null');
        }

        return new self(
            policyId: $policyId,
            tokenType: $tokenType,
            issuedTokenType: $issuedTokenType,
            issuerEndpointUrl: $issuerEndpointUrl,
            securityPolicyUri: $securityPolicyUri,
        );
    }
}
