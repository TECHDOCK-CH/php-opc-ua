<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Core\Transport;

use RuntimeException;
use Throwable;

/**
 * TCP connection debugging wrapper that logs all traffic
 */
final class TcpConnectionDebugWrapper implements TcpConnectionInterface
{
    private TcpConnectionInterface $connection;
    private string $logFile;
    private int $sequenceNumber = 0;
    /** @var resource|null */
    private $logHandle = null;
    private float $startTime;

    public function __construct(TcpConnectionInterface $connection, ?string $logDir = null)
    {
        $this->connection = $connection;
        $this->startTime = microtime(true);

        if ($logDir === null) {
            $logDir = __DIR__ . '/../../../temp';
        }

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d_His');
        $this->logFile = $logDir . "/tcp_debug_{$timestamp}.log";

        $handle = fopen($this->logFile, 'w');
        if ($handle === false) {
            throw new RuntimeException("Failed to open log file: {$this->logFile}");
        }
        $this->logHandle = $handle;

        $this->log("=== TCP DEBUG SESSION STARTED ===");
        $this->log("Endpoint: " . $connection->getEndpointUrl());
        $this->log("Timestamp: " . date('Y-m-d H:i:s'));
        $this->log("");
    }

    public function connect(): void
    {
        $this->log(">>> CONNECT");
        try {
            $this->connection->connect();
            $this->log("<<< CONNECT SUCCESS");
        } catch (Throwable $e) {
            $this->log("<<< CONNECT FAILED: " . $e->getMessage());
            throw $e;
        }
    }

    public function send(string $data): void
    {
        $this->sequenceNumber++;
        $elapsed = microtime(true) - $this->startTime;

        $this->log(sprintf(
            ">>> SEND [#%d] %.4fs %d bytes",
            $this->sequenceNumber,
            $elapsed,
            strlen($data)
        ));

        // Log hex dump
        $this->logHexDump($data, "SEND");

        // Save raw data to separate file
        $this->saveRawData($data, 'send', $this->sequenceNumber);

        try {
            $this->connection->send($data);
            $this->log("<<< SEND COMPLETE");
        } catch (Throwable $e) {
            $this->log("<<< SEND FAILED: " . $e->getMessage());
            throw $e;
        }

        $this->log("");
    }

    public function receive(int $length): string
    {
        $this->sequenceNumber++;
        $elapsed = microtime(true) - $this->startTime;

        $this->log(sprintf(
            ">>> RECEIVE [#%d] %.4fs (expecting %d bytes)",
            $this->sequenceNumber,
            $elapsed,
            $length
        ));

        try {
            $data = $this->connection->receive($length);

            $this->log(sprintf("<<< RECEIVED %d bytes", strlen($data)));

            // Log hex dump
            $this->logHexDump($data, "RECV");

            // Save raw data to separate file
            $this->saveRawData($data, 'recv', $this->sequenceNumber);

            $this->log("");

            return $data;
        } catch (Throwable $e) {
            $this->log("<<< RECEIVE FAILED: " . $e->getMessage());
            throw $e;
        }
    }

    public function receiveHeader(): MessageHeader
    {
        $this->sequenceNumber++;
        $elapsed = microtime(true) - $this->startTime;

        $this->log(sprintf(
            ">>> RECEIVE HEADER [#%d] %.4fs (expecting %d bytes)",
            $this->sequenceNumber,
            $elapsed,
            MessageHeader::HEADER_SIZE
        ));

        try {
            $header = $this->connection->receiveHeader();

            $this->log(sprintf(
                "<<< HEADER: Type=%s, Chunk=%s, Size=%d",
                $header->messageType->value,
                $header->chunkType,
                $header->messageSize
            ));

            $this->log("");

            return $header;
        } catch (Throwable $e) {
            $this->log("<<< RECEIVE HEADER FAILED: " . $e->getMessage());
            throw $e;
        }
    }

    public function receiveMessage(): string
    {
        $this->sequenceNumber++;
        $elapsed = microtime(true) - $this->startTime;

        $this->log(sprintf(
            ">>> RECEIVE MESSAGE [#%d] %.4fs",
            $this->sequenceNumber,
            $elapsed
        ));

        try {
            $data = $this->connection->receiveMessage();

            $this->log(sprintf("<<< RECEIVED MESSAGE %d bytes", strlen($data)));

            // Log hex dump
            $this->logHexDump($data, "MSG");

            // Save raw data to separate file
            $this->saveRawData($data, 'msg', $this->sequenceNumber);

            $this->log("");

            return $data;
        } catch (Throwable $e) {
            $this->log("<<< RECEIVE MESSAGE FAILED: " . $e->getMessage());
            throw $e;
        }
    }

    public function isConnected(): bool
    {
        return $this->connection->isConnected();
    }

    public function close(): void
    {
        $this->log(">>> CLOSE");
        $this->connection->close();
        $this->log("<<< CLOSE COMPLETE");
        $this->log("");
        $this->log("=== TCP DEBUG SESSION ENDED ===");

        if ($this->logHandle !== null) {
            fclose($this->logHandle);
            $this->logHandle = null;
        }
    }

    public function getEndpointUrl(): string
    {
        return $this->connection->getEndpointUrl();
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }

    private function log(string $message): void
    {
        if ($this->logHandle !== null) {
            fwrite($this->logHandle, $message . "\n");
            fflush($this->logHandle);
        }
    }

    private function logHexDump(string $data, string $prefix): void
    {
        $length = strlen($data);

        // Parse message header if possible
        if ($length >= 8) {
            $type = substr($data, 0, 3);
            $chunk = chr(ord($data[3]));
            $unpacked = unpack('V', substr($data, 4, 4));
            if ($unpacked !== false && isset($unpacked[1])) {
                $size = $unpacked[1];
                $this->log("  Message Type: {$type}, Chunk: {$chunk}, Size: {$size}");
            }
        }

        $this->log("  Hex dump:");

        for ($i = 0; $i < $length; $i += 16) {
            $hex = '';
            $ascii = '';

            for ($j = 0; $j < 16; $j++) {
                $offset = $i + $j;

                if ($offset < $length) {
                    $byte = ord($data[$offset]);
                    $hex .= sprintf('%02X ', $byte);
                    $ascii .= ($byte >= 32 && $byte <= 126) ? chr($byte) : '.';
                } else {
                    $hex .= '   ';
                    $ascii .= ' ';
                }
            }

            $this->log(sprintf("  %08X: %s | %s", $i, $hex, $ascii));
        }
    }

    private function saveRawData(string $data, string $direction, int $seq): void
    {
        $filename = sprintf(
            "%s_%04d_%s.bin",
            basename($this->logFile, '.log'),
            $seq,
            $direction
        );

        $filepath = dirname($this->logFile) . '/' . $filename;
        file_put_contents($filepath, $data);
    }

    public function __destruct()
    {
        if ($this->connection->isConnected()) {
            $this->close();
        } elseif ($this->logHandle !== null) {
            fclose($this->logHandle);
        }
    }
}
