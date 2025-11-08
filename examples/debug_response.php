<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;
use TechDock\OpcUa\Core\Messages\GetEndpointsRequest;
use TechDock\OpcUa\Core\Messages\OpenSecureChannelRequest;
use TechDock\OpcUa\Core\Messages\OpenSecureChannelResponse;
use TechDock\OpcUa\Core\Messages\RequestHeader;
use TechDock\OpcUa\Core\Security\AsymmetricSecurityHeader;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;
use TechDock\OpcUa\Core\Security\SequenceHeader;
use TechDock\OpcUa\Core\Security\SymmetricSecurityHeader;
use TechDock\OpcUa\Core\Transport\HelloMessage;
use TechDock\OpcUa\Core\Transport\MessageHeader;
use TechDock\OpcUa\Core\Transport\MessageType;
use TechDock\OpcUa\Core\Transport\TcpConnection;
use TechDock\OpcUa\Core\Types\NodeId;

$endpointUrl = 'opc.tcp://localhost:4840';

// Parse URL
$parsed = parse_url($endpointUrl);
$host = $parsed['host'] ?? 'localhost';
$port = $parsed['port'] ?? 4840;

$connection = new TcpConnection($host, $port, $endpointUrl);

echo "=== Debug GetEndpoints Response ===\n\n";

