<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Types\AttributeId;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\NodeIdType;
use TechDock\OpcUa\Core\Types\ReadValueId;
use TechDock\OpcUa\Core\Types\StructureDefinition;
use Throwable;

/**
 * TypeCache for caching DataType structure definitions
 *
 * Queries the server for DataTypeDefinition attributes and caches the results.
 * This allows dynamic decoding of ExtensionObjects without pre-compiled type knowledge.
 */
final class TypeCache
{
    /**
     * @var array<string, StructureDefinition> Cache keyed by TypeId string (e.g., "ns=0;i=862")
     */
    private array $cache = [];

    public function __construct(
        private readonly Session $session,
    ) {
    }

    /**
     * Get the StructureDefinition for a given DataType NodeId or Encoding NodeId
     *
     * @param NodeId $typeId The DataType NodeId (e.g., ns=0;i=862 for ServerStatusDataType)
     *                       or Encoding NodeId (e.g., ns=0;i=864 for ServerStatusDataType Binary Encoding)
     * @return StructureDefinition|null The structure definition, or null if not found/not a structure
     */
    public function getStructureDefinition(NodeId $typeId): ?StructureDefinition
    {
        // For standard types (namespace 0), encoding NodeId is typically DataType NodeId + 2
        // Try to convert encoding ID to DataType ID for ns=0
        $dataTypeId = $typeId;
        if ($typeId->namespaceIndex === 0 && $typeId->type === NodeIdType::Numeric) {
            $numericId = $typeId->identifier;
            assert(is_int($numericId));
            // If this looks like an encoding ID (even number > 20), try the DataType ID
            if ($numericId > 20 && $numericId % 2 === 0) {
                $dataTypeId = NodeId::numeric(0, $numericId - 2);
            }
        }

        $key = $dataTypeId->toString();

        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // Read DataTypeDefinition attribute from server
        try {
            $dataValues = $this->session->read([
                ReadValueId::attribute($dataTypeId, AttributeId::DATA_TYPE_DEFINITION),
            ]);

            if (count($dataValues) === 0) {
                return null;
            }

            $dataValue = $dataValues[0];

            // Check if read was successful
            if (!($dataValue->statusCode?->isGood() ?? false)) {
                return null;
            }

            if ($dataValue->value === null) {
                return null;
            }

            // The DataTypeDefinition attribute returns an ExtensionObject containing StructureDefinition
            $value = $dataValue->value->value;

            if (!$value instanceof ExtensionObject) {
                return null;
            }

            // Decode the ExtensionObject body as StructureDefinition
            if (!$value->isBinary() || $value->body === null) {
                return null;
            }

            $structureDef = StructureDefinition::decode(
                new BinaryDecoder($value->body)
            );

            // Cache the result
            $this->cache[$key] = $structureDef;

            return $structureDef;
        } catch (Throwable) {
            // If anything fails, return null
            // Servers may not support DataTypeDefinition (OPC UA 1.04+)
            return null;
        }
    }

    /**
     * Clear the cache
     */
    public function clear(): void
    {
        $this->cache = [];
    }

    /**
     * Check if a type is cached
     */
    public function isCached(NodeId $typeId): bool
    {
        return isset($this->cache[$typeId->toString()]);
    }

    /**
     * Preload structure definitions for multiple types
     *
     * @param NodeId[] $typeIds Array of DataType NodeIds to preload
     * @return int Number of definitions successfully loaded
     */
    public function preload(array $typeIds): int
    {
        $loaded = 0;
        foreach ($typeIds as $typeId) {
            if ($this->getStructureDefinition($typeId) !== null) {
                $loaded++;
            }
        }
        return $loaded;
    }

    /**
     * Get cache statistics
     *
     * @return array{count: int, types: string[]}
     */
    public function getStats(): array
    {
        return [
            'count' => count($this->cache),
            'types' => array_keys($this->cache),
        ];
    }
}
