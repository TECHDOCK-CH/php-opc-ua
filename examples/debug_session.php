<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Core\Transport\TcpConnection;
use TechDock\OpcUa\Core\Transport\HelloMessage;
use TechDock\OpcUa\Core\Security\SecureChannel;
use TechDock\OpcUa\Core\Messages\CreateSessionRequest;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Types\NodeId;
use TechDock\OpcUa\Core\Types\ApplicationDescription;
use TechDock\OpcUa\Core\Types\Enums\ApplicationType;

/**
 * Debug tool to see what the server is sending
 */

$endpointUrl = 'opc.tcp://127.0.0.1:4840';

echo "Testing CreateSession with $endpointUrl\n\n";

try {
    // Create TCP connection
    $tcp = new TcpConnection('127.0.0.1', 4840);
    $tcp->connect();
    echo "✓ TCP connected\n";

    // Send Hello
    $hello = HelloMessage::create($endpointUrl);
    $tcp->send($hello->encode());
    echo "✓ Hello sent\n";

    // Receive ACK
    $ackData = $tcp->receive();
    echo "✓ ACK received (" . strlen($ackData) . " bytes)\n";
    echo "  Hex: " . bin2hex($ackData) . "\n\n";

    // Create and send CreateSessionRequest
    echo "Sending CreateSessionRequest...\n";

    $clientDescription = new ApplicationDescription(
        applicationUri: 'urn:php-opcua:client',
        productUri: 'urn:php-opcua',
        applicationName: ['text' => 'PHP OPC UA Client', 'locale' => ''],
        applicationType: ApplicationType::Client,
        gatewayServerUri: '',
        discoveryProfileUri: '',
        discoveryUrls: [],
    );

    $request = new CreateSessionRequest(
        requestHeader: [
            'authenticationToken' => NodeId::null(),
            'timestamp' => new DateTime(),
            'requestHandle' => 1,
            'returnDiagnostics' => 0,
            'auditEntryId' => '',
            'timeoutHint' => 30000,
            'additionalHeader' => null,
        ],
        clientDescription: $clientDescription,
        serverUri: '',
        endpointUrl: $endpointUrl,
        sessionName: 'PHP OPC UA Session',
        clientNonce: random_bytes(32),
        clientCertificate: '',
        requestedSessionTimeout: 60000.0,
        maxResponseMessageSize: 0,
    );

    $encoder = new BinaryEncoder();
    $request->encode($encoder);
    $requestData = $encoder->getBytes();

    echo "Request data: " . strlen($requestData) . " bytes\n";
    echo "First 100 bytes hex:\n" . chunk_split(bin2hex(substr($requestData, 0, 100)), 2, ' ') . "\n\n";

    // We need to wrap this in a secure channel message
    // For now, let's just try to send it and see what happens
    $tcp->send($requestData);

    echo "Waiting for response...\n";
    $responseData = $tcp->receive();

    echo "✓ Response received (" . strlen($responseData) . " bytes)\n";
    echo "Full hex dump:\n";
    echo chunk_split(bin2hex($responseData), 2, ' ') . "\n\n";

    if (strlen($responseData) < 100) {
        echo "Response appears to be truncated or an error message.\n";
    }

    $tcp->disconnect();

} catch (Throwable $e) {
    echo "\n✗ Error: {$e->getMessage()}\n";
    echo "At: {$e->getFile()}:{$e->getLine()}\n";
}
