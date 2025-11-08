<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * TimestampsToReturn enumeration.
 *
 * Specifies which timestamps to return in a read response.
 */
enum TimestampsToReturn: int
{
    case Source = 0;
    case Server = 1;
    case Both = 2;
    case Neither = 3;
    case Invalid = 4;

    /**
     * Convert the enum to a StatusCode-compatible string.
     */
    public function label(): string
    {
        return match ($this) {
            self::Source => 'Source',
            self::Server => 'Server',
            self::Both => 'Both',
            self::Neither => 'Neither',
            self::Invalid => 'Invalid',
        };
    }
}
