<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

use InvalidArgumentException;
use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;

/**
 * TCP/Unix socket connection to OPC UA server
 *
 * Supports both TCP network connections and Unix domain sockets.
 * For Unix sockets, set port to 0 and host to the socket path.
 *
 * @example TCP: new TcpConnection('localhost', 4840, 'opc.tcp://localhost:4840')
 * @example Unix: new TcpConnection('/var/run/opcua.sock', 0, 'opc.tcp://unix-socket')
 */
final class TcpConnection implements TcpConnectionInterface
{
    /** @var resource|null */
    private $socket = null;

    private bool $connected = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $endpointUrl,
        private readonly int $timeout = 10,
    ) {
        // Allow Unix sockets (host will be socket path, port will be 0)
        if (!$this->isUnixSocket() && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException("Invalid port: {$port}");
        }
    }

    /**
     * Check if this is a Unix socket connection
     */
    private function isUnixSocket(): bool
    {
        return $this->port === 0 || str_starts_with($this->host, '/') || str_starts_with($this->host, 'unix://');
    }

    /**
     * Connect to the server
     */
    public function connect(): void
    {
        if ($this->connected) {
            throw new RuntimeException('Already connected');
        }

        $errno = 0;
        $errstr = '';

        // Handle Unix socket vs TCP connections
        if ($this->isUnixSocket()) {
            $socketPath = str_starts_with($this->host, 'unix://')
                ? $this->host
                : 'unix://' . $this->host;

            $socket = @stream_socket_client($socketPath, $errno, $errstr, $this->timeout);

            if ($socket === false) {
                throw new RuntimeException("Failed to connect to Unix socket {$socketPath}: {$errstr} ({$errno})");
            }
        } else {
            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

            if ($socket === false) {
                throw new RuntimeException("Failed to connect to {$this->host}:{$this->port}: {$errstr} ({$errno})");
            }
        }

        stream_set_blocking($socket, true);
        stream_set_timeout($socket, $this->timeout);

        $this->socket = $socket;
        $this->connected = true;
    }

    /**
     * Send data
     */
    public function send(string $data): void
    {
        if (!$this->connected || $this->socket === null) {
            throw new RuntimeException('Not connected');
        }

        $written = @fwrite($this->socket, $data);

        if ($written === false || $written !== strlen($data)) {
            throw new RuntimeException('Failed to send data');
        }
    }

    /**
     * Receive exactly N bytes
     */
    public function receive(int $length): string
    {
        if (!$this->connected || $this->socket === null) {
            throw new RuntimeException('Not connected');
        }

        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = @fread($this->socket, $remaining);

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Connection closed or read failed');
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    /**
     * Receive a message header (8 bytes)
     */
    public function receiveHeader(): MessageHeader
    {
        $headerBytes = $this->receive(MessageHeader::HEADER_SIZE);
        $decoder = new BinaryDecoder($headerBytes);
        return MessageHeader::decode($decoder);
    }

    /**
     * Receive a complete message (header + payload)
     */
    public function receiveMessage(): string
    {
        $header = $this->receiveHeader();
        $payloadSize = $header->getPayloadSize();

        if ($payloadSize === 0) {
            return '';
        }

        return $this->receive($payloadSize);
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Close the connection
     */
    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }

        $this->connected = false;
    }

    /**
     * Get the endpoint URL
     */
    public function getEndpointUrl(): string
    {
        return $this->endpointUrl;
    }

    public function __destruct()
    {
        $this->close();
    }
}
