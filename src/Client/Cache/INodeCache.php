<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client\Cache;

use TechDock\OpcUa\Core\Types\NodeId;

/**
 * INodeCache - Interface for caching node metadata
 *
 * Reduces network roundtrips by caching frequently accessed node information.
 */
interface INodeCache
{
    /**
     * Get a cached node entry
     *
     * @param NodeId $nodeId Node identifier
     * @return NodeCacheEntry|null Cache entry or null if not found/expired
     */
    public function get(NodeId $nodeId): ?NodeCacheEntry;

    /**
     * Store a node entry in the cache
     *
     * @param NodeId $nodeId Node identifier
     * @param NodeCacheEntry $entry Cache entry
     */
    public function set(NodeId $nodeId, NodeCacheEntry $entry): void;

    /**
     * Check if a node is in the cache
     *
     * @param NodeId $nodeId Node identifier
     * @return bool True if cached and not expired
     */
    public function has(NodeId $nodeId): bool;

    /**
     * Remove a node from the cache
     *
     * @param NodeId $nodeId Node identifier
     */
    public function remove(NodeId $nodeId): void;

    /**
     * Clear all cached entries
     */
    public function clear(): void;

    /**
     * Get cache statistics
     *
     * @return array{hits: int, misses: int, size: int, maxSize: int, hitRate: float}
     */
    public function getStats(): array;

    /**
     * Evict expired entries
     *
     * @return int Number of entries evicted
     */
    public function evictExpired(): int;
}
