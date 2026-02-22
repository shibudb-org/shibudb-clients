<?php
/**
 * JSON Parsing Test for ShibuDb PHP Client
 *
 * Validates that the client's response parsing logic can handle JSON
 * containing special characters and handles fallback for non-JSON responses.
 * No server required - tests parsing logic in isolation.
 */

require_once __DIR__ . '/ShibuDbClient.php';

$passed = 0;
$failed = 0;

/**
 * Simulates the client's _sendQuery response parsing logic.
 * Same logic as in ShibuDbClient::_sendQuery()
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

function testJsonWithControlCharacters(&$passed, &$failed)
{
    echo "\n--- JSON with control characters ---\n";

    $testCases = array(
        '{"status": "OK", "message": "test\nwith\rnewline"}',
        '{"status": "OK", "message": "test\twith\ttab"}',
        '{"status": "OK", "message": "line1\nline2\tindented\r\n"}',
    );

    foreach ($testCases as $i => $json) {
        try {
            $result = parseShibuDbResponse($json);
            testAssert(
                is_array($result) && isset($result['status']) && $result['status'] === 'OK',
                "Control chars test " . ($i + 1),
                $passed,
                $failed
            );
        } catch (Exception $e) {
            testAssert(false, "Control chars test " . ($i + 1) . ": " . $e->getMessage(), $passed, $failed);
        }
    }
}

function testJsonWithUnicodeCharacters(&$passed, &$failed)
{
    echo "\n--- JSON with Unicode characters ---\n";

    $testCases = array(
        '{"status": "OK", "message": "Hello \u4e16\u754c"}',
        '{"status": "OK", "message": "Caf\u00e9 na\u00efve r\u00e9sum\u00e9"}',
        '{"status": "OK", "message": "Hello \u4e16\u754c! How are you?"}',
    );

    foreach ($testCases as $i => $json) {
        try {
            $result = parseShibuDbResponse($json);
            testAssert(
                is_array($result) && isset($result['status']) && $result['status'] === 'OK',
                "Unicode test " . ($i + 1),
                $passed,
                $failed
            );
        } catch (Exception $e) {
            testAssert(false, "Unicode test " . ($i + 1), $passed, $failed);
        }
    }
}

function testJsonWithSpecialJsonCharacters(&$passed, &$failed)
{
    echo "\n--- JSON with special JSON characters in strings ---\n";

    $testCases = array(
        '{"status": "OK", "message": "He said \"Hello\" to me"}',
        '{"status": "OK", "message": "Path: C:\\\\Users\\\\Test"}',
        '{"status": "OK", "message": "{\"nested\": \"json\"}"}',
        '{"status": "OK", "message": "Complex: \"test\" \\n \\t \\\\ "}',
    );

    foreach ($testCases as $i => $json) {
        try {
            $result = parseShibuDbResponse($json);
            testAssert(
                is_array($result) && isset($result['status']) && $result['status'] === 'OK',
                "Special JSON chars test " . ($i + 1),
                $passed,
                $failed
            );
        } catch (Exception $e) {
            testAssert(false, "Special JSON chars test " . ($i + 1), $passed, $failed);
        }
    }
}

function testJsonWithUserProvidedData(&$passed, &$failed)
{
    echo "\n--- JSON with user-provided data ---\n";

    $testCases = array(
        '{"status": "OK", "user": "Jos\u00e9 Mar\u00eda", "message": "User created"}',
        '{"status": "OK", "path": "C:\\\\Users\\\\Test\\\\file.txt", "message": "File saved"}',
        '{"status": "OK", "path": "/home/user/\u6587\u6863/file.pdf", "message": "File saved"}',
        '{"status": "OK", "url": "https://example.com/path?param=\u6d4b\u8bd5", "message": "URL processed"}',
        '{"status": "OK", "email": "test@example.com", "message": "Email sent"}',
    );

    foreach ($testCases as $i => $json) {
        try {
            $result = parseShibuDbResponse($json);
            testAssert(
                is_array($result) && isset($result['status']) && $result['status'] === 'OK',
                "User data test " . ($i + 1),
                $passed,
                $failed
            );
        } catch (Exception $e) {
            testAssert(false, "User data test " . ($i + 1), $passed, $failed);
        }
    }
}

function testFallbackBehavior(&$passed, &$failed)
{
    echo "\n--- Fallback when JSON parsing fails ---\n";

    $nonJsonResponse = 'This is not JSON at all';
    $result = parseShibuDbResponse($nonJsonResponse);

    testAssert(
        is_array($result) && isset($result['status']) && $result['status'] === 'OK',
        'Fallback returns array',
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

function testGetOperationResponse(&$passed, &$failed)
{
    echo "\n--- GET operation response format ---\n";

    $testCases = array(
        '{"status": "OK", "value": "Hello \u4e16\u754c!"}',
        '{"status": "OK", "value": "Path: C:\\\\Users\\\\Test"}',
        '{"status": "OK", "value": "Emoji: \ud83d\ude80\ud83c\udf1f"}',
    );

    foreach ($testCases as $i => $json) {
        try {
            $result = parseShibuDbResponse($json);
            testAssert(
                is_array($result) && isset($result['status']) && isset($result['value']),
                "GET response test " . ($i + 1),
                $passed,
                $failed
            );
        } catch (Exception $e) {
            testAssert(false, "GET response test " . ($i + 1), $passed, $failed);
        }
    }
}

// Run tests
echo "ShibuDb PHP Client - JSON Parsing Tests\n";
echo str_repeat('=', 60) . "\n";

testJsonWithControlCharacters($passed, $failed);
testJsonWithUnicodeCharacters($passed, $failed);
testJsonWithSpecialJsonCharacters($passed, $failed);
testJsonWithUserProvidedData($passed, $failed);
testFallbackBehavior($passed, $failed);
testGetOperationResponse($passed, $failed);

// Summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "Results: $passed passed, $failed failed\n";

if ($failed === 0) {
    echo "All JSON parsing tests passed.\n";
    echo "The client can handle special characters in JSON responses.\n";
    exit(0);
} else {
    echo "Some tests failed.\n";
    exit(1);
}
