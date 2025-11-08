<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Messages;

/**
 * BrowseDirection enumeration
 *
 * Specifies the direction of references to follow
 */
enum BrowseDirection: int
{
    case Forward = 0;       // Follow forward references only
    case Inverse = 1;       // Follow inverse references only
    case Both = 2;          // Follow forward and inverse references
}
