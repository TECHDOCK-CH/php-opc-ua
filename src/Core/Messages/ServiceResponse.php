<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Optional marker for responses to allow type verification.
 */
interface ServiceResponse
{
    public static function getTypeId(): NodeId;
}
