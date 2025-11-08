<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * MonitoringMode enumeration.
 *
 * Specifies the monitoring mode for a monitored item.
 */
enum MonitoringMode: int
{
    case Disabled = 0;
    case Sampling = 1;
    case Reporting = 2;

    /**
     * Get the label for this monitoring mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Disabled',
            self::Sampling => 'Sampling',
            self::Reporting => 'Reporting',
        };
    }
}
