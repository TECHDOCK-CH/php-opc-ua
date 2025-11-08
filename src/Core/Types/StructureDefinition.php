<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * StructureDefinition provides metadata for a Structure DataType
 *
 * Contains information about the structure's base type, encoding,
 * and the list of fields that make up the structure.
 */
final readonly class StructureDefinition implements IEncodeable
{
    /**
     * @param NodeId $defaultEncodingId NodeId of the default binary encoding
     * @param NodeId $baseDataType NodeId of the base data type
     * @param StructureType $structureType Type of structure
     * @param StructureField[] $fields Array of fields in the structure
     */
    public function __construct(
        public NodeId $defaultEncodingId,
        public NodeId $baseDataType,
        public StructureType $structureType,
        public array $fields,
    ) {
        foreach ($fields as $field) {
            if (!$field instanceof StructureField) {
                throw new InvalidArgumentException('All fields must be StructureField instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        // Encode defaultEncodingId
        $this->defaultEncodingId->encode($encoder);

        // Encode baseDataType
        $this->baseDataType->encode($encoder);

        // Encode structureType
        $encoder->writeInt32($this->structureType->value);

        // Encode fields array
        $encoder->writeInt32(count($this->fields));
        foreach ($this->fields as $field) {
            $field->encode($encoder);
        }
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        // Decode defaultEncodingId
        $defaultEncodingId = NodeId::decode($decoder);

        // Decode baseDataType
        $baseDataType = NodeId::decode($decoder);

        // Decode structureType
        $structureTypeValue = $decoder->readInt32();
        $structureType = StructureType::from($structureTypeValue);

        // Decode fields array
        $fieldsCount = $decoder->readInt32();
        if ($fieldsCount < 0) {
            throw new RuntimeException('StructureDefinition fields count cannot be negative');
        }

        $fields = [];
        for ($i = 0; $i < $fieldsCount; $i++) {
            $fields[] = StructureField::decode($decoder);
        }

        return new self(
            defaultEncodingId: $defaultEncodingId,
            baseDataType: $baseDataType,
            structureType: $structureType,
            fields: $fields,
        );
    }

    /**
     * Get the TypeId for StructureDefinition
     */
    public static function getTypeId(): NodeId
    {
        return NodeId::numeric(0, 99); // StructureDefinition TypeId
    }

    /**
     * Get a field by name
     */
    public function getField(string $name): ?StructureField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Get all field names
     *
     * @return string[]
     */
    public function getFieldNames(): array
    {
        return array_map(fn($field) => $field->name, $this->fields);
    }

    /**
     * Get the number of fields
     */
    public function getFieldCount(): int
    {
        return count($this->fields);
    }

    /**
     * Get string representation
     */
    public function toString(): string
    {
        $fieldCount = count($this->fields);
        $typeStr = $this->structureType->name;
        return "StructureDefinition({$typeStr}, {$fieldCount} fields)";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
