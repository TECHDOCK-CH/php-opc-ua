# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: **security@techdock.ch**

### What to Include

Please include the following information:

1. **Description** - Clear description of the vulnerability
2. **Impact** - What can an attacker achieve?
3. **Reproduction** - Step-by-step instructions to reproduce
4. **Affected versions** - Which versions are vulnerable?
5. **Suggested fix** - If you have one

### Response Timeline

- **Initial response**: Within 48 hours
- **Assessment**: Within 1 week
- **Fix development**: Depends on severity
- **Disclosure**: Coordinated with reporter

## Security Best Practices

### Certificate Validation

**Always validate server certificates in production:**

```php
use TechDock\OpcUa\Core\Security\CertificateValidator;
use TechDock\OpcUa\Core\Security\TrustStore;

$trustStore = new TrustStore(
    trustedCertsPath: '/path/to/trusted/certs',
    issuerCertsPath: '/path/to/issuers',
);

$validator = new CertificateValidator($trustStore);

$client = new OpcUaClient(
    endpointUrl: 'opc.tcp://server:4840',
    certificateValidator: $validator,
);
```

**Never disable certificate validation in production!**

### Authentication

**Use the strongest authentication available:**

```php
// Preferred: Certificate-based authentication
$identity = UserIdentity::x509Certificate($clientCert, $privateKey);

// Acceptable: Username/password over encrypted channel
$identity = UserIdentity::userName('operator', $securePassword);

// Testing only: Anonymous authentication
$identity = UserIdentity::anonymous();
```

### Secure Channels

**Always use encryption in production:**

```php
use TechDock\OpcUa\Core\Security\MessageSecurityMode;

// Production: Require encryption
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://server:4840')
    ->preferSecurityMode(MessageSecurityMode::SignAndEncrypt)
    ->build();

// Never use in production:
// ->withNoSecurity()  // TESTING ONLY
```

### Sensitive Data

**Protect sensitive information:**

```php
// ✅ Good: Use environment variables
$password = getenv('OPCUA_PASSWORD');

// ❌ Bad: Hardcoded credentials
$password = 'my-password';

// ✅ Good: Clear sensitive data when done
sodium_memzero($password);
```

### Logging

**Never log sensitive data:**

```php
// ❌ Bad: Logging password
$logger->debug("Connecting with password: {$password}");

// ✅ Good: Log without sensitive data
$logger->debug("Connecting with username authentication");

// ❌ Bad: Logging full certificate
$logger->debug("Certificate: {$certData}");

// ✅ Good: Log certificate fingerprint only
$logger->debug("Certificate fingerprint: {$fingerprint}");
```

### Timeouts

**Always set appropriate timeouts:**

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://server:4840')
    ->operationTimeout(30000)   // 30 seconds max per operation
    ->sessionTimeout(300000)    // 5 minutes session timeout
    ->build();
```

### Input Validation

**Validate all external input:**

```php
// ✅ Good: Validate before use
if (!preg_match('#^opc\.tcp://[\w.-]+:\d+#', $endpoint)) {
    throw new InvalidArgumentException('Invalid endpoint URL');
}

// ✅ Good: Validate node IDs
if ($namespaceIndex < 0 || $namespaceIndex > 65535) {
    throw new InvalidArgumentException('Invalid namespace index');
}
```

### Error Handling

**Don't leak sensitive information in errors:**

```php
// ❌ Bad: Exposing internal details
throw new RuntimeException("Connection failed: {$internalError}");

// ✅ Good: Generic error message
throw new RuntimeException('Connection failed');

// ✅ Better: Log details, show generic message
$logger->error("Connection failed: {$internalError}");
throw new RuntimeException('Connection failed. Check logs for details.');
```

## Known Security Considerations

### Certificate Storage

- Store private keys securely with appropriate file permissions (chmod 600)
- Use password-protected private keys when possible
- Rotate certificates according to your security policy

### Network Security

- Use firewalls to restrict OPC UA port access
- Consider using VPNs for remote connections
- Monitor for unusual connection patterns

### Dependency Security

This library uses:

- `phpseclib/phpseclib` - For cryptographic operations
- Regular security audits via `composer audit`
- Automated dependency updates via GitHub Actions

### PHP Version

- **Minimum PHP 8.4** required for security features
- Keep PHP updated with latest security patches
- Monitor PHP security advisories

## Security Features

### Built-in Protections

- **Type safety** - Strict types prevent many vulnerabilities
- **Input validation** - All inputs validated before use
- **Memory safety** - No buffer overflows in PHP
- **Constant-time comparisons** - For cryptographic operations
- **Secure random** - Uses cryptographically secure RNG

### Secure Defaults

- Certificate validation **enabled by default**
- Prefers **encrypted connections**
- **Safe timeouts** to prevent hanging
- **No debug output** in production mode

## Compliance

This library aims to comply with:

- **OPC UA Security Specification** - Part 6 of UA specification
- **OWASP Top 10** - Common web application security risks
- **CWE/SANS Top 25** - Most dangerous software weaknesses

## Security Audits

- Last audit: TBD
- Next scheduled: TBD

## Contact

For security questions: security@techdock.ch

For general questions: info@techdock.ch
