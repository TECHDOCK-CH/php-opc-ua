<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * EventNotificationList - List of event notifications
 *
 * Part of NotificationMessage, contains events that matched the EventFilter.
 */
final readonly class EventNotificationList implements IEncodeable
{
    /**
     * @param EventFieldList[] $events List of event notifications
     */
    public function __construct(
        public array $events,
    ) {
        foreach ($events as $event) {
            if (!$event instanceof EventFieldList) {
                throw new InvalidArgumentException('Events must be EventFieldList instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeInt32(count($this->events));
        foreach ($this->events as $event) {
            $event->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $eventCount = $decoder->readInt32();
        $events = [];

        for ($i = 0; $i < $eventCount; $i++) {
            $events[] = EventFieldList::decode($decoder);
        }

        return new self($events);
    }

    /**
     * Get the number of events
     */
    public function count(): int
    {
        return count($this->events);
    }

    /**
     * Check if empty
     */
    public function isEmpty(): bool
    {
        return $this->events === [];
    }

    /**
     * Get the OPC UA TypeId for EventNotificationList
     */
    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, 945); // EventNotificationList TypeId
    }
}
