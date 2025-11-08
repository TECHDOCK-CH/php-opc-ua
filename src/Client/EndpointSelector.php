<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use TechDock\OpcUa\Core\Messages\EndpointDescription;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;

/**
 * EndpointSelector - Utility for selecting the best endpoint from available options
 *
 * Implements intelligent endpoint selection based on security preferences and requirements.
 */
final class EndpointSelector
{
    /**
     * Select the best endpoint from a list based on security preferences
     *
     * Priority order:
     * 1. Highest security mode (SignAndEncrypt > Sign > None)
     * 2. Strongest security policy (Basic256Sha256 > others)
     * 3. Highest security level value
     *
     * @param EndpointDescription[] $endpoints Available endpoints
     * @param MessageSecurityMode|null $preferredSecurityMode Preferred security mode (null = highest available)
     * @param SecurityPolicy|null $preferredSecurityPolicy Preferred security policy (null = strongest available)
     * @return EndpointDescription|null The best matching endpoint, or null if none found
     */
    public static function selectBest(
        array $endpoints,
        ?MessageSecurityMode $preferredSecurityMode = null,
        ?SecurityPolicy $preferredSecurityPolicy = null,
    ): ?EndpointDescription {
        if ($endpoints === []) {
            return null;
        }

        // Filter by preferred security mode if specified
        if ($preferredSecurityMode !== null) {
            $filtered = array_filter(
                $endpoints,
                fn(EndpointDescription $ep) => $ep->securityMode === $preferredSecurityMode
            );
            if ($filtered !== []) {
                $endpoints = array_values($filtered);
            }
        }

        // Filter by preferred security policy if specified
        if ($preferredSecurityPolicy !== null) {
            $filtered = array_filter(
                $endpoints,
                fn(EndpointDescription $ep) => $ep->securityPolicy === $preferredSecurityPolicy
            );
            if ($filtered !== []) {
                $endpoints = array_values($filtered);
            }
        }

        // Sort by security (strongest first)
        usort($endpoints, function (EndpointDescription $a, EndpointDescription $b): int {
            // 1. Compare security mode
            $modeScore = self::getSecurityModeScore($b->securityMode) <=>
                self::getSecurityModeScore($a->securityMode);
            if ($modeScore !== 0) {
                return $modeScore;
            }

            // 2. Compare security policy
            $policyScore = self::getSecurityPolicyScore($b->securityPolicy) <=>
                self::getSecurityPolicyScore($a->securityPolicy);
            if ($policyScore !== 0) {
                return $policyScore;
            }

            // 3. Compare security level
            return $b->securityLevel <=> $a->securityLevel;
        });

        return $endpoints[0];
    }

    /**
     * Select endpoint with highest security
     *
     * @param EndpointDescription[] $endpoints Available endpoints
     */
    public static function selectHighestSecurity(array $endpoints): ?EndpointDescription
    {
        return self::selectBest($endpoints);
    }

    /**
     * Select endpoint with no security (for testing/development)
     *
     * @param EndpointDescription[] $endpoints Available endpoints
     */
    public static function selectNoSecurity(array $endpoints): ?EndpointDescription
    {
        return self::selectBest(
            endpoints: $endpoints,
            preferredSecurityMode: MessageSecurityMode::None,
            preferredSecurityPolicy: SecurityPolicy::None,
        );
    }

    /**
     * Select endpoint by URL
     *
     * @param EndpointDescription[] $endpoints Available endpoints
     * @param string $url Endpoint URL to match
     */
    public static function selectByUrl(array $endpoints, string $url): ?EndpointDescription
    {
        foreach ($endpoints as $endpoint) {
            if ($endpoint->endpointUrl === $url) {
                return $endpoint;
            }
        }
        return null;
    }

    /**
     * Filter endpoints by transport profile
     *
     * @param EndpointDescription[] $endpoints Available endpoints
     * @param string $transportProfileUri Transport profile URI (e.g., OPC UA TCP)
     * @return EndpointDescription[]
     */
    public static function filterByTransport(array $endpoints, string $transportProfileUri): array
    {
        return array_values(array_filter(
            $endpoints,
            fn(EndpointDescription $ep) => $ep->transportProfileUri === $transportProfileUri
        ));
    }

    /**
     * Filter endpoints by security mode
     *
     * @param EndpointDescription[] $endpoints Available endpoints
     * @param MessageSecurityMode $securityMode Security mode to filter by
     * @return EndpointDescription[]
     */
    public static function filterBySecurityMode(
        array $endpoints,
        MessageSecurityMode $securityMode,
    ): array {
        return array_values(array_filter(
            $endpoints,
            fn(EndpointDescription $ep) => $ep->securityMode === $securityMode
        ));
    }

    /**
     * Filter endpoints by security policy
     *
     * @param EndpointDescription[] $endpoints Available endpoints
     * @param SecurityPolicy $securityPolicy Security policy to filter by
     * @return EndpointDescription[]
     */
    public static function filterBySecurityPolicy(
        array $endpoints,
        SecurityPolicy $securityPolicy,
    ): array {
        return array_values(array_filter(
            $endpoints,
            fn(EndpointDescription $ep) => $ep->securityPolicy === $securityPolicy
        ));
    }

    /**
     * Get all endpoints sorted by security (strongest first)
     *
     * @param EndpointDescription[] $endpoints Available endpoints
     * @return EndpointDescription[]
     */
    public static function sortBySecurity(array $endpoints): array
    {
        $sorted = $endpoints;
        usort($sorted, function (EndpointDescription $a, EndpointDescription $b): int {
            $modeScore = self::getSecurityModeScore($b->securityMode) <=>
                self::getSecurityModeScore($a->securityMode);
            if ($modeScore !== 0) {
                return $modeScore;
            }

            $policyScore = self::getSecurityPolicyScore($b->securityPolicy) <=>
                self::getSecurityPolicyScore($a->securityPolicy);
            if ($policyScore !== 0) {
                return $policyScore;
            }

            return $b->securityLevel <=> $a->securityLevel;
        });
        return $sorted;
    }

    /**
     * Get security mode score (higher = more secure)
     */
    private static function getSecurityModeScore(MessageSecurityMode $mode): int
    {
        return match ($mode) {
            MessageSecurityMode::SignAndEncrypt => 3,
            MessageSecurityMode::Sign => 2,
            MessageSecurityMode::None => 1,
        };
    }

    /**
     * Get security policy score (higher = more secure)
     */
    private static function getSecurityPolicyScore(SecurityPolicy $policy): int
    {
        return match ($policy) {
            SecurityPolicy::Basic256Sha256 => 4,
            SecurityPolicy::Aes128Sha256RsaOaep => 3,
            SecurityPolicy::Aes256Sha256RsaPss => 5,  // Strongest
            SecurityPolicy::None => 1,
            default => 2,
        };
    }
}
