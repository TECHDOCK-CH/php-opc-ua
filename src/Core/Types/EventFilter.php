<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * EventFilter - Filter for event notifications
 *
 * Used with MonitoredItems to filter and select fields from event notifications.
 * - SelectClauses: Specifies which event fields to return
 * - WhereClause: Filters which events to report
 */
final class EventFilter implements IEncodeable
{
    public ContentFilter $whereClause;

    /**
     * @param SimpleAttributeOperand[] $selectClauses Fields to select from events
     * @param ContentFilter|null $whereClause Filter expression (empty = no filtering)
     */
    public function __construct(
        public array $selectClauses = [],
        ?ContentFilter $whereClause = null,
    ) {
        foreach ($selectClauses as $clause) {
            if (!$clause instanceof SimpleAttributeOperand) {
                throw new InvalidArgumentException('Select clauses must be SimpleAttributeOperand instances');
            }
        }

        $this->whereClause = $whereClause ?? ContentFilter::empty();
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode select clauses
        $encoder->writeInt32(count($this->selectClauses));
        foreach ($this->selectClauses as $clause) {
            $clause->encode($encoder);
        }

        // Encode where clause
        $this->whereClause->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // Decode select clauses
        $clauseCount = $decoder->readInt32();
        $selectClauses = [];

        for ($i = 0; $i < $clauseCount; $i++) {
            $selectClauses[] = SimpleAttributeOperand::decode($decoder);
        }

        // Decode where clause
        $whereClause = ContentFilter::decode($decoder);

        return new self(
            selectClauses: $selectClauses,
            whereClause: $whereClause,
        );
    }

    /**
     * Add a select clause for an event field
     */
    public function selectField(SimpleAttributeOperand $operand): self
    {
        $this->selectClauses[] = $operand;
        return $this;
    }

    /**
     * Add a select clause for a simple field (convenience method)
     *
     * @param NodeId $typeDefinitionId Event type (e.g., BaseEventType)
     * @param string[] $browsePath Field path as strings
     */
    public function selectSimpleField(NodeId $typeDefinitionId, array $browsePath): self
    {
        $this->selectClauses[] = SimpleAttributeOperand::fromStrings($typeDefinitionId, $browsePath);
        return $this;
    }

    /**
     * Set the WHERE clause filter
     */
    public function where(ContentFilter $filter): self
    {
        $this->whereClause = $filter;
        return $this;
    }

    /**
     * Create an event filter for common BaseEventType fields
     *
     * Selects: EventId, EventType, SourceName, Time, Message, Severity
     */
    public static function forBaseEventType(NodeId $eventTypeId): self
    {
        $filter = new self();

        // Common event fields
        $filter->selectSimpleField($eventTypeId, ['EventId']);
        $filter->selectSimpleField($eventTypeId, ['EventType']);
        $filter->selectSimpleField($eventTypeId, ['SourceName']);
        $filter->selectSimpleField($eventTypeId, ['Time']);
        $filter->selectSimpleField($eventTypeId, ['Message']);
        $filter->selectSimpleField($eventTypeId, ['Severity']);

        return $filter;
    }
}
