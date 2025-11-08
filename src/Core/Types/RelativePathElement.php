<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * RelativePathElement - A single step in a relative path
 *
 * Specifies a reference to follow and a target browse name.
 */
final readonly class RelativePathElement implements IEncodeable
{
    public function __construct(
        public NodeId $referenceTypeId,
        public bool $isInverse,
        public bool $includeSubtypes,
        public QualifiedName $targetName,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->referenceTypeId->encode($encoder);
        $encoder->writeBoolean($this->isInverse);
        $encoder->writeBoolean($this->includeSubtypes);
        $this->targetName->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $referenceTypeId = NodeId::decode($decoder);
        $isInverse = $decoder->readBoolean();
        $includeSubtypes = $decoder->readBoolean();
        $targetName = QualifiedName::decode($decoder);

        return new self(
            referenceTypeId: $referenceTypeId,
            isInverse: $isInverse,
            includeSubtypes: $includeSubtypes,
            targetName: $targetName,
        );
    }

    /**
     * Create with hierarchical forward reference (most common case)
     */
    public static function hierarchical(string $targetName, int $namespaceIndex = 0): self
    {
        return new self(
            referenceTypeId: NodeId::numeric(0, 33), // HierarchicalReferences
            isInverse: false,
            includeSubtypes: true,
            targetName: new QualifiedName($namespaceIndex, $targetName),
        );
    }
}
