<?php
$env_file = file_get_contents('.env');
$db_user = '';
$db_pass = '';
$db_name = '';

foreach (explode("\n", $env_file) as $line) {
    if (strpos($line, 'DB_USERNAME=') === 0) {
        $db_user = trim(substr($line, 12));
    } elseif (strpos($line, 'DB_PASSWORD=') === 0) {
        $db_pass = trim(substr($line, 12));
    } elseif (strpos($line, 'DB_DATABASE=') === 0) {
        $db_name = trim(substr($line, 12));
    }
}

echo "Connecting with: user=$db_user, pass=$db_pass, db=$db_name\n";

$mysqli = new mysqli('127.0.0.1:33061', $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

$result = $mysqli->query('SELECT id, name, email, password FROM users WHERE email="test@example.com"');
if ($result) {
    $row = $result->fetch_assoc();
    if ($row) {
        echo "User found:\n";
        echo "ID: " . $row['id'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Password Hash: " . substr($row['password'], 0, 20) . "...\n";
    } else {
        echo "No user found with email test@example.com\n";
        // List all users
        $all = $mysqli->query('SELECT id, name, email FROM users');
        if ($all && $all->num_rows > 0) {
            echo "\nAll users:\n";
            while ($u = $all->fetch_assoc()) {
                echo "ID: " . $u['id'] . ", Name: " . $u['name'] . ", Email: " . $u['email'] . "\n";
            }
        } else {
            echo "No users exist in database\n";
        }
    }
} else {
    echo 'Query error: ' . $mysqli->error . "\n";
}

$mysqli->close();
?>
