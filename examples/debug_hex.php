<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Messages\GetEndpointsRequest;
use TechDock\OpcUa\Core\Messages\RequestHeader;
use TechDock\OpcUa\Core\Types\NodeId;

echo "=== Testing GetEndpointsRequest Encoding ===\n\n";

// Create the request
$request = new GetEndpointsRequest(
    requestHeader: RequestHeader::create(requestHandle: 1),
    endpointUrl: 'opc.tcp://localhost:4840',
);

// Encode with TypeId (as SecureChannel does)
$bodyEncoder = new BinaryEncoder();
NodeId::numeric(0, 428)->encode($bodyEncoder);
$request->encode($bodyEncoder);
$messageBody = $bodyEncoder->getBytes();

echo "Message body with TypeId:\n";
echo "Length: " . strlen($messageBody) . " bytes\n";
echo "Hex dump:\n";
echo chunk_split(bin2hex($messageBody), 32, "\n");
echo "\n";

// Decode the TypeId to show structure
echo "First 4 bytes (TypeId encoding): " . bin2hex(substr($messageBody, 0, 4)) . "\n";
echo "  Byte 0: 0x" . bin2hex(substr($messageBody, 0, 1)) . " (encoding mask)\n";
echo "  Byte 1: 0x" . bin2hex(substr($messageBody, 1, 1)) . " (namespace)\n";
echo "  Bytes 2-3: 0x" . bin2hex(substr($messageBody, 2, 2)) . " (identifier = 428)\n";
