<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * Standard OPC UA Attribute IDs
 *
 * These constants identify different attributes that can be read from nodes.
 * Used with ReadValueId to specify which attribute to read.
 */
final class AttributeId
{
    public const int NODE_ID = 1;
    public const int NODE_CLASS = 2;
    public const int BROWSE_NAME = 3;
    public const int DISPLAY_NAME = 4;
    public const int DESCRIPTION = 5;
    public const int WRITE_MASK = 6;
    public const int USER_WRITE_MASK = 7;
    public const int IS_ABSTRACT = 8;
    public const int SYMMETRIC = 9;
    public const int INVERSE_NAME = 10;
    public const int CONTAINS_NO_LOOPS = 11;
    public const int EVENT_NOTIFIER = 12;
    public const int VALUE = 13; // Default for variable nodes
    public const int DATA_TYPE = 14;
    public const int VALUE_RANK = 15;
    public const int ARRAY_DIMENSIONS = 16;
    public const int ACCESS_LEVEL = 17;
    public const int USER_ACCESS_LEVEL = 18;
    public const int MINIMUM_SAMPLING_INTERVAL = 19;
    public const int HISTORIZING = 20;
    public const int EXECUTABLE = 21;
    public const int USER_EXECUTABLE = 22;
    public const int DATA_TYPE_DEFINITION = 23; // OPC UA 1.04+
    public const int ROLE_PERMISSIONS = 24;
    public const int USER_ROLE_PERMISSIONS = 25;
    public const int ACCESS_RESTRICTIONS = 26;
    public const int ACCESS_LEVEL_EX = 27;

    private function __construct()
    {
        // Prevent instantiation - this is a constants class
    }
}
