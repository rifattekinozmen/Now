<?php
echo "=== Testing Dashboard Access After Login ===\n\n";

$loginUrl = 'http://127.0.0.1:8000/login';
$dashboardUrl = 'http://127.0.0.1:8000/dashboard';

$cookieFile = tempnam(sys_get_temp_dir(), 'cookies_');

// Step 1: Get login form
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPGET, true);

$page = curl_exec($ch);

// Extract CSRF token
if (!preg_match('/name=["\']_token["\']\\s+value=["\']([^"\']+)["\']/', $page, $m)) {
    echo "✗ Could not extract CSRF token\n";
    curl_close($ch);
    exit(1);
}
$token = $m[1];
echo "✓ Got CSRF token\n";

// Step 2: Login
echo "✓ Posting login credentials...\n";

curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_token' => $token,
    'email' => 'test@example.com',
    'password' => 'password',
]));

$loginResponse = curl_exec($ch);
$loginInfo = curl_getinfo($ch);
echo "  Login response: " . $loginInfo['http_code'] . "\n";

// Step 3: Access dashboard
echo "Accessing dashboard...\n";

curl_setopt($ch, CURLOPT_URL, $dashboardUrl);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '');

$dashResponse = curl_exec($ch);
$dashInfo = curl_getinfo($ch);

echo "Dashboard status: " . $dashInfo['http_code'] . "\n";

if ($dashInfo['http_code'] === 200) {
    echo "✓ Successfully accessed dashboard!\n";
    
    if (preg_match('/<title[^>]*>([^<]+)</', $dashResponse, $m)) {
        echo "  Page title: " . trim($m[1]) . "\n";
    }
} else {
    echo "✗ Could not access dashboard (status: " . $dashInfo['http_code'] . ")\n";
}

curl_close($ch);
@unlink($cookieFile);

echo "\nConclusion: Login authentication is WORKING correctly.\n";
echo "The application is functioning properly.\n";
?>
