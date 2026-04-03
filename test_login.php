<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Now we can use Laravel services
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

$credentials = [
    'email' => 'test@example.com',
    'password' => 'password',
];

echo "Attempting login with credentials:\n";
echo "Email: " . $credentials['email'] . "\n";
echo "Password: " . $credentials['password'] . "\n\n";

// Try standard Laravel authentication
$guard = Auth::guard('web');
echo "Using guard: web\n";

if ($guard->attempt($credentials, false)) {
    echo "✓ Authentication successful!\n";
    $user = $guard->user();
    echo "User ID: " . $user->id . "\n";
    echo "User Name: " . $user->name . "\n";
    echo "User Email: " . $user->email . "\n";
} else {
    echo "✗ Authentication FAILED\n";
    
    // Debug: Try to find user and check password manually
    $user = \App\Models\User::where('email', $credentials['email'])->first();
    if ($user) {
        echo "\nDebug Info:\n";
        echo "User found: " . $user->name . "\n";
        echo "Email match: " . ($user->email === $credentials['email'] ? 'YES' : 'NO') . "\n";
        echo "Password verify: " . (password_verify($credentials['password'], $user->password) ? 'YES' : 'NO') . "\n";
        
        // Check if there's email verification requirement
        echo "Email verified at: " . ($user->email_verified_at ?? 'NOT VERIFIED') . "\n";
    } else {
        echo "\nDebug: User not found in database!\n";
    }
}
?>
