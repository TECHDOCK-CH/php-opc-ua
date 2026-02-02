# OPC UA Client Examples

This directory contains practical examples demonstrating how to use the PHP OPC UA client library.

## Running Examples

### Prerequisites

1. Install dependencies:
   ```bash
   composer install
   ```

2. Start the test OPC UA server (optional, for testing):
   ```bash
   podman-compose up -d
   ```

   The test server runs on `opc.tcp://localhost:4840` with:
   - Username: `integration-user`
   - Password: `integration-pass`

### Running an Example

```bash
php examples/<example-name>.php
```

Or make them executable and run directly:
```bash
chmod +x examples/*.php
./examples/<example-name>.php
```

## Available Examples

### Authentication Examples

#### `username_password_auth.php`
Basic username/password authentication example.

**Features:**
- Automatic endpoint discovery
- Automatic policy selection
- OPC UA compliant password encryption

**Usage:**
```bash
php examples/username_password_auth.php
```

**What it demonstrates:**
- How to connect with username/password credentials
- Automatic detection of authentication policies
- Automatic selection of strongest security policy

---

#### `username_password_auth_detailed.php`
Detailed analysis of authentication policies and endpoint discovery.

**Usage:**
```bash
php examples/username_password_auth_detailed.php
```

**What it demonstrates:**
- Endpoint discovery process
- Available authentication policies
- Security mode and policy selection
- Detailed breakdown of server capabilities

**Output includes:**
- List of all available endpoints
- User token policies for each endpoint
- Security modes and policies
- Selected endpoint details

---

#### `verify_token_format.php`
Verification of OPC UA compliant password token format.

**Usage:**
```bash
php examples/verify_token_format.php
```

**What it demonstrates:**
- OPC UA Part 4 Section 7.36.2.2 compliance
- Legacy Encrypted Token Secret Format
- 4-byte length prefix implementation
- Token encoding and decoding

**Useful for:**
- Understanding the token format
- Debugging password encryption issues
- Learning OPC UA specifications

---

### Connection Examples

#### `test_connection.php`
Basic connection test without authentication.

**Usage:**
```bash
php examples/test_connection.php
```

---

### Browsing Examples

#### `browse_server.php`
Browse the OPC UA server address space.

**Usage:**
```bash
php examples/browse_server.php
```

---

#### `fetch_all_nodes.php`
Fetch and display all nodes from the server.

**Usage:**
```bash
php examples/fetch_all_nodes.php
```

---

#### `inspect_node.php`
Inspect a specific node and its properties.

**Usage:**
```bash
php examples/inspect_node.php
```

---

### Debug Examples

#### `debug_session.php`
Debug session creation and activation.

**Usage:**
```bash
php examples/debug_session.php
```

---

## Example Server Configuration

The included `podman-compose.yml` in the project root starts a test OPC UA server with:

- **Image**: `mcr.microsoft.com/iotedge/opc-plc:latest`
- **Port**: `4840` (host) → `50000` (container)
- **Server Name**: DemoServer
- **Authentication**:
  - Anonymous access enabled
  - Username: `integration-user`
  - Password: `integration-pass`
  - Certificate authentication enabled (with test certificates)
- **Security**: Supports None, Sign, and SignAndEncrypt modes
- **Transport**: Unsecure transport enabled for testing

### Starting the Test Server

```bash
# Start the server
podman-compose up -d

# Check server status
podman-compose ps

# View server logs
podman-compose logs -f

# Stop the server
podman-compose down
```

## Common Patterns

### Basic Connection (Anonymous)

```php
use TechDock\OpcUa\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withAutoDiscovery()
    ->withAnonymousAuth()
    ->build();
```

### Username/Password Authentication

```php
use TechDock\OpcUa\Client\ClientBuilder;
use TechDock\OpcUa\Core\Security\MessageSecurityMode;
use TechDock\OpcUa\Core\Security\SecurityPolicy;

$client = ClientBuilder::create()
    ->endpoint('opc.tcp://localhost:4840')
    ->withAutoDiscovery()
    ->preferSecurityMode(MessageSecurityMode::None)
    ->preferSecurityPolicy(SecurityPolicy::None)
    ->withUsernameAuth('integration-user', 'integration-pass')
    ->build();
```

### Browsing Nodes

```php
use TechDock\OpcUa\Core\Types\NodeId;

// Browse the Objects folder
$nodes = $client->browser->browse(NodeId::numeric(0, 84));

foreach ($nodes as $node) {
    echo $node->browseName->name . "\n";
}
```

### Reading Node Values

```php
use TechDock\OpcUa\Core\Types\NodeId;

$nodeId = NodeId::numeric(0, 2258); // Server/ServerStatus/CurrentTime
$value = $client->session->read([$nodeId]);

echo "Value: " . $value[0]->value . "\n";
```

## Authentication Implementation Details

The username/password authentication has been fixed to comply with OPC UA specifications:

### Password Token Format (OPC UA Part 4 Section 7.36.2.2)

The encrypted password token now uses the correct format:

```
┌──────────┬─────────────────┬──────────────────┐
│ Length   │ Password Bytes  │ Server Nonce     │
│ (UInt32) │                 │                  │
└──────────┴─────────────────┴──────────────────┘
  4 bytes      N bytes           M bytes

where Length = N + M (excluding the 4-byte length field itself)
```

### Automatic Policy Selection

The client now automatically:
1. Discovers available endpoints
2. Finds username authentication policies
3. Selects the strongest policy (Basic256Sha256 > Basic256 > Basic128Rsa15)
4. Encrypts the password using the policy's security algorithm
5. Activates the session with the correct policy ID

No manual policy ID configuration is required!

## Troubleshooting

### Connection Refused

If you get "Connection refused":
```bash
# Check if the server is running
podman-compose ps

# Start the server if it's not running
podman-compose up -d
```

### Authentication Failed

If authentication fails:
1. Verify the server is configured to accept username/password authentication
2. Check that the username and password are correct
3. Run `username_password_auth_detailed.php` to see available policies
4. Ensure the security mode and policy match the server configuration

### Port Already in Use

If port 4840 is already in use:
```bash
# Stop other OPC UA servers
podman-compose down

# Or edit podman-compose.yml to use a different port
ports:
  - "4841:50000"  # Use 4841 instead of 4840
```

## Further Reading

- [OPC UA Specification](https://reference.opcfoundation.org/)
- [Project Documentation](../README.md)
