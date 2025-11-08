<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use TechDock\OpcUa\Client\Cache\INodeCache;
use Throwable;

/**
 * ConnectedClient - Wrapper for a fully connected and configured OPC UA client
 *
 * This class is returned by ClientBuilder::build() and provides convenient
 * access to all client components with proper lifecycle management.
 *
 * Example:
 * ```php
 * $connected = ClientBuilder::create()
 *     ->endpoint('opc.tcp://localhost:4840')
 *     ->withCache()
 *     ->build();
 *
 * // Use the session
 * $value = $connected->session->read(NodeId::numeric(0, 2258));
 *
 * // Use the browser
 * $refs = $connected->browser->browse(NodeId::numeric(0, 85));
 *
 * // Clean up
 * $connected->disconnect();
 * ```
 */
final readonly class ConnectedClient
{
    public function __construct(
        public OpcUaClient $client,
        public Session $session,
        public Browser $browser,
        public ?INodeCache $cache = null,
    ) {
    }

    /**
     * Close the session and disconnect
     */
    public function disconnect(): void
    {
        try {
            $this->session->close();
        } catch (Throwable) {
            // Ignore errors during cleanup
        }

        try {
            $this->client->disconnect();
        } catch (Throwable) {
            // Ignore errors during cleanup
        }
    }

    /**
     * Check if the client is connected
     */
    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }

    /**
     * Get server capabilities (detects if not already done)
     */
    public function getServerCapabilities(): ServerCapabilities
    {
        return $this->session->getServerCapabilities();
    }

    /**
     * Get cache statistics if caching is enabled
     *
     * @return array{hits: int, misses: int, size: int, maxSize: int, hitRate: float}|null
     */
    public function getCacheStats(): ?array
    {
        return $this->cache?->getStats();
    }

    /**
     * Clear the cache if caching is enabled
     */
    public function clearCache(): void
    {
        $this->cache?->clear();
    }
}
