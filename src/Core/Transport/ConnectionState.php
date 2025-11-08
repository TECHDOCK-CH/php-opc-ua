<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

/**
 * TCP connection states
 */
enum ConnectionState: string
{
    case Disconnected = 'disconnected';
    case Connecting = 'connecting';
    case Connected = 'connected';
    case Closing = 'closing';
    case Closed = 'closed';
    case Error = 'error';
}
