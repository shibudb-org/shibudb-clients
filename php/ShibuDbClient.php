<?php
/**
 * ShibuDb PHP Client
 *
 * A comprehensive PHP client for ShibuDb database that supports:
 * - Authentication and user management
 * - Key-value operations
 * - Vector similarity search
 * - Space management
 * - Connection management
 *
 * Compatible with PHP 5.6+
 *
 * @package ShibuDb
 */

// ---------------------------------------------------------------------------
// Exceptions
// ---------------------------------------------------------------------------

class ShibuDbException extends Exception {}

class ShibuDbAuthenticationError extends ShibuDbException {}

class ShibuDbConnectionError extends ShibuDbException {}

class ShibuDbQueryError extends ShibuDbException {}

class ShibuDbPoolExhaustedError extends ShibuDbException {}

// ---------------------------------------------------------------------------
// ShibuDbClient
// ---------------------------------------------------------------------------

class ShibuDbClient
{
    /** @var string */
    protected $host = 'localhost';

    /** @var int */
    protected $port = 4444;

    /** @var int */
    protected $timeout = 30;

    /** @var resource|null */
    protected $socket = null;

    /** @var bool */
    protected $authenticated = false;

    /** @var array */
    protected $currentUser = array('username' => '', 'role' => '', 'permissions' => array());

    /** @var string|null */
    protected $currentSpace = null;

    /**
     * Initialize ShibuDb client
     *
     * @param string $host   Database server host
     * @param int    $port   Database server port
     * @param int    $timeout Connection timeout in seconds
     */
    public function __construct($host = 'localhost', $port = 4444, $timeout = 30)
    {
        $this->host = $host;
        $this->port = (int) $port;
        $this->timeout = (int) $timeout;
        $this->_connect();
    }

    /**
     * Establish connection to ShibuDb server
     */
    protected function _connect()
    {
        $errno = 0;
        $errstr = '';
        $address = 'tcp://' . $this->host . ':' . $this->port;

        $this->socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($this->socket === false) {
            throw new ShibuDbConnectionError(
                'Failed to connect to ShibuDb server: ' . ($errstr ? $errstr : 'Unknown error')
            );
        }

        stream_set_timeout($this->socket, $this->timeout);
    }

