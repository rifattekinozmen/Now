<?php
// Check if 'password' matches the stored hash
$stored_hash = '$2y$12$PeG2QjnHI1J4HWVr7D/Jg.K6uSLXJvOKzNVq9lTBzDmJVPr7YXHjm'; // from output above (assumed full hash)

// First, let's see what the factory creates
require 'vendor/autoload.php';

// Manually hash 'password' with bcrypt
$test_password = 'password';
$hashed = password_hash($test_password, PASSWORD_BCRYPT, ['rounds' => 12]);

echo "Test password 'password' hashes to:\n";
echo $hashed . "\n\n";

// Now let's check what the factory actually produces
// Get the full hash from database
$env_file = file_get_contents('.env');
$db_user = $db_pass = $db_name = '';
foreach (explode("\n", $env_file) as $line) {
    if (strpos($line, 'DB_USERNAME=') === 0) $db_user = trim(substr($line, 12));
    elseif (strpos($line, 'DB_PASSWORD=') === 0) $db_pass = trim(substr($line, 12));
    elseif (strpos($line, 'DB_DATABASE=') === 0) $db_name = trim(substr($line, 12));
}

$mysqli = new mysqli('127.0.0.1:33061', $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) die('Connection failed: ' . $mysqli->connect_error);

$result = $mysqli->query('SELECT password FROM users WHERE email="test@example.com" LIMIT 1');
$row = $result->fetch_assoc();
$stored = $row['password'];

echo "Stored password hash in database:\n";
echo $stored . "\n\n";

echo "Testing password_verify('password', stored_hash):\n";
$matches = password_verify($test_password, $stored);
echo ($matches ? "✓ MATCH - Password is correct" : "✗ NO MATCH - Password is incorrect") . "\n";

$mysqli->close();
?>
