# Architecture Overview

High-level architecture of the PHP OPC UA Client library.

## Layers

```
┌───────────────────────────────────────────────────────┐
│                   Application Layer                    │
│            (Your PHP Application Code)                 │
├───────────────────────────────────────────────────────┤
│                   Client Layer                         │
│  ┌─────────────┬──────────────┬─────────────────┐    │
│  │ClientBuilder│ConnectedClient│   Browser       │    │
│  │  (Fluent    │  (Wrapper)    │ (Navigation)    │    │
│  │   API)      │               │                 │    │
│  └─────────────┴──────────────┴─────────────────┘    │
├───────────────────────────────────────────────────────┤
│                   Session Layer                        │
│  ┌──────────────────────────────────────────────┐    │
│  │  Session (Service Operations)                 │    │
│  │  - Read/Write    - Call                       │    │
│  │  - Browse        - Subscribe                  │    │
│  │  - History       - Register                   │    │
│  └──────────────────────────────────────────────┘    │
├───────────────────────────────────────────────────────┤
│                   Protocol Layer                       │
│  ┌─────────────┬──────────────┬─────────────────┐    │
│  │SecureChannel│BinaryEncoder │ SecurityHandler │    │
│  │  (Security) │  (Codec)     │  (Crypto)       │    │
│  └─────────────┴──────────────┴─────────────────┘    │
├───────────────────────────────────────────────────────┤
│                   Transport Layer                      │
│  ┌──────────────────────────────────────────────┐    │
│  │  TcpConnection (Socket Communication)         │    │
│  └──────────────────────────────────────────────┘    │
└───────────────────────────────────────────────────────┘
```

## Core Components

### Client Layer (`src/Client/`)

**Purpose**: High-level API for application developers

**Key Classes**:
- `ClientBuilder` - Fluent API for client configuration
- `ConnectedClient` - Wrapper with session, browser, cache
- `OpcUaClient` - Low-level client with connection management
- `Session` - OPC UA service operations
- `Browser` - Address space navigation
- `Subscription` - Real-time monitoring
- `MonitoredItem` - Individual monitored data point

### Protocol Layer (`src/Core/`)

**Purpose**: OPC UA binary protocol implementation

#### Messages (`src/Core/Messages/`)
Service request/response messages:
- Browse, Read, Write
- CreateSession, ActivateSession
- CreateSubscription, Publish
- GetEndpoints, FindServers

#### Types (`src/Core/Types/`)
OPC UA data types:
- Primitives: NodeId, QualifiedName, LocalizedText
- Complex: DataValue, Variant, ExtensionObject
- Structures: Custom structure support

#### Encoding (`src/Core/Encoding/`)
- `BinaryEncoder` - Encode to binary
- `BinaryDecoder` - Decode from binary
- `IEncodeable` - Interface for encodeable types

#### Security (`src/Core/Security/`)
- `SecureChannel` - Encrypted communication
- `CertificateValidator` - X.509 validation
- `SecurityPolicyHandlerInterface` - Crypto operations
- `Basic256Sha256Handler` - AES-256 + SHA-256

#### Transport (`src/Core/Transport/`)
- `TcpConnection` - Socket communication
- `MessageHeader` - Message framing
- `HelloMessage`, `AcknowledgeMessage` - Handshake

## Data Flow

### Read Operation

```
Application
    ↓ read(NodeId)
Session
    ↓ ReadRequest
SecureChannel
    ↓ encode → encrypt → sign
TcpConnection
    ↓ send bytes
    ↓
[OPC UA Server]
    ↓
TcpConnection
    ↓ receive bytes
SecureChannel
    ↓ verify → decrypt → decode
Session
    ↓ ReadResponse
Application
    ↓ DataValue
```

### Subscription Notification

```
[OPC UA Server] (data changed)
    ↓
TcpConnection (PublishResponse)
    ↓
SecureChannel (decrypt/decode)
    ↓
Session (process notifications)
    ↓
Subscription (route to monitored items)
    ↓
MonitoredItem (invoke callback)
    ↓
Application (handle value change)
```

## Design Patterns

### Builder Pattern
`ClientBuilder` uses fluent interface for configuration

### Facade Pattern
`ConnectedClient` provides simplified access to complex subsystems

### Strategy Pattern
`SecurityPolicyHandlerInterface` with different implementations (None, Basic256Sha256)

### Factory Pattern
`SecurityPolicyFactory` creates appropriate security handlers

### Observer Pattern
`Subscription` notifies registered callbacks on data changes

## Threading Model

**Single-threaded**: All operations run synchronously in calling thread.

**Async operations** (like `publishAsync`) are polling-based:
```php
while (true) {
    $subscription->publishAsync();  // Check for notifications
    usleep(100000);  // 100ms
}
```

**Future**: ReactPHP or Amp integration for true async I/O.

## Memory Management

- **PHP GC**: Automatic garbage collection
- **Explicit cleanup**: `disconnect()` closes sockets
- **Cache**: LRU eviction when size limit reached
- **No circular refs**: Careful design to avoid leaks

## Error Handling

### Exception Hierarchy

```
Throwable
  ├─ RuntimeException
  │   ├─ ConnectionException
  │   └─ TimeoutException
  └─ StatusCodeException (OPC UA status codes)
```

### Error Propagation

- Network errors → RuntimeException
- OPC UA errors → StatusCodeException
- Validation errors → InvalidArgumentException
- Configuration errors → RuntimeException

## Extension Points

### Custom Cache Implementation

```php
interface INodeCache {
    public function get(string $nodeId): ?NodeCacheEntry;
    public function set(string $nodeId, NodeCacheEntry $entry): void;
    // ...
}
```

### Custom Security Policy

```php
interface SecurityPolicyHandlerInterface {
    public function encrypt(string $data): string;
    public function decrypt(string $data): string;
    // ...
}
```

## Performance Considerations

### Caching Strategy
- LRU cache for browse results
- Configurable size and TTL
- Cache invalidation on disconnect

### Batch Operations
- Multiple nodes in single request
- Automatic splitting based on server limits
- Continuation point handling

### Connection Management
- Single connection per client
- Session reuse
- Automatic reconnection (planned)

## Security Architecture

### Layers of Security

1. **Transport**: TCP with optional TLS
2. **Application**: OPC UA SecureChannel
   - Asymmetric (RSA) for handshake
   - Symmetric (AES) for messages
3. **Authentication**: Username, Certificate, or Anonymous
4. **Authorization**: Handled by server

### Message Security

```
[Clear Message]
    ↓ sign (RSA/SHA-256)
[Signed Message]
    ↓ encrypt (AES-256-CBC)
[Encrypted Message]
    ↓ send over TCP
```

## Testing Strategy

### Unit Tests (`tests/Unit/`)
- Individual class testing
- Mock dependencies
- Focus on logic

### Integration Tests (`tests/Integration/`)
- Real server connection
- End-to-end scenarios
- Docker-based test server

## See Also

- [Transport Layer](transport.md)
- [Security Architecture](security.md)
- [Encoding System](encoding.md)
- [Extensibility](extensibility.md)
