<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * Security Token Request Types
 *
 * Indicates whether opening a new secure channel or renewing an existing one
 */
enum SecurityTokenRequestType: int
{
    /**
     * Creating a new secure channel
     */
    case Issue = 0;

    /**
     * Renewing an existing secure channel
     */
    case Renew = 1;
}
