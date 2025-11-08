# Security Guide

Comprehensive guide to securing OPC UA connections, authentication, and certificate management.

## Security Levels

### Level 1: No Security (Testing Only)

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withNoSecurity()
    ->withAnonymousAuth()
    ->build();
```

**Use only for**: Local development, testing

### Level 2: Encrypted Transport

```php
$client = ClientBuilder::create()
    ->endpoint('opc.tcp://server:4840')
    ->preferSecurityMode(MessageSecurityMode::SignAndEncrypt)
    ->withUsernameAuth($user, $pass)
    ->build();
```

**Use for**: Production deployments

### Level 3: Certificate Authentication

```php
$clientCert = file_get_contents('/path/to/client-cert.pem');
$privateKey = file_get_contents('/path/to/client-key.pem');

$identity = UserIdentity::x509Certificate($clientCert, $privateKey);

$client = ClientBuilder::create()
    ->endpoint('opc.tcp://server:4840')
    ->preferSecurityMode(MessageSecurityMode::SignAndEncrypt)
    ->withUserIdentity($identity)
    ->build();
```

**Use for**: High-security environments

## Certificate Management

### Generating Certificates

```bash
# Generate private key
openssl genrsa -out client-key.pem 2048

# Generate certificate signing request
openssl req -new -key client-key.pem -out client.csr

# Self-sign certificate (for testing)
openssl x509 -req -days 365 -in client.csr -signkey client-key.pem -out client-cert.pem
```

### Certificate Validation

```php
use TechDock\OpcUa\Core\Security\{CertificateValidator, TrustStore};

$trustStore = new TrustStore(
    trustedCertsPath: '/path/to/trusted',
    issuerCertsPath: '/path/to/issuers',
);

$validator = new CertificateValidator($trustStore);

$client = new OpcUaClient(
    endpointUrl: 'opc.tcp://server:4840',
    certificateValidator: $validator,
);
```

**Important**: Always validate certificates in production!

## Authentication Methods

### Anonymous

```php
$identity = UserIdentity::anonymous();
```

### Username/Password

```php
// ❌ Bad: Hardcoded
$identity = UserIdentity::userName('admin', 'password123');

// ✅ Good: Environment variables
$identity = UserIdentity::userName(
    getenv('OPCUA_USERNAME'),
    getenv('OPCUA_PASSWORD')
);
```

### X.509 Certificate

```php
$cert = file_get_contents('/secure/path/client-cert.pem');
$key = file_get_contents('/secure/path/client-key.pem');

$identity = UserIdentity::x509Certificate($cert, $key);
```

## Best Practices

1. **Always use encryption in production**
2. **Validate server certificates**
3. **Store credentials in environment variables**
4. **Use certificate authentication when possible**
5. **Set appropriate file permissions on private keys** (chmod 600)
6. **Rotate certificates regularly**
7. **Monitor failed authentication attempts**
8. **Use secure random for sensitive operations**

## See Also

- [SECURITY.md](../SECURITY.md) - Security policy
- [Certificate Example](../examples/secure_connection_with_validation.php)
