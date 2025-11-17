<?php

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Types\NodeId;

// SSH tunnel configuration
$sshUser = 'c4l';
$sshHost = '10.3.2.69';
$remoteOpcUaHost = '192.168.5.227';
$remoteOpcUaPort = 30840;
$localOpcUaPort = 20840; // An arbitrary free local port

// The OPC UA server endpoint through the tunnel
$endpointUrl = "opc.tcp://127.0.0.1:{$localOpcUaPort}";

// The node to read
$nodeId = "ns=4;i=6006";

echo "Starting SSH tunnel...\n";
$tunnelCommand = "ssh -L {$localOpcUaPort}:{$remoteOpcUaHost}:{$remoteOpcUaPort} {$sshUser}@{$sshHost} -N -f";
echo "Executing: {$tunnelCommand}\n";
$output = [];
$return_var = 0;
exec($tunnelCommand, $output, $return_var);

if ($return_var !== 0) {
    echo "Failed to create SSH tunnel. Please ensure you have passwordless SSH access to the jump host.\n";
    echo "Error output:\n" . implode("\n", $output) . "\n";
    exit(1);
}

// Find the PID of the background SSH process
$pidCommand = "pgrep -f 'ssh -L {$localOpcUaPort}:{$remoteOpcUaHost}:{$remoteOpcUaPort}'";
$pid = exec($pidCommand);

if (!$pid) {
    echo "Could not find SSH tunnel process PID. It might have failed to start.\n";
    exit(1);
}

echo "SSH tunnel established with PID: {$pid}\n";
echo "Connecting to OPC UA server at {$endpointUrl}...\n";

try {
    // Create a client
    $client = ClientBuilder::create()
        ->endpoint($endpointUrl)
        ->build();

    echo "Connected successfully!\n";

    // Read the value of the node
    echo "Reading node {$nodeId}...\n";
    if (preg_match('/ns=(\d+);i=(\d+)/', $nodeId, $matches)) {
        $namespaceIndex = (int)$matches[1];
        $identifier = (int)$matches[2];
        $nodeIdObject = NodeId::numeric($namespaceIndex, $identifier);
    } else {
        throw new \InvalidArgumentException("Invalid NodeId format: {$nodeId}");
    }
    $value = $client->session->read([$nodeIdObject]);

    echo "Value of node {$nodeId}:\n";
    print_r($value);
    echo "\n";

    // Disconnect from the server
    $client->disconnect();
    echo "Disconnected.\n";

} catch (\Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} finally {
    // Always try to clean up the SSH tunnel
    if ($pid) {
        echo "Closing SSH tunnel (PID: {$pid})...\n";
        exec("kill {$pid}");
        echo "Tunnel closed.\n";
    }
}