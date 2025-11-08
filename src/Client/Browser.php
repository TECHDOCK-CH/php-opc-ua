<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Client\Cache\INodeCache;
use TechDock\OpcUa\Client\Cache\NodeCacheEntry;
use TechDock\OpcUa\Core\Messages\BrowseDescription;
use TechDock\OpcUa\Core\Messages\BrowseDirection;
use TechDock\OpcUa\Core\Messages\ReferenceDescription;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\QualifiedName;
use Throwable;

/**
 * High-level browser helper for OPC UA address space browsing
 *
 * Wraps the low-level Browse service with convenient filtering options
 * and automatic continuation point handling.
 */
final class Browser
{
    /**
     * Create a new Browser instance
     *
     * @param Session $session Active OPC UA session
     * @param BrowseDirection $browseDirection Direction of references to follow
     * @param NodeId|null $referenceTypeId Filter by reference type (null = all types)
     * @param bool $includeSubtypes Include subtypes of reference type
     * @param int $nodeClassMask Bit mask of NodeClass values (0 = all classes)
     * @param int $resultMask Bit mask of BrowseResultMask values (63 = all fields)
     * @param int $maxReferencesPerNode Maximum references per node (0 = server default)
     * @param INodeCache|null $cache Optional node cache for performance optimization
     */
    public function __construct(
        private readonly Session $session,
        private readonly BrowseDirection $browseDirection = BrowseDirection::Forward,
        private readonly ?NodeId $referenceTypeId = null,
        private readonly bool $includeSubtypes = true,
        private readonly int $nodeClassMask = 0,
        private readonly int $resultMask = 63,
        private readonly int $maxReferencesPerNode = 1000,
        private readonly ?INodeCache $cache = null,
    ) {
    }

    /**
     * Browse a single node and return all references
     *
     * Automatically handles continuation points to retrieve all results.
     * Uses cache if available to reduce network roundtrips.
     *
     * @param NodeId $nodeId Node to browse
     * @return ReferenceDescription[] Array of reference descriptions
     */
    public function browse(NodeId $nodeId): array
    {
        // Check cache first
        if ($this->cache !== null) {
            $cached = $this->cache->get($nodeId);
            if ($cached !== null && $cached->references !== []) {
                return $cached->references;
            }
        }

        // Cache miss - fetch from server
        $browseDescription = $this->createBrowseDescription($nodeId);

        $result = $this->session->managedBrowse(
            $browseDescription,
            $this->maxReferencesPerNode
        );

        if (!$result->statusCode->isGood()) {
            throw new RuntimeException(
                "Browse failed for node {$nodeId}: {$result->statusCode}"
            );
        }

        // Store in cache
        if ($this->cache !== null && $result->references !== []) {
            $this->cacheNodeReferences($nodeId, $result->references);
        }

        return $result->references;
    }

    /**
     * Browse multiple nodes in a single operation
     *
     * @param NodeId[] $nodeIds Nodes to browse
     * @return array<string, ReferenceDescription[]> Map of node ID string to references
     */
    public function browseMultiple(array $nodeIds): array
    {
        $results = [];

        foreach ($nodeIds as $nodeId) {
            $key = (string)$nodeId;
            try {
                $results[$key] = $this->browse($nodeId);
            } catch (Throwable $e) {
                // Store exception info instead of failing entire operation
                $results[$key] = [];
            }
        }

        return $results;
    }

    /**
     * Browse recursively to a specified depth
     *
     * @param NodeId $startNode Starting node
     * @param int $maxDepth Maximum depth to browse (1 = children only)
     * @param bool $includeStartNode Include start node in results
     * @return array<string, ReferenceDescription> Map of node ID to reference description
     */
    public function browseRecursive(
        NodeId $startNode,
        int $maxDepth = 10,
        bool $includeStartNode = false
    ): array {
        if ($maxDepth < 1) {
            throw new InvalidArgumentException('maxDepth must be at least 1');
        }

        $allReferences = [];
        $nodesToBrowse = [$startNode];
        $browsedNodes = [];
        $currentDepth = 0;

        while ($nodesToBrowse !== [] && $currentDepth < $maxDepth) {
            $nextLevelNodes = [];

            foreach ($nodesToBrowse as $nodeId) {
                $nodeIdStr = (string)$nodeId;

                // Skip if already browsed (circular reference protection)
                if (isset($browsedNodes[$nodeIdStr])) {
                    continue;
                }

                $browsedNodes[$nodeIdStr] = true;

                try {
                    $references = $this->browse($nodeId);

                    foreach ($references as $reference) {
                        $refIdStr = (string)$reference->nodeId;

                        // Add to results if not already present
                        if (!isset($allReferences[$refIdStr])) {
                            $allReferences[$refIdStr] = $reference;

                            // Add to next level if not a leaf node type
                            if ($reference->nodeClass !== null) {
                                // Don't browse Variable nodes (typically leaf nodes)
                                if ($reference->nodeClass !== 2) { // 2 = Variable
                                    $nextLevelNodes[] = $reference->nodeId->nodeId;
                                }
                            }
                        }
                    }
                } catch (Throwable) {
                    // Skip nodes that fail to browse
                    continue;
                }
            }

            $nodesToBrowse = $nextLevelNodes;
            $currentDepth++;
        }

        return $allReferences;
    }

