<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use RuntimeException;
use TechDock\OpcUa\Core\Types\AnonymousIdentityToken;
use TechDock\OpcUa\Core\Types\UserNameIdentityToken;
use TechDock\OpcUa\Core\Types\UserTokenPolicy;
use TechDock\OpcUa\Core\Types\UserTokenType;
use TechDock\OpcUa\Core\Types\X509IdentityToken;

/**
 * High-level wrapper for user authentication
 *
 * Provides a clean API for creating different types of user identity tokens.
 */
final class UserIdentity
{
    private const DEFAULT_ANONYMOUS_POLICY = 'Anonymous';

    private function __construct(
        private readonly AnonymousIdentityToken|UserNameIdentityToken|X509IdentityToken $token,
        private readonly UserIdentityType $type,
    ) {
    }

    /**
     * Create an anonymous identity (no authentication)
     */
    public static function anonymous(string $policyId = self::DEFAULT_ANONYMOUS_POLICY): self
    {
        return new self(
            token: new AnonymousIdentityToken(policyId: $policyId),
            type: UserIdentityType::Anonymous,
        );
    }

    /**
     * Create an anonymous identity with auto-detected policyId from session
     *
     * @param Session $session The session to detect the policy from
     */
    public static function anonymousFromSession(Session $session): self
    {
        $policyId = self::detectPolicyId($session, UserTokenType::Anonymous);
        return self::anonymous($policyId);
    }

    /**
     * Create a username/password identity
     *
     * The password will be encrypted before sending to the server using the
     * server's certificate and nonce.
     *
     * @param string $userName Username
     * @param string $password Password (will be encrypted)
     * @param string $policyId Policy ID from server's UserTokenPolicy (default: 'UserName')
     */
    public static function userName(
        string $userName,
        string $password,
        string $policyId = 'UserName'
    ): self {
        return new self(
            token: UserNameIdentityToken::create(
                policyId: $policyId,
                userName: $userName,
                password: $password,
            ),
            type: UserIdentityType::UserName,
        );
    }

    /**
     * Create a username/password identity with auto-detected policyId from session
     *
     * Automatically selects the first available UserName token policy,
     * prioritizing stronger security policies (Basic256Sha256 > Basic256 > Basic128Rsa15)
     *
     * @param Session $session The session to detect the policy from
     * @param string $userName Username
     * @param string $password Password (will be encrypted)
     * @return self
     * @throws RuntimeException If no username policies are available
     */
    public static function userNameFromSession(
        Session $session,
        string $userName,
        string $password
    ): self {
        $endpoint = $session->getSecureChannel()->getSelectedEndpoint();

        if ($endpoint === null) {
            throw new RuntimeException('No endpoint selected - cannot detect policy ID');
        }

        // Find all username policies and sort by security strength
        $usernamePolicies = [];
        foreach ($endpoint->userIdentityTokens as $tokenPolicy) {
            if ($tokenPolicy->tokenType === UserTokenType::UserName) {
                $usernamePolicies[] = $tokenPolicy;
            }
        }

        if ($usernamePolicies === []) {
            throw new RuntimeException(
                'No username authentication policies available on this endpoint. ' .
                'Available token types: ' .
                implode(', ', array_map(
                    fn($p) => $p->tokenType->name,
                    $endpoint->userIdentityTokens
                ))
            );
        }

        // Prioritize stronger security policies
        $selectedPolicy = self::selectStrongestPolicy($usernamePolicies);

        return self::userName($userName, $password, $selectedPolicy->policyId);
    }

    /**
     * Select the strongest security policy from available username policies
     * Priority: Basic256Sha256 > Aes128Sha256RsaOaep > Aes256Sha256RsaPss > Basic256 > Basic128Rsa15
     *
     * @param UserTokenPolicy[] $policies
     * @return UserTokenPolicy
     */
    private static function selectStrongestPolicy(array $policies): UserTokenPolicy
    {
        $priorityMap = [
            'Basic256Sha256' => 100,
            'Aes256Sha256RsaPss' => 90,
            'Aes128Sha256RsaOaep' => 85,
            'Basic256' => 50,
            'Basic128Rsa15' => 30,
        ];

        usort($policies, function ($a, $b) use ($priorityMap) {
            $scoreA = 0;
            $scoreB = 0;

            foreach ($priorityMap as $algorithm => $score) {
                if (str_contains($a->securityPolicyUri ?? '', $algorithm)) {
                    $scoreA = $score;
                }
                if (str_contains($b->securityPolicyUri ?? '', $algorithm)) {
                    $scoreB = $score;
                }
            }

            return $scoreB - $scoreA; // Descending order
        });

        return $policies[0];
    }

    /**
     * Create an X.509 certificate identity
     *
     * @param string $certificatePem PEM-encoded X.509 certificate
     * @param string $policyId Policy ID from server's UserTokenPolicy
     */
    public static function certificate(
        string $certificatePem,
        string $policyId = 'Certificate'
    ): self {
        return new self(
            token: X509IdentityToken::fromPem(
                policyId: $policyId,
                certificatePem: $certificatePem,
            ),
            type: UserIdentityType::Certificate,
        );
    }

    /**
     * Get the underlying identity token
     */
    public function getToken(): AnonymousIdentityToken|UserNameIdentityToken|X509IdentityToken
    {
        return $this->token;
    }

    /**
     * Get the identity type
     */
    public function getType(): UserIdentityType
    {
        return $this->type;
    }

    /**
     * Check if this is an anonymous identity
     */
    public function isAnonymous(): bool
    {
        return $this->type === UserIdentityType::Anonymous;
    }

    /**
     * Check if this identity requires encryption
     */
    public function requiresEncryption(): bool
    {
        return $this->type !== UserIdentityType::Anonymous;
    }

    /**
     * Get the policy ID
     */
    public function getPolicyId(): string
    {
        return $this->token->policyId;
    }

    /**
     * Detect the appropriate policyId from a session's endpoint for a given token type
     *
     * @param Session $session The session to query
     * @param UserTokenType $tokenType The token type to find a policy for
     * @return string The detected policyId
     * @throws RuntimeException If no policy is found
     */
    private static function detectPolicyId(Session $session, UserTokenType $tokenType): string
    {
        $endpoint = $session->getSecureChannel()->getSelectedEndpoint();

        if ($endpoint === null) {
            throw new RuntimeException('No endpoint selected - cannot detect policy ID');
        }

        foreach ($endpoint->userIdentityTokens as $tokenPolicy) {
            if ($tokenPolicy->tokenType === $tokenType) {
                return $tokenPolicy->policyId;
            }
        }

        throw new RuntimeException(
            "No user token policy found for token type: {$tokenType->name}"
        );
    }
}
