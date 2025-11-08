# Contributing to PHP OPC UA Client

Thank you for your interest in contributing! This document provides guidelines and information for contributors.

## About This Project

This library is a **PHP implementation** of OPC UA client functionality based on the architecture and design of the [OPC Foundation UA-.NETStandard](https://github.com/OPCFoundation/UA-.NETStandard) implementation. When contributing, please keep in mind:

- This is an **independent implementation** written in PHP, not a translation of C# code
- We follow the same **architectural patterns** as UA-.NETStandard (layered design, service structure)
- We maintain **wire-protocol compatibility** with OPC UA servers
- **Client-only focus** (no server implementation)
- New features should align with OPC UA client capabilities and, where applicable, with patterns from UA-.NETStandard
- Bug fixes should consider the OPC UA specification and may cross-reference the UA-.NETStandard implementation
- PHP-specific optimizations and idioms are welcome and encouraged

**Key Implementation Principles:**
- Hand-written message classes (not generated)
- Modern PHP 8.4+ features (readonly classes, union types, match expressions)
- Type safety throughout
- Focus on core client operations (Browse, Read, Write, Subscribe, Call)

See [NOTICE](NOTICE) for complete details about the relationship between this implementation and the original UA-.NETStandard project.

## Code of Conduct

This project adheres to professional standards of collaboration:
- Be respectful and constructive
- Focus on technical merit
- Welcome diverse perspectives
- Help maintain a productive environment

## How to Contribute

### Reporting Bugs

1. **Check existing issues** - Search for similar problems
2. **Create a detailed report** including:
   - PHP version and OS
   - Library version
   - Minimal reproducible example
   - Expected vs actual behavior
   - Full error messages/stack traces

### Suggesting Features

1. **Check the roadmap** - Feature may already be planned
2. **Open a discussion** - Describe use case and benefits
3. **Consider implementation** - Willing to contribute code?

### Pull Requests

1. **Fork and clone** the repository
2. **Create a feature branch** (`feature/my-feature`)
3. **Follow development guidelines** (see below)
4. **Write tests** for new functionality
5. **Update documentation** as needed
6. **Submit PR** with clear description

## Development Setup

### Prerequisites

```bash
# PHP 8.4 or higher
php -v

# Composer
composer --version

# Clone repository
git clone https://github.com/TECHDOCK-CH/php-opc-ua.git
cd php-opc-ua

# Install dependencies
composer install
```

### Running Tests

```bash
# All tests
composer test

# With coverage
composer test-coverage

# Single test
vendor/bin/phpunit --filter TestClassName
```

### Code Quality

```bash
# Static analysis (PHPStan level 9)
composer analyse

# Coding standards check
composer cs-check

# Auto-fix coding standards
composer cs-fix

# Run all quality checks
composer all
```

## Development Guidelines

### Code Style

- **PHP 8.4 strict types** - All files must use `declare(strict_types=1))`
- **Named arguments** - Use for improved readability
- **Final classes** - Use `final` by default unless extension is intended
- **Type hints** - Always use parameter and return types
- **Readonly properties** - Use when appropriate for immutability

### Architecture Principles

- **Single Responsibility** - One class, one purpose
- **Composition over inheritance** - Prefer dependency injection
- **Interface-based design** - Program to interfaces
- **Explicit over implicit** - Clear data flow
- **Fail fast** - Validate early, throw descriptive exceptions

### Testing Requirements

- **Test behavior, not implementation**
- **One concept per test**
- **Clear test names** - Describe the scenario
- **Arrange-Act-Assert** pattern
- **No test interdependencies**

Example:

```php
public function test_read_returns_value_for_valid_node(): void
{
    // Arrange
    $client = $this->createTestClient();
    $nodeId = NodeId::numeric(0, 2258);

    // Act
    $result = $client->session->read($nodeId);

    // Assert
    $this->assertNotNull($result->value);
}
```

### Documentation Requirements

- **PHPDoc blocks** for all public methods
- **Parameter descriptions** with types
- **Return value documentation**
- **Exception documentation** with `@throws`
- **Usage examples** for complex APIs

Example:

```php
/**
 * Read a single node value from the server
 *
 * @param NodeId $nodeId The node to read
 * @param TimestampsToReturn $timestamps Which timestamps to return
 * @return DataValue The node value with metadata
 * @throws RuntimeException If the operation fails
 * @throws StatusCodeException If the server returns an error
 */
public function read(
    NodeId $nodeId,
    TimestampsToReturn $timestamps = TimestampsToReturn::Both,
): DataValue
```

### Commit Messages

Follow conventional commits format:

```
<type>(<scope>): <subject>

<body>

<footer>
```

Types:
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation only
- `style:` - Code style (formatting, etc)
- `refactor:` - Code restructuring
- `test:` - Adding/updating tests
- `chore:` - Maintenance tasks

Example:

```
feat(client): add automatic endpoint discovery

Implemented ClientBuilder.withAutoDiscovery() to automatically
fetch and select the best endpoint based on security preferences.

Closes #123
```

### Git Workflow

1. **Branch from main** - Keep branches short-lived
2. **Commit frequently** - Small, logical commits
3. **Push regularly** - Don't lose work
4. **Rebase before PR** - Clean history
5. **Squash if needed** - Consolidate related commits

### Before Submitting PR

- [ ] All tests pass (`composer test`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] Code style is correct (`composer cs-check`)
- [ ] New features have tests
- [ ] Documentation is updated
- [ ] CHANGELOG.md is updated (for significant changes)
- [ ] Commit messages follow conventions

## Project Structure

```
php-opc-ua/
├── src/
│   ├── Client/          # High-level client API
│   ├── Core/            # Protocol implementation
│   │   ├── Encoding/    # Binary encoding/decoding
│   │   ├── Messages/    # Service messages
│   │   ├── Security/    # Secure channel & crypto
│   │   ├── Transport/   # TCP connection
│   │   └── Types/       # OPC UA data types
│   └── Exceptions/      # Custom exceptions
├── tests/
│   ├── Unit/            # Unit tests
│   └── Integration/     # Integration tests
├── examples/            # Usage examples
└── docs/                # Documentation
```

## Adding New Features

### New OPC UA Service

1. Define message types in `Core/Messages/`
2. Implement encoding/decoding
3. Add service method to `Session`
4. Write unit tests
5. Add integration test
6. Create example
7. Document in appropriate guide

### New Data Type

1. Define type in `Core/Types/`
2. Implement `IEncodeable` interface
3. Add encoding logic in `BinaryEncoder`
4. Add decoding logic in `BinaryDecoder`
5. Write comprehensive tests
6. Update type system documentation

## Performance Considerations

- **Avoid unnecessary allocations** - Reuse objects when safe
- **Batch operations** - Combine multiple requests
- **Use caching** - For frequently accessed data
- **Profile first** - Don't optimize prematurely

## Security Considerations

- **Validate inputs** - Never trust external data
- **Handle secrets carefully** - No logging of sensitive data
- **Certificate validation** - Always validate by default
- **Secure defaults** - Fail towards security

## Questions?

- **GitHub Discussions** - For general questions
- **GitHub Issues** - For specific problems
- **Email** - info@techdock.ch for private inquiries

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