try {
    // Step 1: Connect
    $connection->connect();
    echo "Connected to {$endpointUrl}\n";

    // Step 2: Hello
    $hello = HelloMessage::create($endpointUrl);
    $connection->send($hello->encode());
    echo "Sent HELLO\n";

    // Step 3: Receive ACK
    $header = $connection->receiveHeader();
    if ($header->messageType !== MessageType::Acknowledge) {
        throw new RuntimeException("Expected ACK, got {$header->messageType->value}");
    }
    $payload = $connection->receive($header->getPayloadSize());
    echo "Received ACK\n";

    // Step 4: Send OpenSecureChannel
    $bodyEncoder = new BinaryEncoder();
    NodeId::numeric(0, 446)->encode($bodyEncoder);

    $openRequest = OpenSecureChannelRequest::issue(
        securityMode: MessageSecurityMode::None,
        clientNonce: null,
        requestedLifetime: 600000,
    );
    $openRequest->encode($bodyEncoder);
    $messageBody = $bodyEncoder->getBytes();

    $securityHeader = new AsymmetricSecurityHeader(
        secureChannelId: 0,
        securityPolicy: SecurityPolicy::None,
        senderCertificate: null,
        receiverCertificateThumbprint: null
    );
    $secHeaderEncoder = new BinaryEncoder();
    $securityHeader->encode($secHeaderEncoder);
    $secHeaderBytes = $secHeaderEncoder->getBytes();

    $seqHeader = new SequenceHeader(sequenceNumber: 1, requestId: 1);
    $seqHeaderEncoder = new BinaryEncoder();
    $seqHeader->encode($seqHeaderEncoder);
    $seqHeaderBytes = $seqHeaderEncoder->getBytes();

    $messageSize = MessageHeader::HEADER_SIZE + strlen($secHeaderBytes) + strlen($seqHeaderBytes) + strlen($messageBody);
    $msgHeader = MessageHeader::final(MessageType::OpenSecureChannel, $messageSize);
    $msgHeaderEncoder = new BinaryEncoder();
    $msgHeader->encode($msgHeaderEncoder);
    $msgHeaderBytes = $msgHeaderEncoder->getBytes();

    $connection->send($msgHeaderBytes . $secHeaderBytes . $seqHeaderBytes . $messageBody);
    echo "Sent OpenSecureChannel request\n";

    // Step 5: Receive OpenSecureChannel response
    $header = $connection->receiveHeader();
    if ($header->messageType !== MessageType::OpenSecureChannel) {
        throw new RuntimeException("Expected OPN, got {$header->messageType->value}");
    }
    $payload = $connection->receive($header->getPayloadSize());
    echo "Received OpenSecureChannel response (" . strlen($payload) . " bytes)\n";

    $decoder = new BinaryDecoder($payload);

    echo "=== Decoding OpenSecureChannel Response ===\n";
    $pos = $decoder->getPosition();
    $asymSecHeader = AsymmetricSecurityHeader::decode($decoder);
    echo "AsymSecHeader: pos {$pos} to {$decoder->getPosition()}\n";

    $pos = $decoder->getPosition();
    $seqHeader = SequenceHeader::decode($decoder);
    echo "SeqHeader: pos {$pos} to {$decoder->getPosition()}\n";

    $pos = $decoder->getPosition();
    $typeId = NodeId::decode($decoder);
    echo "TypeId: pos {$pos} to {$decoder->getPosition()}, ns={$typeId->namespaceIndex}, i={$typeId->identifier}\n";

    // Show next 32 bytes for debugging
    $pos = $decoder->getPosition();
    echo "Next 32 bytes at pos {$pos}: " . bin2hex(substr($payload, $pos, 32)) . "\n";
    echo "First byte: 0x" . bin2hex(substr($payload, $pos, 1)) . " (ASCII: " . chr(ord(substr($payload, $pos, 1))) . ")\n";

    // Decode the full response to get the tokenId
    try {
        $openResponse = OpenSecureChannelResponse::decode($decoder);
        $tokenId = $openResponse->securityToken->tokenId;
        echo "Security Token ID: {$tokenId}\n";
        echo "Channel ID: {$openResponse->securityToken->channelId}\n";
    } catch (Throwable $e) {
        echo "ERROR decoding OpenSecureChannelResponse: {$e->getMessage()}\n";
        echo "Position when error occurred: {$decoder->getPosition()}\n";
        throw $e;
    }
    echo "\n";

    // Step 6: Send GetEndpoints
    $bodyEncoder = new BinaryEncoder();
    NodeId::numeric(0, 428)->encode($bodyEncoder);

    $getEndpointsRequest = new GetEndpointsRequest(
        requestHeader: RequestHeader::create(requestHandle: 2),
        endpointUrl: $endpointUrl,
    );
    $getEndpointsRequest->encode($bodyEncoder);
    $messageBody = $bodyEncoder->getBytes();

    // Use symmetric header with the ACTUAL channelId and tokenId from OpenSecureChannel response
    $channelId = $openResponse->securityToken->channelId;
    $symSecHeader = new SymmetricSecurityHeader(secureChannelId: $channelId, tokenId: $tokenId);
    $symSecHeaderEncoder = new BinaryEncoder();
    $symSecHeader->encode($symSecHeaderEncoder);
    $symSecHeaderBytes = $symSecHeaderEncoder->getBytes();

    $seqHeader = new SequenceHeader(sequenceNumber: 2, requestId: 2);
    $seqHeaderEncoder = new BinaryEncoder();
    $seqHeader->encode($seqHeaderEncoder);
    $seqHeaderBytes = $seqHeaderEncoder->getBytes();

    $messageSize = MessageHeader::HEADER_SIZE + strlen($symSecHeaderBytes) + strlen($seqHeaderBytes) + strlen($messageBody);
    $msgHeader = MessageHeader::final(MessageType::Message, $messageSize);
    $msgHeaderEncoder = new BinaryEncoder();
    $msgHeader->encode($msgHeaderEncoder);
    $msgHeaderBytes = $msgHeaderEncoder->getBytes();

    $connection->send($msgHeaderBytes . $symSecHeaderBytes . $seqHeaderBytes . $messageBody);
    echo "Sent GetEndpoints request\n";

    // Step 7: Receive GetEndpoints response
    $header = $connection->receiveHeader();
    echo "Received response type: {$header->messageType->value}\n";
    echo "Message size: {$header->messageSize}\n";

    $payload = $connection->receive($header->getPayloadSize());
    echo "Payload size: " . strlen($payload) . " bytes\n\n";

    echo "=== HEX DUMP OF PAYLOAD ===\n";
    $hex = bin2hex($payload);
    echo chunk_split($hex, 64, "\n");
    echo "\n";

    echo "=== DECODING STEP BY STEP ===\n";
    $decoder = new BinaryDecoder($payload);

    echo "Position 0: Start\n";

    // Decode symmetric security header
    $startPos = $decoder->getPosition();
    $symSecHeader = SymmetricSecurityHeader::decode($decoder);
    $endPos = $decoder->getPosition();
    echo "Position {$startPos}-{$endPos}: SymmetricSecurityHeader (tokenId={$symSecHeader->tokenId})\n";
    echo "  Bytes: " . bin2hex(substr($payload, $startPos, $endPos - $startPos)) . "\n";

    // Decode sequence header
    $startPos = $decoder->getPosition();
    $seqHeader = SequenceHeader::decode($decoder);
    $endPos = $decoder->getPosition();
    echo "Position {$startPos}-{$endPos}: SequenceHeader (seq={$seqHeader->sequenceNumber}, req={$seqHeader->requestId})\n";
    echo "  Bytes: " . bin2hex(substr($payload, $startPos, $endPos - $startPos)) . "\n";

    // Try to decode TypeId
    $startPos = $decoder->getPosition();
    echo "Position {$startPos}: About to decode TypeId\n";
    echo "  Next 16 bytes: " . bin2hex(substr($payload, $startPos, 16)) . "\n";
    echo "  First byte (encoding mask): 0x" . bin2hex(substr($payload, $startPos, 1)) . "\n";

    try {
        $typeId = NodeId::decode($decoder);
        $endPos = $decoder->getPosition();
        echo "Position {$startPos}-{$endPos}: TypeId (ns={$typeId->namespaceIndex}, i={$typeId->identifier})\n";
        echo "  Bytes: " . bin2hex(substr($payload, $startPos, $endPos - $startPos)) . "\n";
    } catch (Throwable $e) {
        echo "ERROR decoding TypeId: {$e->getMessage()}\n";
    }

} catch (Throwable $e) {
    echo "\nERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    $connection->close();
}
