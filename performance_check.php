<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║           LARAVEL PERFORMANCE DIAGNOSTICS - BOOST CHECK            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// 1. Cache Configuration
echo "1. CACHE CONFIGURATION\n";
echo "   Default store: " . config('cache.default') . "\n";
echo "   Configured in .env: " . (env('CACHE_STORE') ?: 'NOT SET (using default)') . "\n";

if (config('cache.default') === 'database') {
    echo "   ⚠️  WARNING: Using DATABASE cache (SLOW for web requests)\n";
} elseif (config('cache.default') === 'redis') {
    echo "   ✓ Using Redis (GOOD - fast)\n";
} elseif (config('cache.default') === 'array') {
    echo "   ✗ Using Array cache (only in memory, no persistence)\n";
}

// 2. Log Level
echo "\n2. LOGGING CONFIGURATION\n";
echo "   Log channel: " . config('logging.default') . "\n";
echo "   Log level: " . config('logging.channels.single.level') . "\n";

if (env('LOG_LEVEL') === 'debug') {
    echo "   ⚠️  WARNING: LOG_LEVEL=debug (writes EVERY operation to disk)\n";
    echo "      This SIGNIFICANTLY slows down production\n";
    echo "      Should be: warning, error, or critical\n";
}

// 3. Database Queries
echo "\n3. DATABASE QUERY LOGGING\n";
$queryLoggingEnabled = config('database.log.enabled');
echo "   Query logging enabled: " . ($queryLoggingEnabled ? 'YES ⚠️ SLOW' : 'NO ✓') . "\n";

// 4. Session Driver
echo "\n4. SESSION CONFIGURATION\n";
echo "   Session driver: " . config('session.driver') . "\n";
echo "   Session lifetime: " . config('session.lifetime') . " minutes\n";

if (config('session.driver') === 'database') {
    echo "   ⚠️  Database sessions are slower than Redis\n";
    echo "      Consider using: SESSION_DRIVER=redis\n";
}

// 5. Queue Configuration
echo "\n5. QUEUE CONFIGURATION\n";
echo "   Queue driver: " . config('queue.default') . "\n";
if (config('queue.default') === 'redis') {
    echo "   ✓ Using Redis (GOOD)\n";
} elseif (config('queue.default') === 'sync') {
    echo "   ✗ Using SYNC (blocking - slows down requests)\n";
}

// 6. Redis Connection Test
echo "\n6. REDIS CONNECTION\n";
try {
    $redis = new \Predis\Client([
        'scheme' => 'tcp',
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 0,
    ]);
    $redis->ping();
    echo "   ✓ Redis connection OK\n";
} catch (\Exception $e) {
    echo "   ✗ Redis connection failed: " . $e->getMessage() . "\n";
}

// 7. App Debug Mode
echo "\n7. DEBUG MODE\n";
echo "   APP_DEBUG: " . (config('app.debug') ? 'ON ⚠️ SLOWER' : 'OFF ✓') . "\n";

// 8. File Size Check
echo "\n8. LOG FILE SIZE\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $sizeMB = filesize($logFile) / (1024 * 1024);
    echo "   laravel.log size: " . round($sizeMB, 2) . " MB\n";
    if ($sizeMB > 100) {
        echo "   ⚠️  HUGE LOG FILE - causes slowness when writing\n";
        echo "      Run: php artisan logs:clear\n";
    }
}

echo "\n" . str_repeat("═", 68) . "\n";
echo "RECOMMENDATIONS FOR FASTER PAGE LOADS:\n";
echo str_repeat("═", 68) . "\n";

$issues = [];

if (config('cache.default') === 'database') {
    $issues[] = "1. Change CACHE_STORE=redis in .env\n   Edit config/cache.php default to use redis";
}

if (env('LOG_LEVEL') === 'debug') {
    $issues[] = "2. Change LOG_LEVEL=warning in .env\n   (Only log warnings/errors, not every operation)";
}

if (config('session.driver') === 'database') {
    $issues[] = "3. Consider SESSION_DRIVER=redis in .env\n   (Faster session storage)";
}

if (config('app.debug')) {
    $issues[] = "4. Set APP_DEBUG=false in .env for production\n   (Debugging adds overhead)";
}

if (file_exists($logFile) && filesize($logFile) > 100 * 1024 * 1024) {
    $issues[] = "5. Clear large log file: php artisan logs:clear";
}

if (empty($issues)) {
    echo "✓ All performance configurations look good!\n";
    echo "\nIf pages are still slow, check:\n";
    echo "  - N+1 query problems in your Livewire components\n";
    echo "  - Heavy calculations in middleware\n";
    echo "  - Large dataset queries without pagination\n";
} else {
    echo implode("\n\n", $issues) . "\n";
}

echo "\n" . str_repeat("═", 68) . "\n";
?>
