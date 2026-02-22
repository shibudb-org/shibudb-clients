# ShibuDb PHP Client

A comprehensive PHP client for ShibuDb database with support for **PHP 5.6+**. Feature-parity with the Python client including authentication, key-value operations, vector similarity search, space management, and connection pooling.

## Requirements

- **PHP 5.6+**
- ShibuDb server running (e.g., `sudo shibudb start 4444`)
- No external PHP extensions required (uses built-in `stream_socket_client`, `json_encode`, `json_decode`)

## Features

- Authentication & user management
- Key-value operations (PUT, GET, DELETE)
- Vector similarity search (insert, search top-k, range search)
- Space management (create, delete, list, use)
- Connection pooling
- Custom exceptions for error handling

## Installation

### Composer (recommended)

```bash
composer require podcopic-labs/shibudb-client-php
```

Then in your PHP code:

```php
<?php
require_once 'vendor/autoload.php';

$client = new ShibuDbClient('localhost', 4444);
$client->authenticate('admin', 'admin');
```

### Manual

1. Copy `ShibuDbClient.php` to your project:
   ```bash
   cp ShibuDbClient.php /path/to/your/project/
   ```

2. Include the client:
   ```php
   require_once '/path/to/ShibuDbClient.php';
   ```

## Quick Start

### Basic Connection and Authentication

```php
<?php
require_once 'ShibuDbClient.php';

// Create client and authenticate
$client = new ShibuDbClient('localhost', 4444);
$client->authenticate('admin', 'admin');

// Your operations here
$response = $client->listSpaces();
print_r($response);

$client->close();
```

### Convenience Function

```php
$client = shibudb_connect('localhost', 4444, 'admin', 'admin');
$response = $client->listSpaces();
$client->close();
```

### Key-Value Operations

```php
$client = new ShibuDbClient('localhost', 4444);
$client->authenticate('admin', 'admin');

// Create and use a space
$client->createSpace('mytable', 'key-value');
$client->useSpace('mytable');

// Basic operations
$client->put('name', 'John Doe');
$response = $client->get('name');
echo $response['value'];  // "John Doe"

$client->delete('name');
$client->close();
```

### Vector Operations

```php
$client->createSpace('vectors', 'vector', 128, 'Flat', 'L2');
$client->useSpace('vectors');

// Insert vectors
$client->insertVector(1, array(0.1, 0.2, 0.3, /* ... */));
$client->insertVector(2, array(0.4, 0.5, 0.6, /* ... */));

// Search for similar vectors
$results = $client->searchTopk(array(0.1, 0.2, 0.3, /* ... */), 5);
echo $results['message'];

// Range search
$results = $client->rangeSearch(array(0.1, 0.2, 0.3, /* ... */), 0.5);
$client->close();
```

### Connection Pooling

```php
$pool = new ShibuDbConnectionPool(
    'localhost', 4444,   // host, port
    'admin', 'admin',    // username, password
    30, 2, 10, 30        // timeout, min_size, max_size, acquire_timeout
);

$client = $pool->getConnection();
$response = $client->listSpaces();
$pool->releaseConnection($client);

$stats = $pool->getStats();
$pool->close();
```

## API Reference

### ShibuDbClient

| Method | Description |
|--------|-------------|
| `authenticate($username, $password)` | Authenticate with server |
| `useSpace($spaceName)` | Switch to a space |
| `createSpace($name, $engineType, $dimension, $indexType, $metric)` | Create a space |
| `deleteSpace($name)` | Delete a space |
| `listSpaces()` | List all spaces |
| `put($key, $value, $space)` | Store key-value |
| `get($key, $space)` | Retrieve by key |
| `delete($key, $space)` | Delete key |
| `insertVector($id, $vector, $space)` | Insert vector |
| `searchTopk($queryVector, $k, $space)` | Top-k similarity search |
| `rangeSearch($queryVector, $radius, $space)` | Range search |
| `getVector($id, $space)` | Get vector by ID |
| `createUser($user)` | Create user (admin) |
| `updateUserPassword($username, $newPassword)` | Update password (admin) |
| `updateUserRole($username, $newRole)` | Update role (admin) |
| `updateUserPermissions($username, $permissions)` | Update permissions (admin) |
| `deleteUser($username)` | Delete user (admin) |
| `getUser($username)` | Get user (admin) |
| `close()` | Close connection |

### Exceptions

- `ShibuDbException` - Base exception
- `ShibuDbAuthenticationError` - Authentication failed
- `ShibuDbConnectionError` - Connection failed
- `ShibuDbQueryError` - Query execution failed
- `ShibuDbPoolExhaustedError` - Connection pool exhausted

### PHP 5.6 Compatibility

This client avoids PHP 7+ features:
- No scalar type hints
- No return type declarations
- No null coalescing operator (`??`)
- Uses `array()` instead of short array syntax where clarity matters
- Compatible with `json_decode()` strictness in PHP 5.6

## Running Examples

```bash
php example.php
```

## Running Tests

Tests mirror the Python client test structure:

| Test File | Description | Server Required |
|-----------|-------------|-----------------|
| `simple_test.php` | Basic key-value and vector operations | Yes |
| `pool_test.php` | Connection pooling basics | Yes |
| `comprehensive_pool_test.php` | Full pool tests (stats, configs, errors) | Yes |
| `json_parsing_test.php` | JSON parsing with special chars, fallback | No |
| `special_chars_test.php` | Special character handling, encoding | No |

```bash
# Tests that require ShibuDb server (sudo shibudb start 4444)
php simple_test.php
php pool_test.php
php comprehensive_pool_test.php

# Tests that run without server
php json_parsing_test.php
php special_chars_test.php
```

## Troubleshooting

1. **Connection Failed**: Ensure ShibuDb server is running: `sudo shibudb start 4444`
2. **Authentication Failed**: Verify username and password
3. **Space Not Found**: Use `listSpaces()` and create with `createSpace()` before use
