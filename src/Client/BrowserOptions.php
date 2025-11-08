<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use TechDock\OpcUa\Core\Messages\BrowseDirection;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * Configuration options for Browser
 *
 * Provides a fluent API for configuring browse operations.
 */
final class BrowserOptions
{
    /**
     * Create browser options with defaults
     *
     * @param BrowseDirection $browseDirection Direction of references to follow
     * @param NodeId|null $referenceTypeId Filter by reference type (null = all types)
     * @param bool $includeSubtypes Include subtypes of reference type
     * @param int $nodeClassMask Bit mask of NodeClass values (0 = all classes)
     * @param int $resultMask Bit mask of BrowseResultMask values (63 = all fields)
     * @param int $maxReferencesPerNode Maximum references per node (0 = server default)
     */
    public function __construct(
        public BrowseDirection $browseDirection = BrowseDirection::Forward,
        public ?NodeId $referenceTypeId = null,
        public bool $includeSubtypes = true,
        public int $nodeClassMask = 0,
        public int $resultMask = 63,
        public int $maxReferencesPerNode = 1000,
    ) {
    }

    /**
     * Set browse direction
     */
    public function withDirection(BrowseDirection $direction): self
    {
        $clone = clone $this;
        $clone->browseDirection = $direction;
        return $clone;
    }

    /**
     * Browse only forward references
     */
    public function onlyForward(): self
    {
        return $this->withDirection(BrowseDirection::Forward);
    }

    /**
     * Browse only inverse references
     */
    public function onlyInverse(): self
    {
        return $this->withDirection(BrowseDirection::Inverse);
    }

    /**
     * Browse both forward and inverse references
     */
    public function both(): self
    {
        return $this->withDirection(BrowseDirection::Both);
    }

    /**
     * Filter by reference type
     */
    public function withReferenceType(NodeId $referenceTypeId, bool $includeSubtypes = true): self
    {
        $clone = clone $this;
        $clone->referenceTypeId = $referenceTypeId;
        $clone->includeSubtypes = $includeSubtypes;
        return $clone;
    }

    /**
     * Filter by hierarchical references (organizes, hasComponent, etc.)
     */
    public function hierarchicalOnly(): self
    {
        return $this->withReferenceType(NodeId::numeric(0, 33), includeSubtypes: true);
    }

    /**
     * Filter by node class mask
     *
     * NodeClass values:
     * - 1 = Object
     * - 2 = Variable
     * - 4 = Method
     * - 8 = ObjectType
     * - 16 = VariableType
     * - 32 = ReferenceType
     * - 64 = DataType
     * - 128 = View
     */
    public function withNodeClassMask(int $mask): self
    {
        $clone = clone $this;
        $clone->nodeClassMask = $mask;
        return $clone;
    }

    /**
     * Only browse Objects
     */
    public function objectsOnly(): self
    {
        return $this->withNodeClassMask(1);
    }

    /**
     * Only browse Variables
     */
    public function variablesOnly(): self
    {
        return $this->withNodeClassMask(2);
    }

    /**
     * Only browse Methods
     */
    public function methodsOnly(): self
    {
        return $this->withNodeClassMask(4);
    }

    /**
     * Browse Objects and Variables only (common case)
     */
    public function objectsAndVariables(): self
    {
        return $this->withNodeClassMask(1 | 2); // Object | Variable
    }

    /**
     * Set result mask
     *
     * BrowseResultMask values:
     * - 1 = ReferenceTypeId
     * - 2 = IsForward
     * - 4 = NodeClass
     * - 8 = BrowseName
     * - 16 = DisplayName
     * - 32 = TypeDefinition
     * - 63 = All
     */
    public function withResultMask(int $mask): self
    {
        $clone = clone $this;
        $clone->resultMask = $mask;
        return $clone;
    }

    /**
     * Set maximum references per node
     */
    public function withMaxReferences(int $max): self
    {
        $clone = clone $this;
        $clone->maxReferencesPerNode = $max;
        return $clone;
    }

    /**
     * Create default options
     */
    public static function defaults(): self
    {
        return new self();
    }

    /**
     * Create options optimized for performance (minimal result fields)
     */
    public static function minimal(): self
    {
        return new self(
            resultMask: 4 | 8, // NodeClass + BrowseName only
        );
    }

    /**
     * Create options for browsing a full address space
     */
    public static function fullAddressSpace(): self
    {
        return new self(
            browseDirection: BrowseDirection::Forward,
            referenceTypeId: NodeId::numeric(0, 33), // HierarchicalReferences
            includeSubtypes: true,
            nodeClassMask: 0, // All node classes
            resultMask: 63, // All fields
            maxReferencesPerNode: 10000,
        );
    }
}
