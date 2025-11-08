<?php

/**
 * Unix Socket Connection Example
 *
 * Demonstrates connecting to an OPC UA server via Unix domain socket
 * instead of TCP. This is useful for:
 * - Local inter-process communication (IPC)
 * - Container deployments (Docker/Kubernetes with volume mounts)
 * - Enhanced security (no network exposure)
 * - Lower latency for local connections
 *
 * Prerequisites:
 * - OPC UA server listening on a Unix socket
 * - Socket file must be accessible (proper filesystem permissions)
 *
 * Common socket locations:
 * - /var/run/opcua.sock
 * - /tmp/opcua.sock
 * - /run/opcua/server.sock
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Core\Transport\TcpConnection;

// Configuration
$socketPath = '/var/run/opcua.sock'; // Change to your socket path
$endpointUrl = 'opc.tcp://unix-socket'; // Logical endpoint URL

echo "=== OPC UA Unix Socket Connection Example ===\n\n";

// =============================================================================
// Part 1: Basic Unix Socket Connection
// =============================================================================

echo "Part 1: Basic Unix Socket Connection\n";
echo str_repeat('-', 60) . "\n";

try {
    // Method 1: Direct TcpConnection with Unix socket
    echo "Connecting to Unix socket: {$socketPath}\n";

    // For Unix sockets, set port to 0 and host to socket path
    $connection = new TcpConnection(
        host: $socketPath,
        port: 0,
        endpointUrl: $endpointUrl,
        timeout: 10
    );

    $connection->connect();
    echo "✓ Connected successfully via Unix socket\n";
    echo "  Endpoint: {$connection->getEndpointUrl()}\n";
    echo "  Status: " . ($connection->isConnected() ? 'Connected' : 'Disconnected') . "\n";

    $connection->close();
    echo "✓ Connection closed\n\n";
} catch (Exception $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    echo "  Make sure the socket exists and is accessible\n\n";
}

// =============================================================================
// Part 2: Alternative Socket Path Formats
// =============================================================================

echo "Part 2: Socket Path Formats\n";
echo str_repeat('-', 60) . "\n";

$socketFormats = [
    'Absolute path' => '/var/run/opcua.sock',
    'Temp directory' => '/tmp/opcua.sock',
    'With unix:// scheme' => 'unix:///var/run/opcua.sock',
    'Relative path' => './opcua.sock',
];

foreach ($socketFormats as $format => $path) {
    echo "{$format}: {$path}\n";

    try {
        $conn = new TcpConnection($path, 0, $endpointUrl, 5);
        // Note: We're not actually connecting since sockets may not exist
        echo "  ✓ TcpConnection created successfully\n";
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// =============================================================================
// Part 3: Full Client Example with Unix Socket
// =============================================================================

echo "Part 3: Full OPC UA Client via Unix Socket\n";
echo str_repeat('-', 60) . "\n";

// This part requires an actual OPC UA server listening on a Unix socket
// Uncomment and modify when you have a server available

/*
try {
    echo "Creating OPC UA client with Unix socket...\n";

    // Create client with custom connection
    $client = new OpcUaClient(
        host: $socketPath,
        port: 0,
        endpointUrl: $endpointUrl
    );

    $client->connect();
    echo "✓ Client connected\n";

    // Create session
    $session = $client->createSession('UnixSocketExampleSession');
    $session->activate();
    echo "✓ Session activated\n";

    // Read server status
    $serverStatusNode = NodeId::numeric(0, 2259); // Server.ServerStatus.State
    $value = $session->read($serverStatusNode);
    echo "✓ Server state: {$value->value}\n";

    // Browse root folder
    $rootNode = NodeId::numeric(0, 84); // Root folder
    $references = $session->browse($rootNode);
    echo "✓ Root folder has " . count($references) . " children\n";

    // Cleanup
    $session->close();
    $client->disconnect();
    echo "✓ Disconnected\n\n";

} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}
*/

echo "Note: Part 3 is commented out. Uncomment when you have a server\n";
echo "      listening on a Unix socket.\n\n";

// =============================================================================
// Part 4: Comparison - Unix Socket vs TCP
// =============================================================================

echo "Part 4: Unix Socket vs TCP Connection\n";
echo str_repeat('-', 60) . "\n";

echo "TCP Connection:\n";
echo "  - Network communication (localhost or remote)\n";
echo "  - Firewall rules may apply\n";
echo "  - Standard port (4840)\n";
echo "  - Example: new TcpConnection('localhost', 4840, 'opc.tcp://...')\n\n";

echo "Unix Socket Connection:\n";
echo "  - Local IPC only (same machine)\n";
echo "  - No network stack overhead\n";
echo "  - Filesystem permissions control access\n";
echo "  - Example: new TcpConnection('/var/run/opcua.sock', 0, 'opc.tcp://...')\n\n";

// =============================================================================
// Part 5: Best Practices & Security
// =============================================================================

echo "Part 5: Best Practices & Security\n";
echo str_repeat('-', 60) . "\n";

echo "Socket File Permissions:\n";
echo "  - Set restrictive permissions: chmod 600 /var/run/opcua.sock\n";
echo "  - Or use group permissions: chmod 660 + chgrp opcua-users\n";
echo "  - Verify ownership before connecting\n\n";

echo "Error Handling:\n";
echo "  - Check if socket file exists: file_exists(\$socketPath)\n";
echo "  - Verify socket is writeable: is_writeable(\$socketPath)\n";
echo "  - Handle connection timeouts appropriately\n\n";

