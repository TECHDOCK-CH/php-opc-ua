<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

/**
 * OPC UA TCP message types (3-byte ASCII codes)
 */
enum MessageType: string
{
    case Hello = 'HEL';
    case Acknowledge = 'ACK';
    case Error = 'ERR';
    case ReverseHello = 'RHE';
    case Message = 'MSG';
    case OpenSecureChannel = 'OPN';
    case CloseSecureChannel = 'CLO';
}
