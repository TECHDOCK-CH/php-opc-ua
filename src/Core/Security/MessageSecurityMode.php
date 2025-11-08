<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Security;

/**
 * OPC UA Message Security Modes
 *
 * Determines how messages are protected:
 * - None: No protection
 * - Sign: Messages are signed but not encrypted
 * - SignAndEncrypt: Messages are both signed and encrypted
 */
enum MessageSecurityMode: int
{
    /**
     * No message security
     * Only valid with SecurityPolicy::None
     */
    case None = 1;

    /**
     * Messages are signed but not encrypted
     * Provides integrity but not confidentiality
     */
    case Sign = 2;

    /**
     * Messages are signed and encrypted
     * Provides both integrity and confidentiality
     * Recommended for production use
     */
    case SignAndEncrypt = 3;
}
