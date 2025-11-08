<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client\Cache;

use InvalidArgumentException;
use TechDock\OpcUa\Core\Messages\ReferenceDescription;
use TechDock\OpcUa\Core\Types\DataValue;
use TechDock\OpcUa\Core\Types\QualifiedName;

/**
 * NodeCacheEntry - Cached metadata for a single node
 *
 * Stores commonly accessed node information to reduce network roundtrips.
 */
final readonly class NodeCacheEntry
{
    /**
     * @param QualifiedName $browseName Node's browse name
     * @param int|null $nodeClass Node class value (1=Object, 2=Variable, 4=Method, etc.)
     * @param string|null $displayName Human-readable display name
     * @param ReferenceDescription[] $references Cached browse references
     * @param DataValue|null $value Cached value (for variables)
     * @param float $cachedAt Unix timestamp when cached
     * @param float $ttl Time-to-live in seconds
     */
    public function __construct(
        public QualifiedName $browseName,
        public ?int $nodeClass = null,
        public ?string $displayName = null,
        public array $references = [],
        public ?DataValue $value = null,
        public float $cachedAt = 0.0,
        public float $ttl = 300.0,  // 5 minutes default
    ) {
        foreach ($references as $ref) {
            if (!$ref instanceof ReferenceDescription) {
                throw new InvalidArgumentException('References must be ReferenceDescription instances');
            }
        }
    }

    /**
     * Check if this cache entry has expired
     */
    public function isExpired(): bool
    {
        return (microtime(true) - $this->cachedAt) > $this->ttl;
    }

    /**
     * Get age of this cache entry in seconds
     */
    public function getAge(): float
    {
        return microtime(true) - $this->cachedAt;
    }

    /**
     * Create a cache entry with updated timestamp
     */
    public function withRefreshedTimestamp(): self
    {
        return new self(
            browseName: $this->browseName,
            nodeClass: $this->nodeClass,
            displayName: $this->displayName,
            references: $this->references,
            value: $this->value,
            cachedAt: microtime(true),
            ttl: $this->ttl,
        );
    }

    /**
     * Create a cache entry with updated value
     */
    public function withValue(DataValue $value): self
    {
        return new self(
            browseName: $this->browseName,
            nodeClass: $this->nodeClass,
            displayName: $this->displayName,
            references: $this->references,
            value: $value,
            cachedAt: microtime(true),
            ttl: $this->ttl,
        );
    }

    /**
     * Create a cache entry with updated references
     *
     * @param ReferenceDescription[] $references
     */
    public function withReferences(array $references): self
    {
        return new self(
            browseName: $this->browseName,
            nodeClass: $this->nodeClass,
            displayName: $this->displayName,
            references: $references,
            value: $this->value,
            cachedAt: microtime(true),
            ttl: $this->ttl,
        );
    }

    /**
     * Create a minimal cache entry from browse name
     */
    public static function minimal(QualifiedName $browseName, float $ttl = 300.0): self
    {
        return new self(
            browseName: $browseName,
            cachedAt: microtime(true),
            ttl: $ttl,
        );
    }

    /**
     * Create a cache entry from ReferenceDescription
     */
    public static function fromReferenceDescription(
        ReferenceDescription $ref,
        float $ttl = 300.0,
    ): self {
        return new self(
            browseName: $ref->browseName,
            nodeClass: $ref->nodeClass,
            displayName: $ref->displayName->text,
            cachedAt: microtime(true),
            ttl: $ttl,
        );
    }
}
