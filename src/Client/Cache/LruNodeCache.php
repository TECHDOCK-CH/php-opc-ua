<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client\Cache;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Types\NodeId;

/**
 * LruNodeCache - LRU (Least Recently Used) cache implementation
 *
 * Features:
 * - LRU eviction when size limit reached
 * - TTL-based expiration
 * - Cache statistics tracking
 * - Thread-safe for single-threaded PHP
 */
final class LruNodeCache implements INodeCache
{
    /** @var array<string, NodeCacheEntry> */
    private array $cache = [];

    /** @var array<string, float> */
    private array $accessTimes = [];

    private int $hits = 0;
    private int $misses = 0;

    /**
     * @param int $maxSize Maximum number of entries (0 = unlimited)
     */
    public function __construct(
        private readonly int $maxSize = 1000,
    ) {
        if ($maxSize < 0) {
            throw new InvalidArgumentException('Max size must be non-negative');
        }
    }

    public function get(NodeId $nodeId): ?NodeCacheEntry
    {
        $key = $this->getKey($nodeId);

        if (!isset($this->cache[$key])) {
            $this->misses++;
            return null;
        }

        $entry = $this->cache[$key];

        // Check if expired
        if ($entry->isExpired()) {
            $this->remove($nodeId);
            $this->misses++;
            return null;
        }

        // Update access time for LRU
        $this->accessTimes[$key] = microtime(true);
        $this->hits++;

        return $entry;
    }

    public function set(NodeId $nodeId, NodeCacheEntry $entry): void
    {
        // Enforce size limit with LRU eviction
        if ($this->maxSize > 0 && count($this->cache) >= $this->maxSize) {
            $this->evictLru();
        }

        $key = $this->getKey($nodeId);
        $this->cache[$key] = $entry;
        $this->accessTimes[$key] = microtime(true);
    }

    public function has(NodeId $nodeId): bool
    {
        return $this->get($nodeId) !== null;
    }

    public function remove(NodeId $nodeId): void
    {
        $key = $this->getKey($nodeId);
        unset($this->cache[$key], $this->accessTimes[$key]);
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->accessTimes = [];
        $this->hits = 0;
        $this->misses = 0;
    }

    public function getStats(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'size' => count($this->cache),
            'maxSize' => $this->maxSize,
            'hitRate' => $this->getHitRate(),
        ];
    }

    public function evictExpired(): int
    {
        $evicted = 0;
        $now = microtime(true);

        foreach ($this->cache as $key => $entry) {
            if ($entry->isExpired()) {
                unset($this->cache[$key], $this->accessTimes[$key]);
                $evicted++;
            }
        }

        return $evicted;
    }

    /**
     * Get cache hit rate (0.0 to 1.0)
     */
    public function getHitRate(): float
    {
        $total = $this->hits + $this->misses;
        if ($total === 0) {
            return 0.0;
        }
        return $this->hits / $total;
    }

    /**
     * Get cache miss rate (0.0 to 1.0)
     */
    public function getMissRate(): float
    {
        return 1.0 - $this->getHitRate();
    }

    /**
     * Get average entry age in seconds
     */
    public function getAverageAge(): float
    {
        if ($this->cache === []) {
            return 0.0;
        }

        $totalAge = 0.0;
        foreach ($this->cache as $entry) {
            $totalAge += $entry->getAge();
        }

        return $totalAge / count($this->cache);
    }

    /**
     * Evict the least recently used entry
     */
    private function evictLru(): void
    {
        if ($this->accessTimes === []) {
            return;
        }

        // Find the entry with oldest access time
        $lruKey = array_key_first($this->accessTimes);
        $lruTime = $this->accessTimes[$lruKey];

        foreach ($this->accessTimes as $key => $time) {
            if ($time < $lruTime) {
                $lruKey = $key;
                $lruTime = $time;
            }
        }

        unset($this->cache[$lruKey], $this->accessTimes[$lruKey]);
    }

    /**
     * Get cache key for a NodeId
     */
    private function getKey(NodeId $nodeId): string
    {
        // Use NodeId string representation as key
        return (string)$nodeId;
    }

    /**
     * Prune cache to remove old entries
     *
     * @param int $targetSize Target size after pruning
     * @return int Number of entries removed
     */
    public function prune(int $targetSize): int
    {
        if ($targetSize < 0) {
            throw new InvalidArgumentException('Target size must be non-negative');
        }

        $removed = 0;

        // First remove expired entries
        $removed += $this->evictExpired();

        // Then remove LRU entries if still over target
        while (count($this->cache) > $targetSize) {
            $this->evictLru();
            $removed++;
        }

        return $removed;
    }

    /**
     * Get count of cached entries
     */
    public function getSize(): int
    {
        return count($this->cache);
    }
}
