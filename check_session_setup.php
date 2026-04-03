<?php
$env_file = file_get_contents('.env');
$db_pass = '';
foreach (explode("\n", $env_file) as $line) {
    if (strpos($line, 'DB_PASSWORD=') === 0) {
        $db_pass = trim(substr($line, 12));
    }
}

echo "=== Database Check ===\n";
$mysqli = new mysqli('127.0.0.1:33061', 'now', $db_pass, 'now');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

// Check if sessions table exists
$result = $mysqli->query("SHOW TABLES LIKE 'sessions'");
if ($result->num_rows > 0) {
    echo "✓ Sessions table exists\n";
    
    // Count sessions
    $count = $mysqli->query("SELECT COUNT(*) FROM sessions")->fetch_row()[0];
    echo "  Sessions in database: $count\n";
} else {
    echo "✗ Sessions table does NOT exist!\n";
    echo "  This is the problem! Create it with: php artisan session:table && php artisan migrate\n";
}

echo "\n=== Cache/Redis Check ===\n";
echo "Attempting to connect to Redis at 127.0.0.1:63790...\n";
try {
    $redis = new Redis();
    if ($redis->connect('127.0.0.1', 63790, 2)) {
        echo "✓ Redis connection successful\n";
        
        // Try a simple operation
        $redis->set('test_key', 'test_value', 5);
        $value = $redis->get('test_key');
        echo "  Test set/get: " . ($value === 'test_value' ? '✓ OK' : '✗ FAILED') . "\n";
        $redis->close();
    } else {
        echo "✗ Redis connection failed\n";
    }
} catch (\Exception $e) {
    echo "✗ Redis error: " . $e->getMessage() . "\n";
}

$mysqli->close();
?>
