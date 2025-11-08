<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * NodeId identifier types as defined in OPC UA spec
 */
enum NodeIdType: int
{
    /** Numeric identifier (UInt32) */
    case Numeric = 0;

    /** String identifier */
    case String = 1;

    /** GUID identifier (16 bytes) */
    case Guid = 2;

    /** Opaque/ByteString identifier */
    case Opaque = 3;
}
