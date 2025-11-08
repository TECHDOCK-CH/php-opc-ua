<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

use TechDock\OpcUa\Core\Encoding\IEncodeable;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Marker interface for OPC UA service requests.
 * Provides the TypeId required in the MSG body.
 */
interface ServiceRequest extends IEncodeable
{
    public function getTypeId(): NodeId;
}
