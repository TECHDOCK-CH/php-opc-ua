<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * ServerState enumeration
 *
 * Indicates the current state of the Server.
 */
enum ServerState: int
{
    /**
     * The Server is running normally
     */
    case Running = 0;

    /**
     * The Server has encountered a fatal error
     */
    case Failed = 1;

    /**
     * The Server does not have enough information to operate
     */
    case NoConfiguration = 2;

    /**
     * The Server is temporarily suspended
     */
    case Suspended = 3;

    /**
     * The Server is shutting down
     */
    case Shutdown = 4;

    /**
     * The Server is in test mode
     */
    case Test = 5;

    /**
     * The Server cannot communicate with underlying devices
     */
    case CommunicationFault = 6;

    /**
     * The Server state is unknown
     */
    case Unknown = 7;
}