    /**
     * Send a query to the server and receive response
     *
     * @param array $query Query array to send
     * @return array Response array from server
     */
    protected function _sendQuery($query)
    {
        try {
            $queryJson = json_encode($query) . "\n";
            $written = fwrite($this->socket, $queryJson);

            if ($written === false || $written !== strlen($queryJson)) {
                throw new ShibuDbQueryError('Failed to send query');
            }

            $response = stream_get_line($this->socket, 4096, "\n");
            if ($response === false) {
                throw new ShibuDbQueryError('Failed to receive response');
            }

            $response = trim($response);
            $decoded = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return array('status' => 'OK', 'message' => $response);
            }

            return $decoded;

        } catch (ShibuDbException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ShibuDbQueryError('Failed to execute query: ' . $e->getMessage());
        }
    }

    /**
     * Authenticate with the ShibuDb server
     *
     * @param string $username Username for authentication
     * @param string $password Password for authentication
     * @return array Authentication response
     */
    public function authenticate($username, $password)
    {
        $loginQuery = array(
            'username' => $username,
            'password' => $password
        );

        $response = $this->_sendQuery($loginQuery);

        if (isset($response['status']) && $response['status'] === 'OK') {
            $this->authenticated = true;
            $userInfo = isset($response['user']) && is_array($response['user']) ? $response['user'] : array();
            $this->currentUser = array(
                'username' => isset($userInfo['username']) ? $userInfo['username'] : $username,
                'role' => isset($userInfo['role']) ? $userInfo['role'] : '',
                'permissions' => isset($userInfo['permissions']) && is_array($userInfo['permissions'])
                    ? $userInfo['permissions'] : array()
            );
        } else {
            $message = isset($response['message']) ? $response['message'] : 'Unknown error';
            throw new ShibuDbAuthenticationError('Authentication failed: ' . $message);
        }

        return $response;
    }

    /**
     * Switch to a specific space (table)
     *
     * @param string $spaceName Name of the space to use
     * @return array Response from server
     */
    public function useSpace($spaceName)
    {
        $query = array(
            'type' => 'USE_SPACE',
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : ''
        );

        $response = $this->_sendQuery($query);

        if (isset($response['status']) && $response['status'] === 'OK') {
            $this->currentSpace = $spaceName;
        }

        return $response;
    }

    /**
     * Create a new space
     *
     * @param string   $spaceName  Name of the space to create
     * @param string   $engineType Type of engine ("key-value" or "vector")
     * @param int|null $dimension  Vector dimension (required for vector spaces)
     * @param string   $indexType  Index type for vector spaces
     * @param string   $metric     Distance metric for vector spaces
     * @return array Response from server
     */
    public function createSpace($spaceName, $engineType = 'key-value', $dimension = null,
                               $indexType = 'Flat', $metric = 'L2')
    {
        $query = array(
            'type' => 'CREATE_SPACE',
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'engine_type' => $engineType,
            'index_type' => $indexType,
            'metric' => $metric
        );

        if ($dimension !== null) {
            $query['dimension'] = (int) $dimension;
        }

        return $this->_sendQuery($query);
    }

    /**
     * Delete a space
     *
     * @param string $spaceName Name of the space to delete
     * @return array Response from server
     */
    public function deleteSpace($spaceName)
    {
        $query = array(
            'type' => 'DELETE_SPACE',
            'data' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : ''
        );

        return $this->_sendQuery($query);
    }

    /**
     * List all available spaces
     *
     * @return array Response containing list of spaces
     */
    public function listSpaces()
    {
        $query = array(
            'type' => 'LIST_SPACES',
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : ''
        );

        return $this->_sendQuery($query);
    }

    /**
     * Resolve space name (current or parameter)
     *
     * @param string|null $space Space name or null
     * @return string Resolved space name
     * @throws ShibuDbQueryError
     */
    protected function _resolveSpace($space)
    {
        $spaceName = $space !== null ? $space : $this->currentSpace;
        if (empty($spaceName)) {
            throw new ShibuDbQueryError(
                'No space selected. Use useSpace() first or specify space parameter.'
            );
        }
        return $spaceName;
    }

    /**
     * Put a key-value pair
     *
     * @param string      $key   Key to store
     * @param string      $value Value to store
     * @param string|null $space Space name (uses current space if not specified)
     * @return array Response from server
     */
    public function put($key, $value, $space = null)
    {
        $spaceName = $this->_resolveSpace($space);

        $query = array(
            'type' => 'PUT',
            'key' => $key,
            'value' => $value,
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : ''
        );

        return $this->_sendQuery($query);
    }

    /**
     * Get a value by key
     *
     * @param string      $key   Key to retrieve
     * @param string|null $space Space name (uses current space if not specified)
     * @return array Response containing the value
     */
    public function get($key, $space = null)
    {
        $spaceName = $this->_resolveSpace($space);

        $query = array(
            'type' => 'GET',
            'key' => $key,
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : ''
        );

        return $this->_sendQuery($query);
    }

    /**
     * Delete a key-value pair
     *
     * @param string      $key   Key to delete
     * @param string|null $space Space name (uses current space if not specified)
     * @return array Response from server
     */
    public function delete($key, $space = null)
    {
        $spaceName = $this->_resolveSpace($space);

        $query = array(
            'type' => 'DELETE',
            'key' => $key,
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : ''
        );

        return $this->_sendQuery($query);
    }

    /**
     * Insert a vector into a vector space
     *
     * @param int         $vectorId ID for the vector
     * @param array       $vector   Array of float values representing the vector
     * @param string|null $space    Space name (uses current space if not specified)
     * @return array Response from server
     */
    public function insertVector($vectorId, $vector, $space = null)
    {
        $spaceName = $this->_resolveSpace($space);

        $vectorStr = implode(',', array_map('strval', $vector));

        $query = array(
            'type' => 'INSERT_VECTOR',
            'key' => (string) $vectorId,
            'value' => $vectorStr,
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : ''
        );

        return $this->_sendQuery($query);
    }

    /**
     * Search for top-k similar vectors
     *
     * @param array       $queryVector Query vector to search for
     * @param int         $k           Number of top results to return
     * @param string|null $space       Space name (uses current space if not specified)
     * @return array Response containing search results
     */
    public function searchTopk($queryVector, $k = 1, $space = null)
    {
        $spaceName = $this->_resolveSpace($space);

        $vectorStr = implode(',', array_map('strval', $queryVector));

        $query = array(
            'type' => 'SEARCH_TOPK',
            'value' => $vectorStr,
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'dimension' => (int) $k
        );

        return $this->_sendQuery($query);
    }

    /**
     * Search for vectors within a radius
     *
     * @param array       $queryVector Query vector to search for
     * @param float       $radius      Search radius
     * @param string|null $space       Space name (uses current space if not specified)
     * @return array Response containing search results
     */
    public function rangeSearch($queryVector, $radius, $space = null)
    {
        $spaceName = $this->_resolveSpace($space);

        $vectorStr = implode(',', array_map('strval', $queryVector));

        $query = array(
            'type' => 'RANGE_SEARCH',
            'value' => $vectorStr,
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'radius' => (float) $radius
        );

        return $this->_sendQuery($query);
    }

    /**
     * Get a vector by ID
     *
     * @param int         $vectorId ID of the vector to retrieve
     * @param string|null $space    Space name (uses current space if not specified)
     * @return array Response containing the vector
     */
    public function getVector($vectorId, $space = null)
    {
        $spaceName = $this->_resolveSpace($space);

        $query = array(
            'type' => 'GET_VECTOR',
            'key' => (string) $vectorId,
            'space' => $spaceName,
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : ''
        );

        return $this->_sendQuery($query);
    }

    /**
     * Create a new user (admin only)
     *
     * @param array $user User array with keys: username, password, role, permissions
     * @return array Response from server
     */
    public function createUser($user)
    {
        $query = array(
            'type' => 'CREATE_USER',
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'new_user' => array(
                'username' => isset($user['username']) ? $user['username'] : '',
                'password' => isset($user['password']) ? $user['password'] : '',
                'role' => isset($user['role']) ? $user['role'] : 'user',
                'permissions' => isset($user['permissions']) && is_array($user['permissions'])
                    ? $user['permissions'] : array()
            )
        );

        return $this->_sendQuery($query);
    }

    /**
     * Update user password (admin only)
     *
     * @param string $username     Username to update
     * @param string $newPassword  New password
     * @return array Response from server
     */
    public function updateUserPassword($username, $newPassword)
    {
        $query = array(
            'type' => 'UPDATE_USER_PASSWORD',
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'new_user' => array('username' => $username, 'password' => $newPassword)
        );

        return $this->_sendQuery($query);
    }

    /**
     * Update user role (admin only)
     *
     * @param string $username Username to update
     * @param string $newRole  New role
     * @return array Response from server
     */
    public function updateUserRole($username, $newRole)
    {
        $query = array(
            'type' => 'UPDATE_USER_ROLE',
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'new_user' => array('username' => $username, 'role' => $newRole)
        );

        return $this->_sendQuery($query);
    }

    /**
     * Update user permissions (admin only)
     *
     * @param string $username     Username to update
     * @param array  $permissions  New permissions array
     * @return array Response from server
     */
    public function updateUserPermissions($username, $permissions)
    {
        $query = array(
            'type' => 'UPDATE_USER_PERMISSIONS',
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'new_user' => array('username' => $username, 'permissions' => $permissions)
        );

        return $this->_sendQuery($query);
    }

    /**
     * Delete a user (admin only)
     *
     * @param string $username Username to delete
     * @return array Response from server
     */
    public function deleteUser($username)
    {
        $query = array(
            'type' => 'DELETE_USER',
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'delete_user' => array('username' => $username)
        );

        return $this->_sendQuery($query);
    }

    /**
     * Get user information (admin only)
     *
     * @param string $username Username to get information for
     * @return array Response containing user information
     */
    public function getUser($username)
    {
        $query = array(
            'type' => 'GET_USER',
            'user' => isset($this->currentUser['username']) ? $this->currentUser['username'] : '',
            'data' => $username
        );

        return $this->_sendQuery($query);
    }

    /**
     * Close the connection to the server
     */
    public function close()
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->close();
    }
}