    /**
     * Find nodes by browse name pattern
     *
     * @param NodeId $startNode Starting node
     * @param string $pattern Browse name pattern (supports wildcards * and ?)
     * @param int $maxDepth Maximum depth to search
     * @return ReferenceDescription[] Matching nodes
     */
    public function findByBrowseName(
        NodeId $startNode,
        string $pattern,
        int $maxDepth = 5
    ): array {
        $allReferences = $this->browseRecursive($startNode, $maxDepth);
        $matches = [];

        // Convert pattern to regex
        $regexPattern = '/^' . str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        ) . '$/i';

        foreach ($allReferences as $reference) {
            if ($reference->browseName !== null) {
                $browseName = $reference->browseName->name;
                if (preg_match($regexPattern, $browseName) === 1) {
                    $matches[] = $reference;
                }
            }
        }

        return $matches;
    }

    /**
     * Get only Objects from browse results
     *
     * @param NodeId $nodeId Node to browse
     * @return ReferenceDescription[] Objects only
     */
    public function browseObjects(NodeId $nodeId): array
    {
        return $this->browseByNodeClass($nodeId, 1); // 1 = Object
    }

    /**
     * Get only Variables from browse results
     *
     * @param NodeId $nodeId Node to browse
     * @return ReferenceDescription[] Variables only
     */
    public function browseVariables(NodeId $nodeId): array
    {
        return $this->browseByNodeClass($nodeId, 2); // 2 = Variable
    }

    /**
     * Get only Methods from browse results
     *
     * @param NodeId $nodeId Node to browse
     * @return ReferenceDescription[] Methods only
     */
    public function browseMethods(NodeId $nodeId): array
    {
        return $this->browseByNodeClass($nodeId, 4); // 4 = Method
    }

    /**
     * Browse with custom node class filter
     *
     * @param NodeId $nodeId Node to browse
     * @param int $nodeClassValue NodeClass value (1=Object, 2=Variable, 4=Method, etc.)
     * @return ReferenceDescription[] Filtered references
     */
    private function browseByNodeClass(NodeId $nodeId, int $nodeClassValue): array
    {
        $references = $this->browse($nodeId);
        $filtered = [];

        foreach ($references as $reference) {
            if ($reference->nodeClass === $nodeClassValue) {
                $filtered[] = $reference;
            }
        }

        return $filtered;
    }

    /**
     * Create a BrowseDescription from Browser settings
     */
    private function createBrowseDescription(NodeId $nodeId): BrowseDescription
    {
        return BrowseDescription::create(
            nodeId: $nodeId,
            browseDirection: $this->browseDirection,
            referenceTypeId: $this->referenceTypeId,
            includeSubtypes: $this->includeSubtypes,
            nodeClassMask: $this->nodeClassMask,
            resultMask: $this->resultMask,
        );
    }

    /**
     * Create a Browser with custom options
     */
    public static function withOptions(Session $session, BrowserOptions $options, ?INodeCache $cache = null): self
    {
        return new self(
            session: $session,
            browseDirection: $options->browseDirection,
            referenceTypeId: $options->referenceTypeId,
            includeSubtypes: $options->includeSubtypes,
            nodeClassMask: $options->nodeClassMask,
            resultMask: $options->resultMask,
            maxReferencesPerNode: $options->maxReferencesPerNode,
            cache: $cache,
        );
    }

    /**
     * Get the cache instance (if any)
     */
    public function getCache(): ?INodeCache
    {
        return $this->cache;
    }

    /**
     * Cache node references
     *
     * @param NodeId $nodeId
     * @param ReferenceDescription[] $references
     */
    private function cacheNodeReferences(NodeId $nodeId, array $references): void
    {
        if ($this->cache === null) {
            return;
        }

        // Try to get existing entry or create minimal one
        $entry = $this->cache->get($nodeId);
        if ($entry === null) {
            $entry = NodeCacheEntry::minimal($references[0]->browseName ?? new QualifiedName(0, ''));
        }

        // Update with references
        $updatedEntry = $entry->withReferences($references);
        $this->cache->set($nodeId, $updatedEntry);

        // Also cache each reference description
        foreach ($references as $ref) {
            $refEntry = NodeCacheEntry::fromReferenceDescription($ref);
            $this->cache->set($ref->nodeId->nodeId, $refEntry);
        }
    }
}
