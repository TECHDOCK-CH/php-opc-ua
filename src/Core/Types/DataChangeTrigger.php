<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * DataChangeTrigger enumeration.
 *
 * Specifies the conditions under which a data change notification is reported.
 */
enum DataChangeTrigger: int
{
    case Status = 0;
    case StatusValue = 1;
    case StatusValueTimestamp = 2;

    /**
     * Get the label for this trigger.
     */
    public function label(): string
    {
        return match ($this) {
            self::Status => 'Status',
            self::StatusValue => 'StatusValue',
            self::StatusValueTimestamp => 'StatusValueTimestamp',
        };
    }
}