echo "Container Deployments:\n";
echo "  - Mount socket directory as volume\n";
echo "  - Docker: -v /var/run/opcua:/var/run/opcua\n";
echo "  - Kubernetes: use hostPath or emptyDir volume\n\n";

// =============================================================================
// Part 6: Troubleshooting
// =============================================================================

echo "Part 6: Troubleshooting Unix Socket Connections\n";
echo str_repeat('-', 60) . "\n";

echo "Common Issues:\n\n";

echo "1. 'No such file or directory'\n";
echo "   - Socket file doesn't exist\n";
echo "   - Check server is running and created the socket\n";
echo "   - Verify the socket path is correct\n\n";

echo "2. 'Permission denied'\n";
echo "   - Insufficient filesystem permissions\n";
echo "   - Run 'ls -la \$socketPath' to check permissions\n";
echo "   - Add user to socket group or adjust permissions\n\n";

echo "3. 'Connection refused'\n";
echo "   - Server not listening on socket\n";
echo "   - Socket file exists but no server attached\n";
echo "   - Check server logs for issues\n\n";

echo "4. 'Connection timeout'\n";
echo "   - Server is busy or unresponsive\n";
echo "   - Increase timeout parameter\n";
echo "   - Check server health\n\n";

// =============================================================================
// Part 7: Utility Functions
// =============================================================================

echo "Part 7: Utility Functions\n";
echo str_repeat('-', 60) . "\n";

/**
 * Check if a Unix socket is available and accessible
 *
 * @param string $socketPath Path to socket file
 * @return bool True if socket is usable
 */
function isSocketAvailable(string $socketPath): bool
{
    // Check if file exists
    if (!file_exists($socketPath)) {
        echo "  ✗ Socket file does not exist: {$socketPath}\n";
        return false;
    }

    // Check if it's a socket
    if (!is_link($socketPath) && filetype($socketPath) !== 'socket') {
        echo "  ✗ File exists but is not a socket: {$socketPath}\n";
        return false;
    }

    // Check if readable
    if (!is_readable($socketPath)) {
        echo "  ✗ Socket is not readable (check permissions)\n";
        return false;
    }

    // Check if writable
    if (!is_writable($socketPath)) {
        echo "  ✗ Socket is not writable (check permissions)\n";
        return false;
    }

    echo "  ✓ Socket is available and accessible\n";
    return true;
}

/**
 * Get socket file information
 *
 * @param string $socketPath Path to socket file
 */
function getSocketInfo(string $socketPath): void
{
    if (!file_exists($socketPath)) {
        echo "  Socket does not exist\n";
        return;
    }

    $stat = stat($socketPath);
    $perms = substr(sprintf('%o', $stat['mode']), -4);
    $owner = posix_getpwuid($stat['uid']);
    $group = posix_getgrgid($stat['gid']);

    echo "  Path: {$socketPath}\n";
    echo "  Permissions: {$perms}\n";
    echo "  Owner: {$owner['name']} (UID: {$stat['uid']})\n";
    echo "  Group: {$group['name']} (GID: {$stat['gid']})\n";
    echo "  Size: {$stat['size']} bytes\n";
    echo "  Last accessed: " . date('Y-m-d H:i:s', $stat['atime']) . "\n";
}

// Example usage of utility functions
echo "\nChecking socket availability:\n";
isSocketAvailable($socketPath);

if (file_exists($socketPath)) {
    echo "\nSocket information:\n";
    getSocketInfo($socketPath);
}

// =============================================================================
// Part 8: Creating a Test Socket (for development)
// =============================================================================

echo "\n\nPart 8: Creating a Test Socket Server\n";
echo str_repeat('-', 60) . "\n";

echo "To test Unix socket connections, you need a server listening on a socket.\n";
echo "You can create a simple test server using PHP's socket functions:\n\n";

echo <<<'PHP'
<?php
// test-socket-server.php
$socketPath = '/tmp/opcua-test.sock';

// Remove old socket if exists
if (file_exists($socketPath)) {
    unlink($socketPath);
}

// Create socket
$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
socket_bind($socket, $socketPath);
socket_listen($socket);

echo "Server listening on {$socketPath}\n";
echo "Press Ctrl+C to stop\n";

while (true) {
    $client = socket_accept($socket);
    echo "Client connected\n";
    socket_close($client);
}

socket_close($socket);
unlink($socketPath);
?>

PHP;

echo "\nRun this in a separate terminal, then run this example again.\n";

// =============================================================================
// Summary
// =============================================================================

echo "\n" . str_repeat('=', 60) . "\n";
echo "Summary\n";
echo str_repeat('=', 60) . "\n\n";

echo "Unix socket support allows OPC UA clients to connect via:\n";
echo "  • Local inter-process communication (IPC)\n";
echo "  • Container-to-container communication\n";
echo "  • Enhanced security with filesystem permissions\n";
echo "  • Lower latency for local connections\n\n";

echo "Key Differences from TCP:\n";
echo "  • Set port to 0 for Unix sockets\n";
echo "  • Host is the socket file path\n";
echo "  • No network exposure (local only)\n";
echo "  • Access controlled by filesystem permissions\n\n";

echo "Example connections:\n";
echo "  TCP:  new TcpConnection('localhost', 4840, 'opc.tcp://localhost:4840')\n";
echo "  Unix: new TcpConnection('/var/run/opcua.sock', 0, 'opc.tcp://unix-socket')\n\n";

echo "For more information, see:\n";
echo "  • OPC UA specification: https://opcfoundation.org/\n";
echo "  • PHP socket functions: https://www.php.net/manual/en/book.sockets.php\n";
echo "  • Unix domain sockets: man 7 unix\n\n";
