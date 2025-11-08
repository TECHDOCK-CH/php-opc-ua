<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * StructureType enumeration
 *
 * Indicates whether a Structure is a normal Structure, a Structure with optional fields,
 * or a Union.
 */
enum StructureType: int
{
    /**
     * Structure with mandatory fields (no optional fields)
     */
    case Structure = 0;

    /**
     * Structure that can have optional fields
     */
    case StructureWithOptionalFields = 1;

    /**
     * Union type (only one field at a time)
     */
    case Union = 2;
}
