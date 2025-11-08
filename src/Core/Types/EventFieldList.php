<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * EventFieldList - List of event field values
 *
 * Contains the actual values for event fields selected by EventFilter.
 * Each variant corresponds to a SelectClause in the EventFilter.
 */
final readonly class EventFieldList implements IEncodeable
{
    /**
     * @param int $clientHandle Client handle for the monitored item
     * @param Variant[] $eventFields Field values in order of SelectClauses
     */
    public function __construct(
        public int $clientHandle,
        public array $eventFields,
    ) {
        foreach ($eventFields as $field) {
            if (!$field instanceof Variant) {
                throw new InvalidArgumentException('Event fields must be Variant instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $encoder->writeUInt32($this->clientHandle);

        $encoder->writeInt32(count($this->eventFields));
        foreach ($this->eventFields as $field) {
            $field->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $clientHandle = $decoder->readUInt32();

        $fieldCount = $decoder->readInt32();
        $eventFields = [];

        for ($i = 0; $i < $fieldCount; $i++) {
            $eventFields[] = Variant::decode($decoder);
        }

        return new self(
            clientHandle: $clientHandle,
            eventFields: $eventFields,
        );
    }

    /**
     * Get event field by index
     */
    public function getField(int $index): ?Variant
    {
        return $this->eventFields[$index] ?? null;
    }

    /**
     * Get field value (unwrapped from Variant)
     */
    public function getFieldValue(int $index): mixed
    {
        $field = $this->getField($index);
        return $field?->value;
    }

    /**
     * Get the number of fields
     */
    public function count(): int
    {
        return count($this->eventFields);
    }
}
