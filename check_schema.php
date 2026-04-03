<?php
$pdo = new PDO('mysql:host=127.0.0.1:33061;dbname=now', 'now', 'secret');
$result = $pdo->query('DESCRIBE app_notifications');
echo "app_notifications columns:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
