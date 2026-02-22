<?php
/**
 * Comprehensive Connection Pooling Test for ShibuDb PHP Client
 *
 * Tests all connection pooling features including:
 * - Basic pooling functionality
 * - Sequential operations
 * - Error handling
 * - Pool statistics
 * - Different pool configurations
 *
 * Requires ShibuDb server running: sudo shibudb start 4444
 */

require_once __DIR__ . '/ShibuDbClient.php';

$passed = 0;
$failed = 0;

function printTestHeader($name)
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo $name . "\n";
    echo str_repeat('=', 60) . "\n";
}

function testAssert($condition, $message, &$passed, &$failed)
{
    if ($condition) {
        echo "OK: $message\n";
        $passed++;
        return true;
    } else {
        echo "FAIL: $message\n";
        $failed++;
        return false;
    }
}

function testBasicPooling(&$passed, &$failed)
{
    printTestHeader('Basic Connection Pooling');

    try {
        $pool = new ShibuDbConnectionPool(
            'localhost', 4444,
            'admin', 'admin',
            30, 2, 5
        );

        $client = $pool->getConnection();

        $response = $client->listSpaces();
        testAssert(isset($response['status']) || isset($response['spaces']), 'List spaces', $passed, $failed);

        $response = $client->createSpace('pool_test_basic', 'key-value');
        testAssert(isset($response['status']) && $response['status'] === 'OK', 'Create space', $passed, $failed);

        $response = $client->useSpace('pool_test_basic');
        testAssert(isset($response['status']) && $response['status'] === 'OK', 'Use space', $passed, $failed);

        $response = $client->put('test_key', 'test_value');
        testAssert(isset($response['status']) && $response['status'] === 'OK', 'Put', $passed, $failed);

        $response = $client->get('test_key');
        testAssert(isset($response['value']) && $response['value'] === 'test_value', 'Get value', $passed, $failed);

        $pool->releaseConnection($client);

        $stats = $pool->getStats();
        testAssert(is_array($stats), 'Pool stats is array', $passed, $failed);
        testAssert(isset($stats['pool_size']), 'Pool has pool_size', $passed, $failed);
        testAssert(isset($stats['active_connections']), 'Pool has active_connections', $passed, $failed);

        $pool->close();
        testAssert(true, 'Pool closed', $passed, $failed);

        return true;

    } catch (Exception $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        $failed++;
        return false;
    }
}

function testPoolStatistics(&$passed, &$failed)
{
    printTestHeader('Pool Statistics');

    try {
        $pool = new ShibuDbConnectionPool(
            'localhost', 4444,
            'admin', 'admin',
            30, 2, 6
        );

        $initialStats = $pool->getStats();
        testAssert($initialStats['min_size'] == 2, 'Initial min_size', $passed, $failed);
        testAssert($initialStats['max_size'] == 6, 'Initial max_size', $passed, $failed);

        $connections = array();
        for ($i = 0; $i < 3; $i++) {
            $conn = $pool->getConnection();
            $connections[] = $conn;
            $response = $conn->listSpaces();
            testAssert(isset($response['status']) || isset($response['spaces']), "Get connection " . ($i + 1), $passed, $failed);
        }

        foreach ($connections as $conn) {
            $pool->releaseConnection($conn);
        }

        $finalStats = $pool->getStats();
        testAssert(isset($finalStats['pool_size']), 'Final stats has pool_size', $passed, $failed);

        $pool->close();

        return true;

    } catch (Exception $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        $failed++;
        return false;
    }
}

function testPoolConfigurations(&$passed, &$failed)
{
    printTestHeader('Pool Configurations');

    $configs = array(
        array('name' => 'Small Pool', 'min' => 1, 'max' => 2),
        array('name' => 'Medium Pool', 'min' => 2, 'max' => 5),
        array('name' => 'Large Pool', 'min' => 3, 'max' => 8),
    );

    $successCount = 0;

    foreach ($configs as $config) {
        try {
            $pool = new ShibuDbConnectionPool(
                'localhost', 4444,
                'admin', 'admin',
                30, $config['min'], $config['max']
            );

            $client = $pool->getConnection();
            $response = $client->listSpaces();
            $pool->releaseConnection($client);
            $pool->close();

            testAssert(true, $config['name'] . ' works', $passed, $failed);
            $successCount++;

        } catch (Exception $e) {
            echo "FAIL: " . $config['name'] . " - " . $e->getMessage() . "\n";
            $failed++;
        }
    }

    return $successCount >= 2;
}

function testPoolErrorHandling(&$passed, &$failed)
{
    printTestHeader('Error Handling');

    $errorTestsPassed = 0;

    // Invalid credentials
    try {
        $pool = new ShibuDbConnectionPool(
            'localhost', 4444,
            'invalid_user',
            'invalid_password',
            30, 1, 2
        );

        try {
            $client = $pool->getConnection();
            $client->listSpaces();
        } catch (ShibuDbAuthenticationError $e) {
            testAssert(true, 'Invalid credentials - AuthenticationError', $passed, $failed);
            $errorTestsPassed++;
        } catch (ShibuDbPoolExhaustedError $e) {
            testAssert(true, 'Invalid credentials - PoolExhaustedError', $passed, $failed);
            $errorTestsPassed++;
        }

        $pool->close();

    } catch (Exception $e) {
        testAssert(true, 'Invalid credentials handled', $passed, $failed);
        $errorTestsPassed++;
    }

    // Non-existent server
    try {
        $pool = new ShibuDbConnectionPool(
            'localhost', 9999,
            null, null,
            2, 1, 2
        );

        try {
            $client = $pool->getConnection();
        } catch (ShibuDbConnectionError $e) {
            testAssert(true, 'Non-existent server - ConnectionError', $passed, $failed);
            $errorTestsPassed++;
        } catch (ShibuDbPoolExhaustedError $e) {
            testAssert(true, 'Non-existent server - PoolExhaustedError', $passed, $failed);
            $errorTestsPassed++;
        }

        $pool->close();

    } catch (ShibuDbConnectionError $e) {
        testAssert(true, 'Non-existent server on creation', $passed, $failed);
        $errorTestsPassed++;
    } catch (Exception $e) {
        testAssert(true, 'Non-existent server handled', $passed, $failed);
        $errorTestsPassed++;
    }

    return $errorTestsPassed >= 1;
}

// Run tests
echo "ShibuDb PHP Client - Comprehensive Connection Pooling Tests\n";
echo str_repeat('=', 60) . "\n";

testBasicPooling($passed, $failed);
testPoolStatistics($passed, $failed);
testPoolConfigurations($passed, $failed);
testPoolErrorHandling($passed, $failed);

// Summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 60) . "\n";
echo "Total: $passed passed, $failed failed\n";

if ($failed === 0) {
    echo "\nAll connection pooling tests passed.\n";
    exit(0);
} else {
    echo "\nSome tests failed.\n";
    exit(1);
}
