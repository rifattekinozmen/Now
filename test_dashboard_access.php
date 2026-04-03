<?php
echo "=== Testing Dashboard Access with Authenticated Session ===\n\n";

$loginUrl = 'http://127.0.0.1:8000/login';
$dashboardUrl = 'http://127.0.0.1:8000/dashboard';

$cookieFile = tempnam(sys_get_temp_dir(), 'cookies_');

// Login
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$page = curl_exec($ch);

// Extract and use CSRF token
preg_match('/name=["\']_token["\']\\s+value=["\']([^"\']+)["\']/', $page, $m);
$token = $m[1] ?? null;

if (!$token) {
    echo "✗ Could not get CSRF token\n";
    exit(1);
}

// Login POST
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_token' => $token,
    'email' => 'test@example.com',
    'password' => 'password',
    'remember' => 'on',
]));

curl_exec($ch);
echo "✓ Login request sent\n";

// Now access dashboard
curl_setopt($ch, CURLOPT_URL, $dashboardUrl);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, '');

$dashContent = curl_exec($ch);
$info = curl_getinfo($ch);

echo "Dashboard status: " . $info['http_code'] . "\n";

if ($info['http_code'] === 200) {
    echo "✓ Dashboard access successful!\n";
    
    // Check if we're actually authenticated (page contains dashboard content)
    if (preg_match('/dashboard|protected/i', $dashContent)) {
        echo "✓ Dashboard content found\n";
    }
} elseif ($info['http_code'] === 302) {
    echo "Dashboard redirect: " . (isset($info['redirect_url']) ? $info['redirect_url'] : 'unknown') . "\n";
} else {
    echo "? Unexpected status\n";
}

curl_close($ch);
@unlink($cookieFile);
?>
