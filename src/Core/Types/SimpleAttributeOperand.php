<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * SimpleAttributeOperand - Simplified operand for event filtering
 *
 * Specifies an attribute of an Event field using a browse path.
 * Commonly used in EventFilter SelectClauses to specify which event fields to return.
 *
 * Example: To get the "Message" field from a BaseEventType:
 *   new SimpleAttributeOperand(
 *       typeDefinitionId: ObjectTypeIds::BaseEventType,
 *       browsePath: [new QualifiedName(0, 'Message')],
 *       attributeId: Attributes::Value
 *   )
 */
final readonly class SimpleAttributeOperand implements IEncodeable
{
    /**
     * @param NodeId $typeDefinitionId Type definition node (e.g., BaseEventType)
     * @param QualifiedName[] $browsePath Path from type to desired property
     * @param int $attributeId Attribute to read (13 = Value)
     * @param string|null $indexRange Index range for arrays (null = all)
     */
    public function __construct(
        public NodeId $typeDefinitionId,
        public array $browsePath,
        public int $attributeId = 13, // Attributes::Value
        public ?string $indexRange = null,
    ) {
        foreach ($browsePath as $element) {
            if (!$element instanceof QualifiedName) {
                throw new InvalidArgumentException('Browse path must contain only QualifiedName instances');
            }
        }
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->typeDefinitionId->encode($encoder);

        // Encode browse path
        $encoder->writeInt32(count($this->browsePath));
        foreach ($this->browsePath as $name) {
            $name->encode($encoder);
        }

        $encoder->writeUInt32($this->attributeId);
        $encoder->writeString($this->indexRange);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $typeDefinitionId = NodeId::decode($decoder);

        // Decode browse path
        $pathLength = $decoder->readInt32();
        $browsePath = [];
        for ($i = 0; $i < $pathLength; $i++) {
            $browsePath[] = QualifiedName::decode($decoder);
        }

        $attributeId = $decoder->readUInt32();
        $indexRange = $decoder->readString();

        return new self(
            typeDefinitionId: $typeDefinitionId,
            browsePath: $browsePath,
            attributeId: $attributeId,
            indexRange: $indexRange,
        );
    }

    /**
     * Create with string browse path (convenience method)
     *
     * @param NodeId $typeDefinitionId Type definition
     * @param string[] $browsePathNames Simple string names (namespace 0 assumed)
     * @param int $attributeId Attribute ID
     */
    public static function fromStrings(
        NodeId $typeDefinitionId,
        array $browsePathNames,
        int $attributeId = 13
    ): self {
        $browsePath = [];
        foreach ($browsePathNames as $name) {
            $browsePath[] = new QualifiedName(0, $name);
        }

        return new self(
            typeDefinitionId: $typeDefinitionId,
            browsePath: $browsePath,
            attributeId: $attributeId,
        );
    }
}
