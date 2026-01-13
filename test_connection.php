<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use TechDock\OpcUa\Core\Transport\TcpConnection;
use TechDock\OpcUa\Core\Transport\HelloMessage;
use TechDock\OpcUa\Core\Transport\AcknowledgeMessage;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;

/**
 * Test basic connection to OPC UA server
 */

$endpointUrl = 'opc.tcp://127.0.0.1:4840';

echo "Testing connection to $endpointUrl\n\n";

try {
    // Parse URL
    $url = parse_url($endpointUrl);
    $host = $url['host'] ?? 'localhost';
    $port = $url['port'] ?? 4840;

    echo "Connecting to $host:$port...\n";

    // Open TCP connection
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);

    if (!$socket) {
        throw new RuntimeException("Failed to connect: $errstr ($errno)");
    }

    echo "✓ TCP connection established\n\n";

    // Send Hello message
    $hello = new HelloMessage(
        protocolVersion: 0,
        receiveBufferSize: 65536,
        sendBufferSize: 65536,
        maxMessageSize: 0,  // unlimited
        maxChunkCount: 0,   // unlimited
        endpointUrl: $endpointUrl
    );

    $helloData = $hello->encode();
    echo "Sending Hello message (" . strlen($helloData) . " bytes)...\n";
    echo "Endpoint URL: $endpointUrl\n";

    fwrite($socket, $helloData);

    // Read ACK response
    echo "\nWaiting for Acknowledge message...\n";

    $response = fread($socket, 8192);

    if (!$response) {
        throw new RuntimeException("No response from server");
    }

    echo "✓ Received response (" . strlen($response) . " bytes)\n";
    echo "Raw hex dump (first 100 bytes):\n";
    echo chunk_split(bin2hex(substr($response, 0, 100)), 2, ' ') . "\n\n";

    // Try to decode ACK
    $decoder = new BinaryDecoder($response);
    try {
        $ack = AcknowledgeMessage::decode($decoder);
        echo "✓ Successfully decoded Acknowledge message:\n";
        echo "  Protocol Version: {$ack->protocolVersion}\n";
        echo "  Receive Buffer: {$ack->receiveBufferSize} bytes\n";
        echo "  Send Buffer: {$ack->sendBufferSize} bytes\n";
        echo "  Max Message Size: " . ($ack->maxMessageSize === 0 ? 'unlimited' : "{$ack->maxMessageSize} bytes") . "\n";
        echo "  Max Chunk Count: " . ($ack->maxChunkCount === 0 ? 'unlimited' : $ack->maxChunkCount) . "\n";
    } catch (Throwable $e) {
        echo "✗ Failed to decode Acknowledge: {$e->getMessage()}\n";
    }

    fclose($socket);
    echo "\n✓ Test completed\n";

} catch (Throwable $e) {
    echo "\n✗ Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
