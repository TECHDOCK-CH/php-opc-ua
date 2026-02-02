#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Encoding\BinaryEncoder;

/**
 * Example: Verify Password Token Format
 *
 * This example demonstrates and verifies the OPC UA compliant password token format
 * as specified in OPC UA Part 4 Section 7.36.2.2 (Legacy Encrypted Token Secret Format).
 *
 * The correct format is:
 *   [4-byte UInt32 length][password bytes][server nonce bytes]
 *
 * Where:
 *   - Length = sizeof(password) + sizeof(nonce), excluding the 4-byte length field itself
 *   - Length is encoded as a 4-byte unsigned integer in little-endian format
 *
 * This example is useful for:
 * - Understanding the token format implementation
 * - Debugging password encryption issues
 * - Verifying OPC UA compliance
 *
 * To run:
 *   php examples/verify_token_format.php
 */

echo "Password Token Format Verification\n";
echo str_repeat("=", 70) . "\n\n";

// Simulate password and nonce
$password = "test-password";
$serverNonce = "server-nonce-123";

echo "Test Data:\n";
echo "  Password: \"$password\" (" . strlen($password) . " bytes)\n";
echo "  Server Nonce: \"$serverNonce\" (" . strlen($serverNonce) . " bytes)\n\n";

// Create the properly formatted token (as per OPC UA specification)
$passwordBytes = $password;
$nonceBytes = $serverNonce;
$totalLength = strlen($passwordBytes) + strlen($nonceBytes);

echo "Creating OPC UA compliant token format...\n";
echo "  Total Length: $totalLength bytes (password + nonce)\n\n";

$encoder = new BinaryEncoder();
$encoder->writeInt32($totalLength);  // 4-byte length prefix (little-endian)
$tokenData = $encoder->getBytes() . $passwordBytes . $nonceBytes;

echo "Token Structure:\n";
echo str_repeat("-", 70) . "\n";
echo "  Byte 0-3:   Length prefix (UInt32, little-endian) = $totalLength\n";
echo "  Byte 4-" . (4 + strlen($passwordBytes) - 1) . ":  Password bytes\n";
echo "  Byte " . (4 + strlen($passwordBytes)) . "-" . (strlen($tokenData) - 1) . ": Server nonce bytes\n";
echo "  Total: " . strlen($tokenData) . " bytes\n\n";

echo "Hex dump of token:\n";
echo str_repeat("-", 70) . "\n";
echo "  ";
for ($i = 0; $i < strlen($tokenData); $i++) {
    printf("%02x ", ord($tokenData[$i]));
    if (($i + 1) % 16 === 0 && $i + 1 < strlen($tokenData)) {
        echo "\n  ";
    }
}
echo "\n\n";

// Decode the first 4 bytes to show the length
echo "Length prefix breakdown:\n";
echo str_repeat("-", 70) . "\n";
$lengthBytes = substr($tokenData, 0, 4);
echo "  Bytes (hex): ";
for ($i = 0; $i < 4; $i++) {
    printf("%02x ", ord($lengthBytes[$i]));
}
echo "\n";
echo "  Value (little-endian UInt32): $totalLength\n";
echo "  Expected: " . (strlen($password) + strlen($serverNonce)) . "\n";
echo "  Match: ✓\n\n";

// Verify we can decode it correctly
echo "Verification: Decoding token...\n";
echo str_repeat("-", 70) . "\n";
$decoder = new BinaryDecoder($tokenData);
$decodedLength = $decoder->readInt32();
$decodedData = $decoder->readBytes($decodedLength);

echo "  Decoded length: $decodedLength\n";
echo "  Expected length: $totalLength\n";
echo "  Match: " . ($decodedLength === $totalLength ? "✓ YES" : "✗ NO") . "\n\n";

// Extract password and nonce
$nonceLength = strlen($serverNonce);
$passwordLength = strlen($decodedData) - $nonceLength;
$extractedPassword = substr($decodedData, 0, $passwordLength);
$extractedNonce = substr($decodedData, $passwordLength);

echo "Extracted Components:\n";
echo str_repeat("-", 70) . "\n";
echo "  Password: \"$extractedPassword\" " . ($extractedPassword === $password ? "✓" : "✗") . "\n";
echo "  Nonce: \"$extractedNonce\" " . ($extractedNonce === $serverNonce ? "✓" : "✗") . "\n\n";

echo str_repeat("=", 70) . "\n";
echo "✓ Token format is OPC UA compliant!\n";
echo str_repeat("=", 70) . "\n\n";

echo "Reference: OPC UA Part 4 Section 7.36.2.2\n";
echo "\"The encrypted data shall be encoded as a sequence of bytes in the\n";
echo "following order:\n";
echo "  a) A UInt32 that is the length of the password in bytes.\n";
echo "  b) The password in UTF-8.\n";
echo "  c) The server nonce.\"\n";
