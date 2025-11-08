<?php

declare(strict_types=1);

/**
 * Simple Unix Socket Test Server
 *
 * Creates a basic socket server for testing Unix socket connections.
 * This is NOT a full OPC UA server - it's just for testing connectivity.
 *
 * Usage:
 *   php examples/unix_socket_test_server.php
 *
 * Then in another terminal:
 *   php examples/unix_socket_connection.php
 */

$socketPath = '/tmp/opcua-test.sock';

echo "=== Unix Socket Test Server ===\n\n";

// Remove old socket if exists
if (file_exists($socketPath)) {
    echo "Removing old socket file...\n";
    unlink($socketPath);
}

// Create socket
$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
if ($socket === false) {
    die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
}

// Bind to socket path
if (!socket_bind($socket, $socketPath)) {
    die("Failed to bind socket: " . socket_strerror(socket_last_error()) . "\n");
}

// Set permissions so other users can connect
chmod($socketPath, 0666);

// Listen for connections
if (!socket_listen($socket, 5)) {
    die("Failed to listen on socket: " . socket_strerror(socket_last_error()) . "\n");
}

echo "Server listening on: {$socketPath}\n";
echo "Socket permissions: " . substr(sprintf('%o', fileperms($socketPath)), -4) . "\n";
echo "Press Ctrl+C to stop\n\n";

// Accept connections
$clientCount = 0;
while (true) {
    echo "Waiting for connections...\n";

    $client = socket_accept($socket);
    if ($client === false) {
        echo "Failed to accept connection: " . socket_strerror(socket_last_error()) . "\n";
        continue;
    }

    $clientCount++;
    echo "[{$clientCount}] Client connected at " . date('H:i:s') . "\n";

    // Read some data from client
    $data = socket_read($client, 1024);
    if ($data !== false && $data !== '') {
        echo "[{$clientCount}] Received " . strlen($data) . " bytes: " . bin2hex(substr($data, 0, 20)) . "...\n";

        // Send a simple response (not valid OPC UA, just for testing)
        $response = "Hello from test server!";
        socket_write($client, $response, strlen($response));
        echo "[{$clientCount}] Sent response: {$response}\n";
    }

    // Close client connection
    socket_close($client);
    echo "[{$clientCount}] Client disconnected\n\n";
}

// Cleanup (this won't be reached unless you modify the loop)
socket_close($socket);
unlink($socketPath);
