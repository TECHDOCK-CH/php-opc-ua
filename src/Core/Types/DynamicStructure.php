<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Client\TypeCache;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use Throwable;

// Import well-known types

/**
 * DynamicStructure - dynamically decoded structure from ExtensionObject
 *
 * Decodes structure data based on StructureDefinition metadata from the server.
 * Returns field values as an associative array.
 */
final class DynamicStructure
{
    /**
     * Decode an ExtensionObject body into an associative array or typed object
     *
     * @param ExtensionObject $extensionObject The extension object to decode
     * @param TypeCache|null $typeCache Optional type cache for dynamic type discovery
     * @return array<string, mixed>|object|null Decoded fields/object, or null if cannot decode
     */
    public static function decode(ExtensionObject $extensionObject, ?TypeCache $typeCache = null): array|object|null
    {
        // Only decode binary-encoded objects
        if (!$extensionObject->isBinary() || $extensionObject->body === null) {
            return null;
        }

        // Try well-known types first (hardcoded, no server query needed)
        $decoded = self::decodeWellKnownType($extensionObject);
        if ($decoded !== null) {
            return $decoded;
        }

        // Fall back to dynamic type discovery if TypeCache provided
        if ($typeCache === null) {
            return null;
        }

        // Get the structure definition from the cache
        $structureDef = $typeCache->getStructureDefinition($extensionObject->typeId);
        if ($structureDef === null) {
            return null;
        }

        // Decode the body
        $decoder = new BinaryDecoder($extensionObject->body);

        // Handle Union types - only one field is present
        if ($structureDef->structureType === StructureType::Union) {
            return self::decodeUnion($decoder, $structureDef, $typeCache);
        }

        // Handle structures with optional fields
        $encodingMask = null;
        if ($structureDef->structureType === StructureType::StructureWithOptionalFields) {
            // Read encoding mask (UInt32) indicating which optional fields are present
            $encodingMask = $decoder->readUInt32();
        }

        $fields = [];
        $optionalFieldIndex = 0;

        foreach ($structureDef->fields as $fieldDef) {
            $fieldName = $fieldDef->name;

            // Check if this optional field is present
            if ($fieldDef->isOptional && $encodingMask !== null) {
                $isPresent = (($encodingMask >> $optionalFieldIndex) & 1) === 1;
                $optionalFieldIndex++;

                if (!$isPresent) {
                    // Field not present, set to null and skip
                    $fields[$fieldName] = null;
                    continue;
                }
            }

            // Decode based on field data type
            try {
                $value = self::decodeField($decoder, $fieldDef, $typeCache);
                $fields[$fieldName] = $value;
            } catch (Throwable $e) {
                // If decoding fails, stop and return what we have
                break;
            }
        }

        return $fields;
    }

