<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

/**
 * FilterOperator enumeration
 *
 * Operators used in ContentFilter for event filtering and queries
 */
enum FilterOperator: int
{
    case Equals = 0;                // Equal to
    case IsNull = 1;                // Is null
    case GreaterThan = 2;           // Greater than
    case LessThan = 3;              // Less than
    case GreaterThanOrEqual = 4;    // Greater than or equal
    case LessThanOrEqual = 5;       // Less than or equal
    case Like = 6;                  // String pattern match (SQL LIKE)
    case Not = 7;                   // Logical NOT
    case Between = 8;               // Between two values
    case InList = 9;                // In a list of values
    case And = 10;                  // Logical AND
    case Or = 11;                   // Logical OR
    case Cast = 12;                 // Type cast
    case InView = 13;               // Node is in view
    case OfType = 14;               // Object is of type
    case RelatedTo = 15;            // Related by reference
    case BitwiseAnd = 16;           // Bitwise AND
    case BitwiseOr = 17;            // Bitwise OR
}
