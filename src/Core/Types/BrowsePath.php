<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Types;

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\IEncodeable;

/**
 * BrowsePath - Specifies a path from a starting node to a target node
 *
 * Used in TranslateBrowsePathsToNodeIds service to resolve paths to NodeIds.
 * Example: Translate "Objects/Server/ServerStatus/CurrentTime" to its NodeId.
 */
final readonly class BrowsePath implements IEncodeable
{
    public function __construct(
        public NodeId $startingNode,
        public RelativePath $relativePath,
    ) {
    }

    public function encode(BinaryEncoder $encoder): void
    {
        $this->startingNode->encode($encoder);
        $this->relativePath->encode($encoder);
    }

    public static function decode(BinaryDecoder $decoder): static
    {
        $startingNode = NodeId::decode($decoder);
        $relativePath = RelativePath::decode($decoder);

        return new self(
            startingNode: $startingNode,
            relativePath: $relativePath,
        );
    }

    /**
     * Create from a starting node and string path elements
     *
     * @param NodeId $startingNode Starting point
     * @param string[] $pathElements Simple string names (uses hierarchical references)
     */
    public static function fromStrings(NodeId $startingNode, array $pathElements): self
    {
        $relativePathElements = [];
        foreach ($pathElements as $element) {
            $relativePathElements[] = RelativePathElement::hierarchical($element);
        }

        return new self(
            startingNode: $startingNode,
            relativePath: new RelativePath($relativePathElements),
        );
    }
}
