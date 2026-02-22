<?php
/**
 * ShibuDb PHP Client - Example Usage
 *
 * Requires ShibuDb server running: sudo shibudb start 4444
 */

require_once __DIR__ . '/ShibuDbClient.php';

// Simple test of basic operations
function simpleTest()
{
    echo "Simple ShibuDb Test\n";
    echo str_repeat('=', 40) . "\n";

    try {
        $client = new ShibuDbClient('localhost', 4444);
        $response = $client->authenticate('admin', 'admin');
        echo "Authentication: " . (isset($response['status']) ? $response['status'] : 'N/A') . "\n";

        $response = $client->createSpace('testspace', 'key-value');
        echo "Create Space: " . (isset($response['status']) ? $response['status'] : 'N/A') . "\n";

        $response = $client->useSpace('testspace');
        echo "Use Space: " . (isset($response['status']) ? $response['status'] : 'N/A') . "\n";

        $response = $client->put('hello', 'world');
        echo "Put: " . (isset($response['status']) ? $response['status'] : 'N/A') . "\n";

        $response = $client->get('hello');
        $value = isset($response['value']) ? $response['value'] : 'N/A';
        echo "Get: " . (isset($response['status']) ? $response['status'] : 'N/A') . " - Value: $value\n";

        $response = $client->listSpaces();
        $spaces = isset($response['spaces']) ? $response['spaces'] : array();
        echo "List Spaces: " . print_r($spaces, true) . "\n";

        $client->close();
        echo "Test completed successfully!\n";

    } catch (ShibuDbAuthenticationError $e) {
        echo "Authentication failed: " . $e->getMessage() . "\n";
    } catch (ShibuDbConnectionError $e) {
        echo "Connection failed: " . $e->getMessage() . "\n";
    } catch (ShibuDbQueryError $e) {
        echo "Query failed: " . $e->getMessage() . "\n";
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
    }
}

// Test vector operations
function vectorTest()
{
    echo "\nVector Test\n";
    echo str_repeat('=', 40) . "\n";

    try {
        $client = new ShibuDbClient('localhost', 4444);
        $client->authenticate('admin', 'admin');

        $client->createSpace('vectest', 'vector', 3);
        $client->useSpace('vectest');

        $vectors = array(
            array(1, array(1.0, 2.0, 3.0)),
            array(2, array(4.0, 5.0, 6.0)),
            array(3, array(1.1, 2.1, 3.1))
        );

        foreach ($vectors as $item) {
            $response = $client->insertVector($item[0], $item[1]);
            echo "Insert Vector {$item[0]}: " . (isset($response['status']) ? $response['status'] : 'N/A') . "\n";
        }

        $queryVector = array(1.0, 2.0, 3.0);
        $response = $client->searchTopk($queryVector, 2);
        $message = isset($response['message']) ? $response['message'] : print_r($response, true);
        echo "Search: $message\n";

        $client->close();
        echo "Vector test completed successfully!\n";

    } catch (Exception $e) {
        echo "Vector test failed: " . $e->getMessage() . "\n";
    }
}

// Test connection pool
function poolTest()
{
    echo "\nConnection Pool Test\n";
    echo str_repeat('=', 40) . "\n";

    try {
        $pool = new ShibuDbConnectionPool(
            'localhost', 4444,
            'admin', 'admin',
            30, 2, 10, 30
        );

        $client = $pool->getConnection();
        $response = $client->listSpaces();
        echo "List Spaces via pool: " . print_r($response, true) . "\n";

        $pool->releaseConnection($client);

        $stats = $pool->getStats();
        echo "Pool stats: " . print_r($stats, true) . "\n";

        $pool->close();
        echo "Pool test completed successfully!\n";

    } catch (Exception $e) {
        echo "Pool test failed: " . $e->getMessage() . "\n";
    }
}

// Run examples
simpleTest();
vectorTest();
poolTest();
