<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

/**
 * Interface for TCP connections
 */
interface TcpConnectionInterface
{
    public function connect(): void;

    public function send(string $data): void;

    public function receive(int $length): string;

    public function receiveHeader(): MessageHeader;

    public function receiveMessage(): string;

    public function isConnected(): bool;

    public function close(): void;

    public function getEndpointUrl(): string;
}
