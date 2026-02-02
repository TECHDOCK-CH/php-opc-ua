<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TechDock\OpcUa\Client\OpcUaClient;
use TechDock\OpcUa\Core\Security\CertificateValidator;
use TechDock\OpcUa\Core\Security\TrustStore;

/**
 * Example: Secure Connection with Certificate Validation
 *
 * This example demonstrates how to connect to an OPC UA server with
 * certificate validation enabled. This is CRITICAL for production deployments.
 *
 * WARNING: Never use auto-accept mode in production!
 */

try {
    $serverUrl = $argv[1] ?? 'opc.tcp://localhost:4840';
    echo "=== Secure OPC UA Connection with Certificate Validation ===\n\n";

    // Example 1: Basic connection (NO VALIDATION - DEVELOPMENT ONLY)
    echo "Example 1: Insecure connection (no validation)\n";
    echo "WARNING: This is NOT safe for production!\n\n";

    $client = new OpcUaClient($serverUrl);
    $client->connect();
    echo "✓ Connected without validation\n";
    $client->disconnect();
    echo "\n";

    // Example 2: Connection with auto-accept (DEVELOPMENT ONLY)
    echo "Example 2: Connection with auto-accept mode\n";
    echo "WARNING: This trusts ALL certificates - development only!\n\n";

    $trustStore = new TrustStore();
    $trustStore->enableAutoAccept(); // Automatically trust all certificates

    $validator = new CertificateValidator($trustStore);

    $client = new OpcUaClient($serverUrl, certificateValidator: $validator);
    $client->connect();
    echo "✓ Connected with auto-accept\n";

    // Get certificate info
    $session = $client->createSession();
    $endpoint = $client->getSecureChannel()?->getSelectedEndpoint();

    if ($endpoint?->serverCertificate) {
        $info = $validator->validateAndGetInfo($endpoint->serverCertificate);
        echo "\nServer Certificate Information:\n";
        echo "  Subject: {$info['subject']}\n";
        echo "  Issuer: {$info['issuer']}\n";
        echo "  Thumbprint: {$info['thumbprint']}\n";
        echo "  Valid from: " . date('Y-m-d H:i:s', $info['notBefore']) . "\n";
        echo "  Valid until: " . date('Y-m-d H:i:s', $info['notAfter']) . "\n";
        echo "  Self-signed: " . ($info['selfSigned'] ? 'Yes' : 'No') . "\n";
    }

    $session->close();
    $client->disconnect();
    echo "\n";

    // Example 3: Connection with persistent trust store (PRODUCTION)
    echo "Example 3: Connection with persistent trust store (RECOMMENDED)\n";
    echo "This stores trusted certificates on disk.\n\n";

    $trustStorePath = sys_get_temp_dir() . '/opcua-trust-store';
    echo "Trust store path: $trustStorePath\n\n";

    // Create trust store that persists to disk
    $trustStore = new TrustStore($trustStorePath);

    // In production, you would NOT enable auto-accept
    // For this example, we'll enable it to demonstrate the workflow
    echo "Note: In production, add certificates manually to trust store\n";
    echo "      For demo purposes, using auto-accept\n\n";
    $trustStore->enableAutoAccept();

    $validator = new CertificateValidator($trustStore);

    $client = new OpcUaClient($serverUrl, certificateValidator: $validator);
    $client->connect();
    echo "✓ Connected with persistent trust store\n";

    // The certificate is now saved to disk
    echo "Trusted certificates: {$trustStore->count()}\n";

    $client->disconnect();
    echo "\n";

    // Example 4: Manual trust store management (PRODUCTION)
    echo "Example 4: Manual certificate trust management\n\n";

    // Create a new trust store without auto-accept
    $trustStore = new TrustStore();

    // First, try to connect without trusting the certificate (will fail)
    echo "Attempting connection without trusted certificate...\n";

    try {
        $validator = new CertificateValidator($trustStore);
        $client = new OpcUaClient($serverUrl, certificateValidator: $validator);
        $client->connect();
        echo "✗ Should have failed!\n";
    } catch (RuntimeException $e) {
        echo "✓ Connection correctly rejected: {$e->getMessage()}\n\n";
    }

    // Now, manually add the certificate to trust store
    echo "Manually trusting the server certificate...\n";

    // To get the certificate, we need to connect without validation first
    $tempClient = new OpcUaClient($serverUrl);
    $tempClient->connect();
    $tempSession = $tempClient->createSession();
    $tempEndpoint = $tempClient->getSecureChannel()?->getSelectedEndpoint();

    if ($tempEndpoint?->serverCertificate) {
        // Add to trust store
        $trustStore->addTrustedCertificate($tempEndpoint->serverCertificate);
        echo "✓ Certificate added to trust store\n\n";

        // Save thumbprint for later use
        $tempValidator = new CertificateValidator($trustStore);
        $thumbprint = $tempValidator->getThumbprint($tempEndpoint->serverCertificate);
        echo "Certificate thumbprint: $thumbprint\n\n";
    }

    $tempSession->close();
    $tempClient->disconnect();

    // Now connect with validation
    echo "Connecting with manually trusted certificate...\n";
    $validator = new CertificateValidator($trustStore);
    $client = new OpcUaClient($serverUrl, certificateValidator: $validator);
    $client->connect();
    echo "✓ Connection successful with trusted certificate!\n\n";

    $client->disconnect();

    // Example 5: Certificate expiration checking
    echo "\nExample 5: Certificate validation options\n\n";

    $trustStore = new TrustStore();
    $trustStore->enableAutoAccept();

    // Validator with expiration checking (default)
    $validator = new CertificateValidator(
        $trustStore,
        checkExpiration: true,  // Check if certificate is expired
        checkChain: false       // Don't check certificate chain (for self-signed)
    );

    $client = new OpcUaClient($serverUrl, certificateValidator: $validator);
    $client->connect();
    echo "✓ Connection validated (expiration checked)\n";
    $client->disconnect();

    // Cleanup
    if (is_dir($trustStorePath)) {
        $files = glob($trustStorePath . '/*.der');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($trustStorePath);
    }

    echo "\n=== Summary ===\n";
    echo "1. Development: Use auto-accept mode (NOT for production)\n";
    echo "2. Production: Use persistent trust store with manual certificate approval\n";
    echo "3. Always validate certificates in production environments\n";
    echo "4. Store trusted certificates securely on disk\n";
    echo "5. Monitor certificate expiration dates\n\n";

    echo "For production deployments:\n";
    echo "  \$trustStore = new TrustStore('/path/to/trust/store');\n";
    echo "  // Manually add trusted certificates using addTrustedCertificate()\n";
    echo "  \$validator = new CertificateValidator(\$trustStore);\n";
    echo "  \$client = new OpcUaClient(\$serverUrl, certificateValidator: \$validator);\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
