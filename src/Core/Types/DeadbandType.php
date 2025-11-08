<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * DeadbandType enumeration.
 *
 * Specifies the deadband type for data change filters.
 */
enum DeadbandType: int
{
    case None = 0;
    case Absolute = 1;
    case Percent = 2;

    /**
     * Get the label for this deadband type.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Absolute => 'Absolute',
            self::Percent => 'Percent',
        };
    }
}
