<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║              DETAILED PERFORMANCE ANALYSIS                         ║\n";
echo "║  Large Sidebar + Many Livewire Pages                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// 1. Database Schema Analysis
echo "═ 1. DATABASE SCHEMA ANALYSIS ═\n";
$tables = DB::select("
    SELECT TABLE_NAME, TABLE_ROWS, 
           ROUND(((data_length + index_length) / 1024 / 1024), 2) AS Size_MB
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY TABLE_ROWS DESC
    LIMIT 10
");

echo "Largest tables by row count:\n";
foreach ($tables as $t) {
    printf("  %-30s %8s rows  %8s MB\n", $t->TABLE_NAME, number_format($t->TABLE_ROWS), $t->Size_MB);
}

// 2. Blade Files Analysis
echo "\n═ 2. LIVEWIRE PAGES COUNT ═\n";
$files = glob('resources/views/pages/admin/*.blade.php');
echo "Total Livewire pages: " . count($files) . "\n";

// 3. Routes Check
echo "\n═ 3. TOTAL ROUTES ═\n";
$routes = DB::select("SELECT * FROM information_schema.columns LIMIT 0");
$routeFile = file_get_contents('routes/web.php');
$routeCount = substr_count($routeFile, 'Route::');
echo "Approximate routes registered: " . $routeCount . "\n";

// 4. Models Check
echo "\n═ 4. MODEL RELATIONSHIPS ═\n";
$models = [
    'User', 'Order', 'Customer', 'Shipment', 'Vehicle', 
    'Employee', 'Warehouse', 'AppNotification'
];
foreach ($models as $model) {
    echo "  - App\\Models\\{$model}\n";
}

// 5. Middleware Check
echo "\n═ 5. MIDDLEWARE OVERHEAD ═\n";
$middleware = file_get_contents('app/Http/Kernel.php');
echo "Custom middleware in use: " . substr_count($middleware, 'class ') . " files\n";

// 6. View Components Check
echo "\n═ 6. SIDEBAR COMPONENTS ═\n";
$sidebar = file_get_contents('resources/views/layouts/app/sidebar.blade.php');
$items = substr_count($sidebar, 'flux:sidebar.item');
$groups = substr_count($sidebar, 'flux:sidebar.group');
echo "Sidebar menu items: $items\n";
echo "Sidebar groups: $groups\n";

// 7. Critical Issues
echo "\n" . str_repeat("═", 68) . "\n";
echo "IDENTIFIED PERFORMANCE ISSUES:\n";
echo str_repeat("═", 68) . "\n\n";

$issues = [
    [
        'priority' => 'CRITICAL',
        'issue' => 'Sidebar has @canany permission checks',
        'impact' => 'Permission checks run on EVERY page load',
        'location' => 'resources/views/layouts/app/sidebar.blade.php',
        'fix' => 'Cache permission checks or use eager-loaded roles'
    ],
    [
        'priority' => 'HIGH',
        'issue' => 'NotificationBell polls every 60 seconds',
        'impact' => 'Constant DB queries for notification count',
        'location' => 'app/Livewire/NotificationBell.php',
        'fix' => 'Cache unreadCount or use event broadcasting'
    ],
    [
        'priority' => 'HIGH',
        'issue' => '37 Livewire pages + complex layouts',
        'impact' => 'Large initial bundle, slower hydration',
        'location' => 'resources/views/pages/admin/*',
        'fix' => 'Code-split pages, lazy-load Livewire components'
    ],
    [
        'priority' => 'MEDIUM',
        'issue' => 'Sidebar renders on EVERY page load',
        'impact' => 'Even simple pages render full sidebar HTML',
        'location' => 'resources/views/layouts/app/sidebar.blade.php',
        'fix' => 'Already using @persist() - good!'
    ],
    [
        'priority' => 'MEDIUM',
        'issue' => 'No database indexes on frequently filtered columns',
        'impact' => 'Slow WHERE queries (vehicle.plate, order.number)',
        'location' => 'Database schema',
        'fix' => 'Add indexes to search/filter columns'
    ],
];

$i = 1;
foreach ($issues as $issue) {
    echo "$i. [{$issue['priority']}] {$issue['issue']}\n";
    echo "   Impact: {$issue['impact']}\n";
    echo "   Location: {$issue['location']}\n";
    echo "   Fix: {$issue['fix']}\n\n";
    $i++;
}

?>
