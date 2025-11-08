<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use Throwable;

/**
 * SessionReconnectHandler - handles automatic session reconnection.
 *
 * Implements exponential backoff strategy for reconnecting after connection loss.
 */
final class SessionReconnectHandler
{
    private bool $reconnecting = false;
    private int $reconnectAttempts = 0;
    private float $lastReconnectTime = 0.0;

    // Reconnection strategy
    private float $minReconnectDelay = 1.0;      // 1 second
    private float $maxReconnectDelay = 60.0;     // 60 seconds
    private int $maxReconnectAttempts = 10;
    private float $backoffMultiplier = 2.0;

    /** @var callable|null Callback: function(SessionReconnectHandler, int): void */
    private $onReconnecting = null;

    /** @var callable|null Callback: function(SessionReconnectHandler): void */
    private $onReconnected = null;

    /** @var callable|null Callback: function(SessionReconnectHandler, \Throwable): void */
    private $onReconnectFailed = null;

    public function __construct(
        private readonly OpcUaClient $client,
        private readonly Session $session,
    ) {
    }

    /**
     * Configure reconnection strategy.
     */
    public function configure(
        float $minDelay = 1.0,
        float $maxDelay = 60.0,
        int $maxAttempts = 10,
        float $backoffMultiplier = 2.0,
    ): void {
        $this->minReconnectDelay = $minDelay;
        $this->maxReconnectDelay = $maxDelay;
        $this->maxReconnectAttempts = $maxAttempts;
        $this->backoffMultiplier = $backoffMultiplier;
    }

    /**
     * Set callback invoked when reconnection starts.
     *
     * @param callable $callback function(SessionReconnectHandler, int $attempt): void
     */
    public function setReconnectingCallback(callable $callback): void
    {
        $this->onReconnecting = $callback;
    }

    /**
     * Set callback invoked when reconnection succeeds.
     *
     * @param callable $callback function(SessionReconnectHandler): void
     */
    public function setReconnectedCallback(callable $callback): void
    {
        $this->onReconnected = $callback;
    }

    /**
     * Set callback invoked when reconnection fails.
     *
     * @param callable $callback function(SessionReconnectHandler, \Throwable): void
     */
    public function setReconnectFailedCallback(callable $callback): void
    {
        $this->onReconnectFailed = $callback;
    }

    /**
     * Attempt to reconnect the session.
     *
     * @return bool True if reconnection succeeded, false otherwise
     */
    public function reconnect(): bool
    {
        if ($this->reconnecting) {
            return false; // Already reconnecting
        }

        $this->reconnecting = true;
        $this->reconnectAttempts = 0;

        while ($this->reconnectAttempts < $this->maxReconnectAttempts) {
            $this->reconnectAttempts++;

            // Invoke reconnecting callback
            if ($this->onReconnecting !== null) {
                ($this->onReconnecting)($this, $this->reconnectAttempts);
            }

            try {
                // Calculate delay with exponential backoff
                $delay = $this->calculateBackoffDelay($this->reconnectAttempts);

                // Wait before attempting reconnection
                if ($this->reconnectAttempts > 1) {
                    usleep((int)($delay * 1_000_000));
                }

                // Attempt reconnection
                $this->performReconnect();

                // Success!
                $this->reconnecting = false;
                $this->reconnectAttempts = 0;

                if ($this->onReconnected !== null) {
                    ($this->onReconnected)($this);
                }

                return true;
            } catch (Throwable $e) {
                // Log error and continue trying
                $this->lastReconnectTime = microtime(true);

                // If max attempts reached, give up
                if ($this->reconnectAttempts >= $this->maxReconnectAttempts) {
                    $this->reconnecting = false;

                    if ($this->onReconnectFailed !== null) {
                        ($this->onReconnectFailed)($this, $e);
                    }

                    return false;
                }
            }
        }

        $this->reconnecting = false;
        return false;
    }

    /**
     * Perform the actual reconnection steps.
     */
    private function performReconnect(): void
    {
        // Step 1: Disconnect if still connected
        try {
            $this->client->disconnect();
        } catch (Throwable $e) {
            // Ignore disconnect errors
        }

        // Step 2: Reconnect to server
        $this->client->connect();

        // Step 3: Recreate session
        $this->session->create();

        // Step 4: Reactivate session
        $this->session->activate();

        // Step 5: Recreate subscriptions
        // Note: In a full implementation, we would store subscription state
        // and recreate all subscriptions and monitored items here
        // For now, subscriptions need to be manually recreated by the application
    }

    /**
     * Calculate exponential backoff delay.
     */
    private function calculateBackoffDelay(int $attempt): float
    {
        // Base delay with exponential increase
        $delay = $this->minReconnectDelay * pow($this->backoffMultiplier, $attempt - 1);

        // Add jitter (random 0-20% variation)
        $jitter = $delay * (mt_rand(0, 20) / 100.0);
        $delay += $jitter;

        // Cap at max delay
        return min($delay, $this->maxReconnectDelay);
    }

    /**
     * Check if currently reconnecting.
     */
    public function isReconnecting(): bool
    {
        return $this->reconnecting;
    }

    /**
     * Get the number of reconnection attempts made.
     */
    public function getReconnectAttempts(): int
    {
        return $this->reconnectAttempts;
    }

    /**
     * Get time since last reconnect attempt.
     */
    public function getTimeSinceLastReconnect(): float
    {
        if ($this->lastReconnectTime === 0.0) {
            return 0.0;
        }
        return microtime(true) - $this->lastReconnectTime;
    }

    /**
     * Reset reconnection state.
     */
    public function reset(): void
    {
        $this->reconnecting = false;
        $this->reconnectAttempts = 0;
        $this->lastReconnectTime = 0.0;
    }
}
