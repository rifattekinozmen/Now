<?php
// Test login by making a raw HTTP request that preserves session
$loginPageUrl = 'http://127.0.0.1:8000/login';
$loginStoreUrl = 'http://127.0.0.1:8000/login';

echo "=== Web Login Test ===\n\n";

// Step 1: Get login page and extract CSRF token
echo "Step 1: Fetching login page...\n";
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'follow_location' => false,
    ]
]);

$pageContent = @file_get_contents($loginPageUrl, false, $context);
if (!$pageContent) {
    echo "✗ Failed to fetch login page\n";
    exit(1);
}

echo "✓ Got login page (" . strlen($pageContent) . " bytes)\n";

// Extract CSRF token
if (preg_match('/name=["\']_token["\']\\s+value=["\']([^"\']+)["\']/', $pageContent, $matches)) {
    $token = $matches[1];
    echo "✓ Found CSRF token: " . substr($token, 0, 20) . "...\n";
} else {
    echo "✗ Could not find _token in HTML\n";
    // Try to find any mention of token
    if (preg_match_all('/\btoken\b|_token|csrf/i', $pageContent, $matches)) {
        echo "  (Found " . count($matches[0]) . " token references)\n";
    }
    exit(1);
}

// Step 2: Perform login POST request
echo "\nStep 2: Posting login credentials...\n";

$postData = http_build_query([
    '_token' => $token,
    'email' => 'test@example.com',
    'password' => 'password',
    'remember' => 'on',
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postData),
            'User-Agent: TestClient/1.0',
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: http://127.0.0.1:8000/login',
        ],
        'content' => $postData,
        'timeout' => 10,
        'follow_location' => false,
    ]
]);

$response = @file_get_contents($loginStoreUrl, false, $context);

// Check response headers
$headers = $http_response_header ?? [];
echo "Response headers:\n";
foreach ($headers as $header) {
    if (preg_match('/(HTTP|Location|Set-Cookie)/i', $header)) {
        echo "  " . trim($header) . "\n";
    }
}

// Get status code
if ($headers) {
    if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $headers[0], $m)) {
        $statusCode = $m[1];
        echo "\n✓ Status code: $statusCode\n";
        
        if ($statusCode === '302' || $statusCode === '303') {
            // Check redirect location
            foreach ($headers as $h) {
                if (preg_match('/^Location:\\s*(.+)/i', $h, $m)) {
                    echo "✓ Redirected to: " . trim($m[1]) . "\n";
                    break;
                }
            }
        } elseif ($statusCode === '419') {
            echo "✗ Token mismatch (419)\n";
            echo "   The CSRF token was rejected\n";
        } else {
            echo "? Unexpected status: $statusCode\n";
        }
    }
}

echo "\n=== Check Laravel Logs ===\n";
$logs = file('storage/logs/laravel.log');
$recent = array_slice($logs, -20);
$foundAuth = false;
foreach ($recent as $log) {
    if (preg_match('/login|auth|Token|POST/i', $log)) {
        echo trim($log) . "\n";
        $foundAuth = true;
    }
}
if (!$foundAuth) {
    echo "(No authentication-related logs found)\n";
}
?>
