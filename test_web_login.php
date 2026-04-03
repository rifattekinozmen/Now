<?php
use Symfony\Component\DomCrawler\Crawler;

require 'vendor/autoload.php';

$base = 'http://127.0.0.1:8000';

// Create a simple HTTP client (using raw file_get_contents)
echo "Step 1: Getting login page...\n";

$loginPageContent = file_get_contents($base . '/login', false, stream_context_create([
    'http' => [
        'timeout' => 10,
    ]
]));

if (!$loginPageContent) {
    echo "✗ Failed to get login page\n";
    exit(1);
}

echo "✓ Got login page (" . strlen($loginPageContent) . " bytes)\n";

// Extract CSRF token using simple regex
$pattern = '/name=["\']csrf_token["\']\\s+value=["\']([^"\']+)["\']|name=["\']_token["\']\\s+value=["\']([^"\']+)["\']/';
if (preg_match($pattern, $loginPageContent, $matches)) {
    $csrfToken = $matches[1] ?: $matches[2];
    echo "✓ Found CSRF token: " . substr($csrfToken, 0, 20) . "...\n";
} else {
    echo "✗ Could not find CSRF token\n";
    // Show a snippet
    if (preg_match('/csrf|_token|token/i', $loginPageContent)) {
        echo "  (Found token references in page)\n";
        preg_match('/csrf.*?value=["\']([^"\']+)["\']|_token.*?value=["\']([^"\']+)["\']/i', $loginPageContent, $m);
        if ($m) {
            $csrfToken = $m[1] ?: $m[2];
            echo "  Using token: " . substr($csrfToken, 0, 20) . "...\n";
        } else {
            echo "  Cannot extract token\n";
            exit(1);
        }
    } else {
        exit(1);
    }
}

echo "\nStep 2: Attempting login...\n";

// Use cURL via exec
$curlCmd = 'powershell -Command "' .
    'Invoke-WebRequest -Uri ' . escapeshellarg($base . '/login') .
    ' -Method POST' .
    ' -Body @{email=\'test@example.com\'; password=\'password\'; csrf_token=\'' . $csrfToken . '\'; remember=\'on\'}' .
    ' -UseBasicParsing' .
    ' -SessionVariable session' .
    ' | % { $_.StatusCode }"';

exec($curlCmd, $output, $returnCode);

echo "Login attempt result: " . implode("\n", $output) . "\n";
echo "Return code: $returnCode\n";

?>
