<?php
// Perform login with proper session and cookie handling
echo "=== Proper Web Login Test with Session Preservation ===\n\n";

$loginUrl = 'http://127.0.0.1:8000/login';

// Create a temporary cookie jar file
$cookieFile = tempnam(sys_get_temp_dir(), 'cookies_');
echo "Using cookie jar: $cookieFile\n\n";

// Step 1: GET /login and extract CSRF token
echo "Step 1: Getting login page and extracting token...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

$pageContent = curl_exec($ch);
$info = curl_getinfo($ch);

if (!$pageContent) {
    echo "✗ Failed: " . curl_error($ch) . "\n";
    curl_close($ch);
    exit(1);
}

echo "✓ Got page (" . strlen($pageContent) . " bytes)\n";

// Extract CSRF token
if (preg_match('/name=["\']_token["\']\\s+value=["\']([^"\']+)["\']/', $pageContent, $matches)) {
    $token = $matches[1];
    echo "✓ Extracted CSRF token: " . substr($token, 0, 20) . "...\n";
} else {
    echo "✗ Could not find CSRF token\n";
    curl_close($ch);
    exit(1);
}

// Step 2: POST login with preserved session
echo "\nStep 2: Posting login credentials with session...\n";

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_token' => $token,
    'email' => 'test@example.com',
    'password' => 'password',
    'remember' => 'on',
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Referer: ' . $loginUrl,
]);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$info = curl_getinfo($ch);

echo "Response status: " . $info['http_code'] . "\n";

if ($info['http_code'] === 419) {
    echo "✗ Token validation failed (419)\n";
    echo "  This means the CSRF token in the POST didn't match\n";
} elseif ($info['http_code'] === 302 || $info['http_code'] === 303) {
    echo "✓ Login successful - redirect response\n";
    if (isset($info['redirect_url'])) {
        echo "  Redirected to: " . $info['redirect_url'] . "\n";
    }
} else {
    echo "? Unexpected status code\n";
}

// Check final cookies
if (file_exists($cookieFile)) {
    $cookies = file_get_contents($cookieFile);
    echo "\nSession cookie file content:\n";
    $lines = array_filter(explode("\n", $cookies), fn($l) => !preg_match('/^#|^$/', trim($l)));
    foreach ($lines as $line) {
        echo "  $line\n";
    }
}

curl_close($ch);
@unlink($cookieFile);

echo "\n=== Check Logs for Errors ===\n";
$logs = @file('storage/logs/laravel.log');
if ($logs) {
    $recent = array_slice($logs, -15);
    foreach ($recent as $log) {
        if (preg_match('/(Token|CSRF|419|validation)/i', $log)) {
            echo trim($log) . "\n";
        }
    }
}
?>
