<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

use RuntimeException;

/**
 * Factory for creating security policy handlers
 */
final class SecurityPolicyFactory
{
    /**
     * Create a handler for the specified security policy
     *
     * @throws RuntimeException If policy is not supported
     */
    public static function createHandler(SecurityPolicy $policy): SecurityPolicyHandlerInterface
    {
        return match ($policy) {
            SecurityPolicy::None => new NoneHandler(),
            SecurityPolicy::Basic256Sha256 => new Basic256Sha256Handler(),
            default => throw new RuntimeException(
                "Security policy {$policy->value} is not yet implemented. " .
                "Currently supported: None, Basic256Sha256"
            ),
        };
    }

    /**
     * Check if a security policy is supported
     */
    public static function isSupported(SecurityPolicy $policy): bool
    {
        return match ($policy) {
            SecurityPolicy::None,
            SecurityPolicy::Basic256Sha256 => true,
            default => false,
        };
    }

    /**
     * Get list of all supported security policies
     *
     * @return SecurityPolicy[]
     */
    public static function getSupportedPolicies(): array
    {
        return [
            SecurityPolicy::None,
            SecurityPolicy::Basic256Sha256,
        ];
    }
}
