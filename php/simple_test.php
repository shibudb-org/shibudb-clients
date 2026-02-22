<?php
/**
 * Simple ShibuDb PHP Client Test
 *
 * A simple script to test basic ShibuDb operations.
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

function simpleTest(&$passed, &$failed)
{
    echo "Simple ShibuDb Test\n";
    echo str_repeat('=', 40) . "\n";

    try {
        $client = new ShibuDbClient('localhost', 4444);
        $response = $client->authenticate('admin', 'admin');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Authentication',
            $passed,
            $failed
        );

        $response = $client->createSpace('testspace', 'key-value');
        testAssert(
            isset($response['status']) && ($response['status'] === 'OK' || $response['status'] === 'ERROR'),
            'Create Space',
            $passed,
            $failed
        );

        $response = $client->useSpace('testspace');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Use Space',
            $passed,
            $failed
        );

        $response = $client->put('hello', 'world');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Put',
            $passed,
            $failed
        );

        $response = $client->get('hello');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Get status',
            $passed,
            $failed
        );
        testAssert(
            isset($response['value']) && $response['value'] === 'world',
            'Get value',
            $passed,
            $failed
        );

        $response = $client->listSpaces();
        testAssert(
            isset($response['status']) || isset($response['spaces']),
            'List Spaces',
            $passed,
            $failed
        );

        $client->close();
        testAssert(true, 'Close connection', $passed, $failed);

    } catch (ShibuDbAuthenticationError $e) {
        echo "FAIL: Authentication error: " . $e->getMessage() . "\n";
        $failed++;
    } catch (ShibuDbConnectionError $e) {
        echo "FAIL: Connection error: " . $e->getMessage() . "\n";
        echo "      Ensure ShibuDb server is running: sudo shibudb start 4444\n";
        $failed++;
    } catch (ShibuDbQueryError $e) {
        echo "FAIL: Query error: " . $e->getMessage() . "\n";
        $failed++;
    } catch (Exception $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        $failed++;
    }
}

function vectorTest(&$passed, &$failed)
{
    echo "\nVector Test\n";
    echo str_repeat('=', 40) . "\n";

    try {
        $client = new ShibuDbClient('localhost', 4444);
        $response = $client->authenticate('admin', 'admin');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Authentication',
            $passed,
            $failed
        );

        $response = $client->createSpace('vectest', 'vector', 3);
        testAssert(
            isset($response['status']) && ($response['status'] === 'OK' || $response['status'] === 'ERROR'),
            'Create Vector Space',
            $passed,
            $failed
        );

        $response = $client->useSpace('vectest');
        testAssert(
            isset($response['status']) && $response['status'] === 'OK',
            'Use Space',
            $passed,
            $failed
        );

        $vectors = array(
            array(1, array(1.0, 2.0, 3.0)),
            array(2, array(4.0, 5.0, 6.0)),
            array(3, array(1.1, 2.1, 3.1))
        );

        foreach ($vectors as $item) {
            $response = $client->insertVector($item[0], $item[1]);
            testAssert(
                isset($response['status']) && $response['status'] === 'OK',
                "Insert Vector {$item[0]}",
                $passed,
                $failed
            );
        }

        $queryVector = array(1.0, 2.0, 3.0);
        $response = $client->searchTopk($queryVector, 2);
        testAssert(
            isset($response['status']) || isset($response['message']),
            'Search TopK',
            $passed,
            $failed
        );

        $client->close();
        testAssert(true, 'Close connection', $passed, $failed);

    } catch (ShibuDbConnectionError $e) {
        echo "FAIL: Connection error - ensure server is running\n";
        $failed++;
    } catch (Exception $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        $failed++;
    }
}

// Run tests
echo "ShibuDb PHP Client - Simple Test\n";
echo str_repeat('=', 40) . "\n";

simpleTest($passed, $failed);
vectorTest($passed, $failed);

// Summary
echo "\n" . str_repeat('=', 40) . "\n";
echo "Results: $passed passed, $failed failed\n";

if ($failed === 0) {
    echo "All tests passed.\n";
    exit(0);
} else {
    echo "Some tests failed.\n";
    exit(1);
}
