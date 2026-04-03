<?php
$lines = file('storage/logs/laravel.log');
$recent = array_slice($lines, -50);
foreach ($recent as $line) {
    if (preg_match('/login|auth|419|Token|POST|CSRF/i', $line)) {
        echo trim($line) . "\n";
    }
}
?>
