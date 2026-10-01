<?php
// Simple database test with error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Test</h1>";

// Hardcode the connection for testing
$host = 'mysql.railway.internal';
$name = 'klps';
$user = 'root';
$pass = 'mkfIbwuAUJwczcQSCDOpFOiunbRzGmDx';

echo "Connecting to: $host<br>";
echo "Database: $name<br>";
echo "User: $user<br><br>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
    echo "✅ Connected successfully!<br>";
    
    $stmt = $pdo->query("SELECT DATABASE() as db");
    $row = $stmt->fetch();
    echo "Current database: " . $row['db'] . "<br>";
    
    $stmt = $pdo->query("SHOW TABLES");
    echo "<h3>Tables:</h3>";
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "📄 " . $row[0] . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>