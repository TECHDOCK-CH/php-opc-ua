<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * AttributeOperand - Operand that specifies an attribute of an object or variable
 *
 * More complex than SimpleAttributeOperand, allows relative paths from nodes.
 * Used in ContentFilter WHERE clauses for advanced filtering.
 */
final readonly class AttributeOperand implements IEncodeable
{
    /**
     * @param NodeId $nodeId Starting node
     * @param string $alias Alias for the operand
     * @param RelativePath $browsePath Relative path from the node
     * @param int $attributeId Attribute to read
     * @param string|null $indexRange Index range for arrays
     */
    public function __construct(
        public NodeId $nodeId,
        public string $alias,
        public RelativePath $browsePath,
        public int $attributeId,
        public ?string $indexRange = null,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->nodeId->encode($encoder);
        $encoder->writeString($this->alias);
        $this->browsePath->encode($encoder);
        $encoder->writeUInt32($this->attributeId);
        $encoder->writeString($this->indexRange);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $nodeId = NodeId::decode($decoder);
        $alias = $decoder->readString() ?? '';
        $browsePath = RelativePath::decode($decoder);
        $attributeId = $decoder->readUInt32();
        $indexRange = $decoder->readString();

        return new self(
            nodeId: $nodeId,
            alias: $alias,
            browsePath: $browsePath,
            attributeId: $attributeId,
            indexRange: $indexRange,
        );
    }
}