    /**
     * Try to decode as a well-known standard type
     *
     * These are standard OPC UA types that all clients should understand.
     *
     * @return object|null Decoded object or null if not a known type
     */
    private static function decodeWellKnownType(ExtensionObject $extensionObject): ?object
    {
        if ($extensionObject->body === null) {
            return null;
        }

        $decoder = new BinaryDecoder($extensionObject->body);

        // Check the TypeId - for standard types in ns=0, the encoding ID is typically dataTypeId + 2
        if (
            $extensionObject->typeId->namespaceIndex !== 0 ||
            $extensionObject->typeId->type !== NodeIdType::Numeric
        ) {
            return null;
        }

        $typeId = $extensionObject->typeId->identifier;
        assert(is_int($typeId));

        try {
            return match ($typeId) {
                864 => ServerStatusDataType::decode($decoder), // ServerStatusDataType encoding
                340 => BuildInfo::decode($decoder),            // BuildInfo encoding
                // Add more well-known types as needed
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Decode a Union type structure
     *
     * Union types encode a switch field (UInt32) indicating which field is present,
     * followed by the field value. Switch value 0 means no field is present.
     *
     * @param BinaryDecoder $decoder Binary decoder positioned at the union data
     * @param StructureDefinition $structureDef Structure definition
     * @param TypeCache $typeCache Type cache for nested structures
     * @return array<string, mixed> Decoded fields (with selected field only)
     */
    private static function decodeUnion(
        BinaryDecoder $decoder,
        StructureDefinition $structureDef,
        TypeCache $typeCache
    ): array {
        // Read switch field (UInt32) - 0 means no field, 1+ is field index (1-based)
        $switchValue = $decoder->readUInt32();

        $fields = [];

        // Switch value 0 means null/empty union
        if ($switchValue === 0) {
            return $fields;
        }

        // Switch value is 1-based field index
        $fieldIndex = $switchValue - 1;

        if ($fieldIndex >= count($structureDef->fields)) {
            // Invalid switch value - return empty
            return $fields;
        }

        $fieldDef = $structureDef->fields[$fieldIndex];

        try {
            $value = self::decodeField($decoder, $fieldDef, $typeCache);
            $fields[$fieldDef->name] = $value;
        } catch (Throwable) {
            // Decoding failed, return empty
        }

        return $fields;
    }

    /**
     * Decode a single field based on its definition
     *
     * @param BinaryDecoder $decoder Binary decoder positioned at the field
     * @param StructureField $fieldDef Field definition
     * @param TypeCache $typeCache Type cache for nested structures
     * @return mixed Decoded field value
     */
    private static function decodeField(
        BinaryDecoder $decoder,
        StructureField $fieldDef,
        TypeCache $typeCache
    ): mixed {
        // Check if this is an array
        if ($fieldDef->valueRank >= 1) {
            return self::decodeArray($decoder, $fieldDef, $typeCache);
        }

        // Decode scalar value
        return self::decodeScalar($decoder, $fieldDef->dataType, $typeCache);
    }

    /**
     * Decode an array field
     *
     * @return array<mixed>
     */
    private static function decodeArray(
        BinaryDecoder $decoder,
        StructureField $fieldDef,
        TypeCache $typeCache
    ): array {
        // Read array length
        $length = $decoder->readInt32();

        if ($length < 0) {
            // Null array
            return [];
        }

        $array = [];
        for ($i = 0; $i < $length; $i++) {
            $array[] = self::decodeScalar($decoder, $fieldDef->dataType, $typeCache);
        }

        return $array;
    }

    /**
     * Decode a nested ExtensionObject and recursively decode its body
     *
     * This method prevents returning raw ExtensionObjects with binary bodies
     * by recursively decoding them using the same DynamicStructure logic.
     *
     * @param BinaryDecoder $decoder Binary decoder
     * @param TypeCache $typeCache Type cache for type discovery
     * @return mixed Decoded nested ExtensionObject (as object or array), or raw ExtensionObject if decoding fails
     */
    private static function decodeNestedExtensionObject(
        BinaryDecoder $decoder,
        TypeCache $typeCache
    ): mixed {
        // First decode the ExtensionObject structure itself
        $extensionObject = ExtensionObject::decode($decoder);

        // If it's not binary or body is null, return as-is
        if (!$extensionObject->isBinary() || $extensionObject->body === null) {
            return $extensionObject;
        }

        // Try to recursively decode the ExtensionObject body
        $decoded = self::decode($extensionObject, $typeCache);

        // If decoding succeeded, return the decoded object/array
        // Otherwise, return the raw ExtensionObject
        return $decoded ?? $extensionObject;
    }

    /**
     * Decode a scalar value based on its data type NodeId
     */
    private static function decodeScalar(
        BinaryDecoder $decoder,
        NodeId $dataType,
        TypeCache $typeCache
    ): mixed {
        // Check if this is a built-in type (namespace 0, numeric)
        if ($dataType->namespaceIndex === 0 && $dataType->type === NodeIdType::Numeric) {
            $typeId = $dataType->identifier;
            assert(is_int($typeId));

            return match ($typeId) {
                1 => $decoder->readBoolean(),          // Boolean
                2 => $decoder->readSByte(),            // SByte
                3 => $decoder->readByte(),             // Byte
                4 => $decoder->readInt16(),            // Int16
                5 => $decoder->readUInt16(),           // UInt16
                6 => $decoder->readInt32(),            // Int32
                7 => $decoder->readUInt32(),           // UInt32
                8 => $decoder->readInt64(),            // Int64
                9 => $decoder->readUInt64(),           // UInt64
                10 => $decoder->readFloat(),           // Float
                11 => $decoder->readDouble(),          // Double
                12 => $decoder->readString(),          // String
                13 => DateTime::decode($decoder),      // DateTime
                14 => $decoder->readGuid(),            // Guid
                15 => $decoder->readByteString(),      // ByteString
                16 => $decoder->readString(),          // XmlElement (encoded as String)
                17 => NodeId::decode($decoder),        // NodeId
                18 => ExpandedNodeId::decode($decoder), // ExpandedNodeId
                19 => StatusCode::decode($decoder),    // StatusCode
                20 => QualifiedName::decode($decoder), // QualifiedName
                21 => LocalizedText::decode($decoder), // LocalizedText
                // ExtensionObject (nested, recursively decoded)
                22 => self::decodeNestedExtensionObject($decoder, $typeCache),
                23 => DataValue::decode($decoder),     // DataValue
                24 => Variant::decode($decoder),       // Variant
                25 => DiagnosticInfo::decode($decoder), // DiagnosticInfo
                default => null, // Unknown built-in type
            };
        }

        // Not a built-in type - might be a custom structure
        // Try to decode as ExtensionObject
        try {
            return ExtensionObject::decode($decoder);
        } catch (Throwable) {
            return null;
        }
    }
}
