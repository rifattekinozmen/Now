<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;

echo "=== CSRF Token Analysis ===\n\n";

// Create a request and session
$request = Request::create('/login', 'GET');
$app['request'] = $request;

// Get CSRF token generator
$tokenManager = app(\Illuminate\Contracts\Encryption\Encrypter::class);
echo "Encrypter cipher: " . config('app.cipher') . "\n";
echo "APP_KEY configured: " . (env('APP_KEY') ? 'YES' : 'NO') . "\n\n";

// Generate a token
$token1 = \Illuminate\Support\Str::random(80);
echo "Generated random token: " . substr($token1, 0, 20) . "...\n";

// Check session configuration
echo "\n=== Session Config ===\n";
echo "Driver: " . config('session.driver') . "\n";
echo "Encrypt: " . (config('session.encrypt') ? 'YES' : 'NO') . "\n";
echo "Cookie: " . config('session.cookie') . "\n";

// Try to generate a proper CSRF token
echo "\n=== Testing Token Generation ===\n";

// Start session
Session::start();
$sessionId = Session::getId();
echo "Session ID: " . substr($sessionId, 0, 20) . "...\n";

// Get CSRF token via token() helper (if available)
try {
    $csrfToken = csrf_token();
    echo "CSRF Token: " . substr($csrfToken, 0, 20) . "...\n";
    echo "✓ csrf_token() works\n";
} catch (\Exception $e) {
    echo "✗ csrf_token() error: " . $e->getMessage() . "\n";
}

// Check token storage
$stored = Session::get('_token');
echo "Token in session: " . ($stored ? substr($stored, 0, 20) . "..." : "NOT FOUND") . "\n";
?>
