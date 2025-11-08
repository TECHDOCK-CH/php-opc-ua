<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

/**
 * Types of user identity tokens
 *
 * OPC UA Part 4 - Section 7.36
 */
enum UserIdentityType
{
    /**
     * No authentication required
     */
    case Anonymous;

    /**
     * Username and password authentication
     */
    case UserName;

    /**
     * X.509 certificate authentication
     */
    case Certificate;

    /**
     * Issued token (JWT, Kerberos, etc.)
     */
    case IssuedToken;
}
