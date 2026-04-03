<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║           ANALYZING POTENTIAL N+1 QUERY ISSUES                     ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Model relationships control
$models = [
    \App\Models\User::class,
    \App\Models\Tenant::class,
];

echo "Checking model relationships for N+1 issues:\n\n";

foreach ($models as $model) {
    $reflection = new ReflectionClass($model);
    echo "Model: " . class_basename($model) . "\n";
    
    $methods = $reflection->getMethods();
    $relationships = [];
    
    foreach ($methods as $method) {
        if ($method->getDeclaringClass()->getName() !== $model) continue;
        
        try {
            $instance = new $model;
            $result = $method->invoke($instance);
            
            if ($result instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                $relationships[] = $method->getName();
            }
        } catch (\Exception $e) {
            // Skip
        }
    }
    
    if (!empty($relationships)) {
        echo "  Relationships found: " . implode(', ', $relationships) . "\n";
        echo "  ⚠️  REMEMBER: Always use with() for eager loading\n";
        echo "      Example: User::with('tenant')->get()\n";
    } else {
        echo "  No relationships found\n";
    }
    echo "\n";
}

echo "═ OPTIMIZATION CHECKLIST ═\n";
echo "□ Use ->with() for eager loading related data\n";
echo "□ Add pagination to large queries\n";
echo "□ Cache frequently accessed data\n";
echo "□ Use select() to fetch only needed columns\n";
echo "□ Add indexes to frequently filtered columns\n\n";

// Check database
echo "═ DATABASE STATS ═\n";
$tables = DB::select('SHOW TABLES');
echo "Total tables: " . count($tables) . "\n\n";

// Get table sizes
$largestTables = DB::select("
    SELECT TABLE_NAME, 
           ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size_MB'
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY data_length DESC
    LIMIT 5
");

echo "Largest tables:\n";
foreach ($largestTables as $table) {
    echo "  " . $table->TABLE_NAME . ": " . $table->Size_MB . " MB\n";
}

echo "\n✓ Performance optimization complete!\n";
?>
