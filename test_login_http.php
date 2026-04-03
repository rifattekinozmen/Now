<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a mock request
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Enable file logging
Log::channel('stack')->info('Test login simulation started');

// Simulate the login request
$request = Request::create('/login', 'POST', [
    'email' => 'test@example.com',
    'password' => 'password',
]);

// Set the request in the container
$app['request'] = $request;

// Get the HTTP kernel
$httpKernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

// Process the request
try {
    $response = $httpKernel->handle($request);
    echo "Response status: " . $response->status() . "\n";
    echo "Response type: " . get_class($response) . "\n";
    
    // Check if we got a redirect
    if ($response->status() === 302) {
        echo "Redirect location: " . $response->headers->get('Location') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Read logs
$logFile = 'storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recent = array_slice($lines, -20);
    echo "\n--- Last 20 log lines ---\n";
    echo implode('', $recent);
}
?>
