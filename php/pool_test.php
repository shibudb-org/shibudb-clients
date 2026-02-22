<?php
/**
 * Simple test script for ShibuDb PHP connection pooling functionality
 *
 * Requires ShibuDb server running: sudo shibudb start 4444
 */

require_once __DIR__ . '/ShibuDbClient.php';

$passed = 0;
$failed = 0;

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
    echo "Testing basic connection pooling...\n";

    try {
        $pool = new ShibuDbConnectionPool(
            'localhost', 4444,
            'admin', 'admin',
            30, 1, 3
        );

        $client = $pool->getConnection();

        $response = $client->listSpaces();
        testAssert(
            isset($response['status']) || isset($response['spaces']),
            'List spaces via pool',
            $passed,
            $failed
        );

        $response = $client->createSpace('pool_test', 'key-value');
        testAssert(
            isset($response['status']) && ($response['status'] === 'OK' || $response['status'] === 'ERROR'),
            'Create space via pool',
            $passed,
            $failed
        );

        $response = $client->useSpace('pool_test');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Use space via pool',
            $passed,
            $failed
        );

        $response = $client->put('test_key', 'test_value');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Put via pool',
            $passed,
            $failed
        );

        $response = $client->get('test_key');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Get via pool',
            $passed,
            $failed
        );
        testAssert(
            isset($response['value']) && $response['value'] === 'test_value',
            'Get value matches',
            $passed,
            $failed
        );

        $pool->releaseConnection($client);

        $stats = $pool->getStats();
        testAssert(
            is_array($stats) && isset($stats['pool_size']),
            'Pool statistics',
            $passed,
            $failed
        );

        $pool->close();
        testAssert(true, 'Pool closed', $passed, $failed);

        return true;

    } catch (Exception $e) {
        echo "FAIL: Basic pooling test failed: " . $e->getMessage() . "\n";
        $failed++;
        return false;
    }
}

function testSequentialPoolUsage(&$passed, &$failed)
{
    echo "\nTesting sequential pool usage...\n";

    try {
        $pool = new ShibuDbConnectionPool(
            'localhost', 4444,
            'admin', 'admin',
            30, 2, 5
        );

        for ($i = 0; $i < 3; $i++) {
            $client = $pool->getConnection();
            $response = $client->listSpaces();
            testAssert(
                isset($response['status']) || isset($response['spaces']),
                "Sequential get $i",
                $passed,
                $failed
            );
            $pool->releaseConnection($client);
        }

        $pool->close();
        testAssert(true, 'Sequential pool test complete', $passed, $failed);

        return true;

    } catch (Exception $e) {
        echo "FAIL: Sequential pool test failed: " . $e->getMessage() . "\n";
        $failed++;
        return false;
    }
}

function testPoolErrorHandling(&$passed, &$failed)
{
    echo "\nTesting error handling...\n";

    // Test with invalid credentials - pool creation may succeed but getConnection may fail
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
            echo "FAIL: Expected authentication error\n";
            $failed++;
        } catch (ShibuDbAuthenticationError $e) {
            testAssert(true, 'Invalid credentials handled', $passed, $failed);
        } catch (ShibuDbPoolExhaustedError $e) {
            testAssert(true, 'Pool exhausted with invalid credentials', $passed, $failed);
        } catch (Exception $e) {
            testAssert(true, 'Error caught: ' . get_class($e), $passed, $failed);
        }

        $pool->close();

    } catch (Exception $e) {
        echo "OK: Pool with invalid credentials failed as expected\n";
        $passed++;
    }

    // Test with non-existent server
    try {
        $pool = new ShibuDbConnectionPool(
            'localhost', 9999,
            null, null,
            2, 1, 2
        );

        try {
            $client = $pool->getConnection();
            echo "FAIL: Expected connection error\n";
            $failed++;
        } catch (ShibuDbConnectionError $e) {
            testAssert(true, 'Non-existent server handled', $passed, $failed);
        } catch (ShibuDbPoolExhaustedError $e) {
            testAssert(true, 'Pool exhausted (connection failed)', $passed, $failed);
        } catch (Exception $e) {
            testAssert(true, 'Error caught', $passed, $failed);
        }

        $pool->close();

    } catch (ShibuDbConnectionError $e) {
        testAssert(true, 'Connection error on pool creation', $passed, $failed);
    } catch (Exception $e) {
        echo "OK: Non-existent server handled\n";
        $passed++;
    }
}

// Run tests
echo "ShibuDb PHP Connection Pooling Tests\n";
echo str_repeat('=', 50) . "\n";

testBasicPooling($passed, $failed);
testSequentialPoolUsage($passed, $failed);
testPoolErrorHandling($passed, $failed);

// Summary
echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: $passed passed, $failed failed\n";

if ($failed === 0) {
    echo "All connection pooling tests passed.\n";
    exit(0);
} else {
    echo "Some tests failed.\n";
    exit(1);
}