// ---------------------------------------------------------------------------
// Connection Pool (simplified for PHP - no background threading)
// ---------------------------------------------------------------------------

class ShibuDbConnectionPool
{
    /** @var string */
    protected $host = 'localhost';

    /** @var int */
    protected $port = 4444;

    /** @var int */
    protected $timeout = 30;

    /** @var string|null */
    protected $username = null;

    /** @var string|null */
    protected $password = null;

    /** @var int */
    protected $minSize = 2;

    /** @var int */
    protected $maxSize = 10;

    /** @var int */
    protected $acquireTimeout = 30;

    /** @var array */
    protected $pool = array();

    /** @var int */
    protected $activeConnections = 0;

    /** @var bool */
    protected $shutdown = false;

    /**
     * Initialize connection pool
     *
     * @param string $host                  Database server host
     * @param int    $port                  Database server port
     * @param string|null $username         Username for authentication
     * @param string|null $password         Password for authentication
     * @param int    $timeout               Connection timeout
     * @param int    $minSize               Minimum connections in pool
     * @param int    $maxSize               Maximum connections in pool
     * @param int    $acquireTimeout        Timeout for acquiring connection
     */
    public function __construct($host = 'localhost', $port = 4444, $username = null, $password = null,
                               $timeout = 30, $minSize = 2, $maxSize = 10, $acquireTimeout = 30)
    {
        $this->host = $host;
        $this->port = (int) $port;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = (int) $timeout;
        $this->minSize = (int) $minSize;
        $this->maxSize = (int) $maxSize;
        $this->acquireTimeout = (int) $acquireTimeout;

        for ($i = 0; $i < $this->minSize; $i++) {
            $conn = $this->_createConnection();
            if ($conn !== null) {
                $this->pool[] = $conn;
                $this->activeConnections++;
            }
        }
    }

