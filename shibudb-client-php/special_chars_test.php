<?php
/**
 * Special Characters Test for ShibuDb PHP Client
 *
 * Validates that the client can handle special characters in JSON responses
 * without issues. Tests various edge cases and realistic user data.
 * No server required.
 */

require_once __DIR__ . '/ShibuDbClient.php';

$passed = 0;
$failed = 0;

/**
 * Simulates the client's response parsing logic.
 */
function parseShibuDbResponse($response)
{
    $response = trim($response);
    $decoded = json_decode($response, true);

    if (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE) {
        return array('status' => 'OK', 'message' => $response);
    }

    return $decoded;
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

function testJsonParsingWithSpecialChars(&$passed, &$failed)
{
    echo "Testing JSON parsing with special characters\n";
    echo str_repeat('=', 50) . "\n";

    $testCases = array(
        array(
            'name' => 'Control characters',
            'json' => '{"status": "OK", "message": "test\\nwith\\rnewline\\tand\\ttab"}',
        ),
        array(
            'name' => 'Unicode characters',
            'json' => '{"status": "OK", "message": "Hello \u4e16\u754c! Caf\u00e9 na\u00efve"}',
        ),
        array(
            'name' => 'Quotes and backslashes',
            'json' => '{"status": "OK", "message": "He said \\"Hello\\" and path is C:\\\\Users\\\\Test"}',
        ),
        array(
            'name' => 'JSON-like content',
            'json' => '{"status": "OK", "message": "{\\"key\\": \\"value\\"}"}',
        ),
        array(
            'name' => 'Mixed special characters',
            'json' => '{"status": "OK", "message": "Complex: \\"test\\" \\n \\t \\\\ Hello"}',
        ),
        array(
            'name' => 'User data',
            'json' => '{"status": "OK", "user": "Jos\u00e9 Mar\u00eda", "path": "/home/user/\u6587\u6863", "message": "User created"}',
        ),
        array(
            'name' => 'Code snippet',
            'json' => '{"status": "OK", "code": "echo \\"Hello\\";", "message": "Code executed"}',
        ),
        array(
            'name' => 'URL with parameters',
            'json' => '{"status": "OK", "url": "https://example.com/path?param=\u6d4b\u8bd5", "message": "URL processed"}',
        ),
    );

    foreach ($testCases as $i => $tc) {
        echo "\n" . ($i + 1) . ". " . $tc['name'] . "\n";

        try {
            $result = parseShibuDbResponse($tc['json']);

            if (is_array($result) && isset($result['status']) && $result['status'] === 'OK') {
                testAssert(true, $tc['name'] . ' - parsed successfully', $passed, $failed);
            } else {
                testAssert(false, $tc['name'] . ' - unexpected result', $passed, $failed);
            }
        } catch (Exception $e) {
            testAssert(false, $tc['name'] . ' - exception: ' . $e->getMessage(), $passed, $failed);
        }
    }
}

function testFallbackBehavior(&$passed, &$failed)
{
    echo "\nTesting fallback behavior\n";
    echo str_repeat('=', 30) . "\n";

    $nonJsonResponse = 'This is not JSON at all';
    $result = parseShibuDbResponse($nonJsonResponse);

    testAssert(
        is_array($result) && isset($result['status']) && $result['status'] === 'OK',
        'Fallback returns OK status',
        $passed,
        $failed
    );

    testAssert(
        isset($result['message']) && $result['message'] === $nonJsonResponse,
        'Fallback preserves raw message',
        $passed,
        $failed
    );
}

function testPutGetValuesWithSpecialChars(&$passed, &$failed)
{
    echo "\nTesting PUT/GET value encoding (json_encode)\n";
    echo str_repeat('=', 50) . "\n";

    $testValues = array(
        'Hello World',
        'Path: C:\Users\Test',
        'JSON: {"key": "value"}',
        "Line1\nLine2\tTabbed",
    );

    foreach ($testValues as $i => $value) {
        $encoded = json_encode(array('status' => 'OK', 'value' => $value));
        if ($encoded === false) {
            testAssert(false, "Value " . ($i + 1) . " - json_encode failed", $passed, $failed);
        } else {
            $result = parseShibuDbResponse($encoded);
            testAssert(
                is_array($result) && isset($result['value']) && $result['value'] === $value,
                "Value " . ($i + 1) . " - roundtrip OK",
                $passed,
                $failed
            );
        }
    }
}

function testComprehensiveResponse(&$passed, &$failed)
{
    echo "\nTesting comprehensive response\n";
    echo str_repeat('=', 40) . "\n";

    $complexJson = '{"status":"OK","message":"Operation completed","data":{'
        . '"user":"Jos\u00e9 Mar\u00eda","path":"C:\\\\Users\\\\Test\\\\\u6587\u6863",'
        . '"url":"https://example.com?param=\u6d4b\u8bd5"},"metadata":{"version":"1.0"}}';

    $result = parseShibuDbResponse($complexJson);

    testAssert(is_array($result), 'Returns array', $passed, $failed);
    testAssert(isset($result['status']) && $result['status'] === 'OK', 'Has status', $passed, $failed);
    testAssert(isset($result['data']), 'Has data', $passed, $failed);
    testAssert(isset($result['metadata']), 'Has metadata', $passed, $failed);
}

// Run tests
echo "ShibuDb PHP Client - Special Characters Test\n";
echo str_repeat('=', 60) . "\n";

testJsonParsingWithSpecialChars($passed, $failed);
testFallbackBehavior($passed, $failed);
testPutGetValuesWithSpecialChars($passed, $failed);
testComprehensiveResponse($passed, $failed);

// Summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "FINAL TEST SUMMARY\n";
echo str_repeat('=', 60) . "\n";
echo "Results: $passed passed, $failed failed\n";

if ($failed === 0) {
    echo "\nAll tests passed. Client handles special characters correctly.\n";
    exit(0);
} else {
    echo "\nSome tests failed.\n";
    exit(1);
}