    /**
     * Create a new database connection
     *
     * @return ShibuDbClient|null
     */
    protected function _createConnection()
    {
        try {
            $client = new ShibuDbClient($this->host, $this->port, $this->timeout);

            if ($this->username !== null && $this->password !== null) {
                $client->authenticate($this->username, $this->password);
            }

            return $client;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get a connection from the pool
     *
     * @return ShibuDbClient
     * @throws ShibuDbPoolExhaustedError
     */
    public function getConnection()
    {
        if ($this->shutdown) {
            throw new ShibuDbPoolExhaustedError('Connection pool is closed');
        }

        $connection = null;

        if (!empty($this->pool)) {
            $connection = array_shift($this->pool);
        } elseif ($this->activeConnections < $this->maxSize) {
            $connection = $this->_createConnection();
            if ($connection !== null) {
                $this->activeConnections++;
            }
        }

        if ($connection === null) {
            throw new ShibuDbPoolExhaustedError('Connection pool exhausted');
        }

        try {
            $connection->listSpaces();
        } catch (Exception $e) {
            $connection->close();
            $this->activeConnections--;
            return $this->getConnection();
        }

        return $connection;
    }

    /**
     * Return a connection to the pool
     *
     * @param ShibuDbClient $connection Connection to return
     */
    public function releaseConnection($connection)
    {
        if ($this->shutdown) {
            $connection->close();
            $this->activeConnections--;
            return;
        }

        try {
            $connection->listSpaces();
            if (count($this->pool) < $this->maxSize) {
                $this->pool[] = $connection;
            } else {
                $connection->close();
                $this->activeConnections--;
            }
        } catch (Exception $e) {
            $connection->close();
            $this->activeConnections--;
        }
    }

    /**
     * Close all connections in the pool
     */
    public function close()
    {
        $this->shutdown = true;

        foreach ($this->pool as $connection) {
            $connection->close();
        }
        $this->pool = array();
        $this->activeConnections = 0;
    }

    /**
     * Get pool statistics
     *
     * @return array
     */
    public function getStats()
    {
        return array(
            'pool_size' => count($this->pool),
            'active_connections' => $this->activeConnections,
            'min_size' => $this->minSize,
            'max_size' => $this->maxSize,
            'shutdown' => $this->shutdown
        );
    }
}

// ---------------------------------------------------------------------------
// Convenience Functions
// ---------------------------------------------------------------------------

/**
 * Create and optionally authenticate a ShibuDb client
 *
 * @param string      $host    Database server host
 * @param int         $port    Database server port
 * @param string|null $username Username for authentication
 * @param string|null $password Password for authentication
 * @param int         $timeout Connection timeout in seconds
 * @return ShibuDbClient
 */
function shibudb_connect($host = 'localhost', $port = 4444, $username = null,
                        $password = null, $timeout = 30)
{
    $client = new ShibuDbClient($host, $port, $timeout);

    if ($username !== null && $password !== null) {
        $client->authenticate($username, $password);
    }

    return $client;
}
